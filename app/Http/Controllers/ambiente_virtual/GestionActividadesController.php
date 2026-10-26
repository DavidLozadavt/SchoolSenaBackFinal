<?php

namespace App\Http\Controllers\ambiente_virtual;

use App\Http\Controllers\Controller;
use App\Models\Actividad;
use App\Services\ambiente_virtual\RapAccessService;
use App\Support\ActividadEliminacionReglas;
use App\Util\KeyUtil;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogo general de actividades del instructor (Competencia → RAP → Actividad).
 * La ficha no forma parte de la creación. Publicar = calificacionActividad.
 */
class GestionActividadesController extends Controller
{
    public function __construct(private RapAccessService $rapAccess)
    {
    }

    public function filtros(): JsonResponse
    {
        $gate = $this->asegurarInstructor();
        if ($gate instanceof JsonResponse) {
            return $gate;
        }

        try {
            return response()->json([
                'competencias' => $this->rapAccess->competenciasAccesibles(),
                'raps' => $this->rapAccess->rapsAccesibles(),
            ]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function catalogo(Request $request): JsonResponse
    {
        $gate = $this->asegurarInstructor();
        if ($gate instanceof JsonResponse) {
            return $gate;
        }

        try {
            $user = KeyUtil::user();
            $idPersona = (int) ($user?->idpersona ?? 0);
            $idCompany = KeyUtil::idCompany();
            if ($idPersona <= 0) {
                return response()->json(['error' => 'Usuario autenticado sin persona asociada'], 401);
            }

            $page = max(1, (int) $request->query('page', 1));
            $perPage = (int) $request->query('per_page', 30);
            if ($perPage < 1) {
                $perPage = 30;
            }
            $perPage = min(50, $perPage);

            $idCompetencia = (int) $request->query('idCompetencia', 0);
            $idRap = (int) $request->query('idRap', 0);
            $search = trim((string) $request->query('search', ''));

            if ($idCompetencia > 0 && ! $this->rapAccess->usuarioPuedeAccederCompetencia($idCompetencia)) {
                return response()->json(['error' => 'No autorizado para esa competencia.'], 403);
            }
            if ($idRap > 0 && ! $this->rapAccess->usuarioPuedeAccederRap($idRap)) {
                return response()->json(['error' => 'No autorizado para ese RAP.'], 403);
            }
            if ($idCompetencia > 0 && $idRap > 0) {
                $idsRapComp = $this->rapAccess->idsRapDeCompetencia($idCompetencia);
                if (! in_array($idRap, $idsRapComp, true)) {
                    return response()->json([
                        'error' => 'El RAP seleccionado no pertenece a la competencia.',
                        'code' => 'RAP_COMPETENCIA_INCONSISTENTE',
                    ], 422);
                }
            }

            $query = DB::table('actividades as a')
                ->leftJoin('materia as rap', 'a.idMateria', '=', 'rap.id')
                ->leftJoin('materia as comp', 'rap.idMateriaPadre', '=', 'comp.id')
                ->leftJoin('estado as e', 'a.idEstado', '=', 'e.id')
                ->leftJoin('persona as p', 'a.idPersona', '=', 'p.id')
                ->where('a.idCompany', $idCompany)
                ->where('a.idPersona', $idPersona);

            if ($idRap > 0) {
                $query->where('a.idMateria', $idRap);
            } elseif ($idCompetencia > 0) {
                $idsRap = array_values(array_intersect(
                    $this->rapAccess->idsRapDeCompetencia($idCompetencia),
                    $this->rapAccess->idsRapAccesibles()
                ));
                if ($idsRap === []) {
                    return $this->paginadoVacio($page, $perPage);
                }
                $query->whereIn('a.idMateria', $idsRap);
            }

            if ($search !== '') {
                $like = '%'.$search.'%';
                $query->where(function ($q) use ($like) {
                    $q->where('a.tituloActividad', 'like', $like)
                        ->orWhere('a.autor', 'like', $like)
                        ->orWhere('a.entregables', 'like', $like)
                        ->orWhere('a.descripcionActividad', 'like', $like)
                        ->orWhere('rap.nombreMateria', 'like', $like)
                        ->orWhere('rap.codigo', 'like', $like)
                        ->orWhere('comp.nombreMateria', 'like', $like)
                        ->orWhere('comp.codigo', 'like', $like)
                        ->orWhere('p.nombre1', 'like', $like)
                        ->orWhere('p.nombre2', 'like', $like)
                        ->orWhere('p.apellido1', 'like', $like)
                        ->orWhere('p.apellido2', 'like', $like);
                });
            }

            $total = (int) (clone $query)->distinct()->count('a.id');
            $lastPage = max(1, (int) ceil($total / $perPage));
            if ($page > $lastPage) {
                $page = $lastPage;
            }

            $rows = $query
                ->select([
                    'a.id',
                    'a.tituloActividad',
                    'a.descripcionActividad',
                    'a.tipoActividad',
                    'a.entregables',
                    'a.idMateria',
                    'a.idEstado',
                    'a.idPersona',
                    'a.autor',
                    'rap.nombreMateria as rapNombre',
                    'rap.codigo as rapCodigo',
                    'rap.idMateriaPadre as idCompetencia',
                    'comp.nombreMateria as competenciaNombre',
                    'comp.codigo as competenciaCodigo',
                    'e.estado as estadoNombre',
                    'p.nombre1',
                    'p.nombre2',
                    'p.apellido1',
                    'p.apellido2',
                ])
                ->orderByDesc('a.id')
                ->offset(($page - 1) * $perPage)
                ->limit($perPage)
                ->get();

            $ids = $rows->pluck('id')->map(fn ($id) => (int) $id)->filter(fn ($id) => $id > 0)->values()->all();
            $conteoFichas = $this->conteoFichasAsignadas($ids);

            $data = $rows->map(function ($row) use ($conteoFichas) {
                $id = (int) $row->id;
                $idRap = (int) ($row->idMateria ?? 0);
                $idCompetencia = (int) ($row->idCompetencia ?? 0);
                $fichasAsignadas = (int) ($conteoFichas[$id] ?? $conteoFichas[(string) $id] ?? 0);
                $inactivo = ActividadEliminacionReglas::esInactivo(
                    $row->idEstado ? (int) $row->idEstado : null,
                    $row->estadoNombre ? (string) $row->estadoNombre : null
                );
                $motivoNoEliminar = ActividadEliminacionReglas::motivoNoEliminar($inactivo, $fichasAsignadas);
                $nombreCreador = trim(implode(' ', array_filter([
                    $row->nombre1 ?? null,
                    $row->nombre2 ?? null,
                    $row->apellido1 ?? null,
                    $row->apellido2 ?? null,
                ])));

                return [
                    'id' => $id,
                    'codigo' => (string) $id,
                    'tituloActividad' => $row->tituloActividad,
                    'descripcionActividad' => $row->descripcionActividad,
                    'tipoActividad' => $row->tipoActividad,
                    'entregables' => $row->entregables,
                    'idMateria' => $idRap,
                    'idEstado' => $row->idEstado ? (int) $row->idEstado : null,
                    'idPersona' => $row->idPersona ? (int) $row->idPersona : null,
                    'autor' => $row->autor,
                    'creadorNombre' => $nombreCreador !== '' ? $nombreCreador : ($row->autor ?: null),
                    'estado' => $row->estadoNombre,
                    'rap' => $idRap > 0 ? [
                        'id' => $idRap,
                        'nombreMateria' => $row->rapNombre,
                        'codigo' => $row->rapCodigo,
                    ] : null,
                    'competencia' => $idCompetencia > 0 ? [
                        'id' => $idCompetencia,
                        'nombreMateria' => $row->competenciaNombre,
                        'codigo' => $row->competenciaCodigo,
                    ] : null,
                    'fichasAsignadas' => $fichasAsignadas,
                    'puedeEliminar' => $motivoNoEliminar === null,
                    'motivoNoEliminar' => $motivoNoEliminar,
                ];
            })->values()->all();

            $from = $total === 0 ? 0 : (($page - 1) * $perPage) + 1;
            $to = min($page * $perPage, $total);

            return response()->json([
                'data' => $data,
                'meta' => [
                    'current_page' => $page,
                    'per_page' => $perPage,
                    'total' => $total,
                    'last_page' => $lastPage,
                    'from' => $from,
                    'to' => $to,
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function fichasAsignadas(int $idActividad): JsonResponse
    {
        $gate = $this->asegurarActividadPropia($idActividad);
        if ($gate instanceof JsonResponse) {
            return $gate;
        }

        try {
            if (! Schema::hasTable('ficha')) {
                return response()->json(['data' => []]);
            }

            $publicadas = $this->idsFichasPublicadas($idActividad);
            $meta = $this->metaFichas($publicadas);

            $data = collect($meta)
                ->map(function (array $ficha) {
                    return array_merge($ficha, [
                        'asignada' => true,
                        'activa' => true,
                        'estadoPublicacion' => 'Publicada',
                    ]);
                })
                ->sortBy(fn (array $ficha) => (string) ($ficha['codigo'] ?? $ficha['id']))
                ->values()
                ->all();

            return response()->json(['data' => $data]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function fichasCompatibles(int $idActividad): JsonResponse
    {
        $gate = $this->asegurarActividadPropia($idActividad);
        if ($gate instanceof JsonResponse) {
            return $gate;
        }

        try {
            $actividad = Actividad::query()->find($idActividad);
            $idRap = (int) ($actividad?->idMateria ?? 0);
            if ($idRap <= 0) {
                return response()->json([
                    'actividad' => $this->contextoActividad($actividad),
                    'data' => [],
                    'fichas' => [],
                    'idRap' => $idRap,
                ]);
            }

            $compatibles = $this->rapAccess->fichasHorarioDeRap($idRap);
            $idsFicha = array_map(static fn (array $f) => (int) $f['id'], $compatibles);
            $meta = $this->metaFichas($idsFicha);
            $publicadas = $this->idsFichasPublicadas($idActividad, $idsFicha);
            $instructor = $this->rapAccess->fichasInstructor();

            $data = [];
            foreach ($compatibles as $ficha) {
                $id = (int) $ficha['id'];
                $info = $meta[$id] ?? [
                    'id' => $id,
                    'codigo' => $ficha['codigo'] ?? null,
                    'programa' => null,
                    'jornada' => null,
                    'estado' => null,
                ];
                $publicada = in_array($id, $publicadas, true);
                $data[] = array_merge($info, [
                    'idHorarioMateria' => $ficha['idHorarioMateria'] ?? null,
                    'asignada' => $publicada,
                    'activa' => $publicada,
                    'estadoPublicacion' => $publicada ? 'Publicada' : 'Disponible',
                ]);
            }

            return response()->json([
                'actividad' => $this->contextoActividad($actividad),
                'data' => $data,
                'fichas' => $data,
                'idRap' => $idRap,
                'meta' => [
                    'totalInstructor' => count($instructor),
                    'totalCompatibles' => count($data),
                    'totalPublicadas' => count(array_filter($data, static fn (array $f) => $f['asignada'])),
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    private function asegurarInstructor(): ?JsonResponse
    {
        if (! $this->rapAccess->usuarioPuedeAdministrar()) {
            return response()->json(['error' => 'No autorizado para la gestión general de actividades.'], 403);
        }

        return null;
    }

    private function asegurarActividadPropia(int $idActividad): ?JsonResponse
    {
        $gate = $this->asegurarInstructor();
        if ($gate instanceof JsonResponse) {
            return $gate;
        }

        $user = KeyUtil::user();
        $idPersona = (int) ($user?->idpersona ?? 0);
        if ($idPersona <= 0) {
            return response()->json(['error' => 'Usuario autenticado sin persona asociada'], 401);
        }

        $actividad = Actividad::query()->find($idActividad);
        if (! $actividad) {
            return response()->json(['error' => 'Actividad no encontrada.'], 404);
        }
        if ((int) $actividad->idPersona !== $idPersona) {
            return response()->json(['error' => 'No puedes consultar asignaciones de una actividad ajena.'], 403);
        }

        $idRap = (int) ($actividad->idMateria ?? 0);
        if ($idRap > 0 && ! $this->rapAccess->usuarioPuedeAccederRap($idRap)) {
            return response()->json(['error' => 'No autorizado para el RAP de esta actividad.'], 403);
        }

        return null;
    }

    /**
     * @param  list<int>  $idsActividad
     * @return array<int|string, int>
     */
    private function conteoFichasAsignadas(array $idsActividad): array
    {
        if ($idsActividad === []) {
            return [];
        }

        $tablaMa = $this->tablaMatriculaAcademica();
        if (! $tablaMa || ! Schema::hasTable('calificacionActividad')) {
            return [];
        }

        $colFicha = $this->columnaFicha($tablaMa);
        $publicadas = DB::table('calificacionActividad as ca')
            ->join($tablaMa.' as ma', 'ca.idAMartriculaAcademica', '=', 'ma.id')
            ->whereIn('ca.idActividad', $idsActividad)
            ->whereNotNull('ma.'.$colFicha)
            ->select('ca.idActividad', 'ma.'.$colFicha.' as idFicha')
            ->distinct()
            ->get();

        $porActividad = [];
        foreach ($publicadas as $row) {
            $porActividad[(int) $row->idActividad][(int) $row->idFicha] = true;
        }

        $conteo = [];
        foreach ($porActividad as $idAct => $fichas) {
            $conteo[$idAct] = count($fichas);
        }

        return $conteo;
    }

    private function tablaMatriculaAcademica(): ?string
    {
        if (Schema::hasTable('matriculaAcademica')) {
            return 'matriculaAcademica';
        }
        if (Schema::hasTable('matriculaacademica')) {
            return 'matriculaacademica';
        }

        return null;
    }

    private function columnaFicha(string $tablaMa): string
    {
        if (Schema::hasColumn($tablaMa, 'idFicha')) {
            return 'idFicha';
        }
        if (Schema::hasColumn($tablaMa, 'idAsignacionPeriodoProgramaJornada')) {
            return 'idAsignacionPeriodoProgramaJornada';
        }

        return 'idFicha';
    }

    /**
     * @param  list<int>  $idsFicha
     * @return list<int>
     */
    private function idsFichasPublicadas(int $idActividad, array $idsFicha = []): array
    {
        if (! Schema::hasTable('calificacionActividad')) {
            return [];
        }
        $tablaMa = $this->tablaMatriculaAcademica();
        if (! $tablaMa) {
            return [];
        }
        $colFicha = $this->columnaFicha($tablaMa);
        $q = DB::table('calificacionActividad as ca')
            ->join($tablaMa.' as ma', 'ca.idAMartriculaAcademica', '=', 'ma.id')
            ->where('ca.idActividad', $idActividad)
            ->whereNotNull('ma.'.$colFicha);
        if ($idsFicha !== []) {
            $q->whereIn('ma.'.$colFicha, $idsFicha);
        }

        return $q->distinct()
            ->pluck('ma.'.$colFicha)
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  list<int>  $idsFicha
     * @return array<int, array{id:int,codigo:?string,programa:?string,jornada:?string,estado:?string}>
     */
    private function metaFichas(array $idsFicha): array
    {
        if ($idsFicha === [] || ! Schema::hasTable('ficha')) {
            return [];
        }

        $qb = DB::table('ficha as f')->whereIn('f.id', $idsFicha)->select(['f.id', 'f.codigo']);
        if (Schema::hasTable('jornadas')) {
            $qb->leftJoin('jornadas as j', 'f.idJornada', '=', 'j.id')
                ->addSelect('j.nombreJornada as jornada');
        }
        if (Schema::hasTable('aperturarprograma')) {
            $qb->leftJoin('aperturarprograma as ap', 'f.idAsignacion', '=', 'ap.id')
                ->addSelect('ap.estado as estado');
            if (Schema::hasTable('programa')) {
                $qb->leftJoin('programa as p', 'ap.idPrograma', '=', 'p.id')
                    ->addSelect('p.nombrePrograma as programa');
            }
        }

        $out = [];
        foreach ($qb->get() as $row) {
            $id = (int) $row->id;
            $out[$id] = [
                'id' => $id,
                'codigo' => $row->codigo !== null ? (string) $row->codigo : null,
                'programa' => isset($row->programa) && $row->programa !== null ? (string) $row->programa : null,
                'jornada' => isset($row->jornada) && $row->jornada !== null ? (string) $row->jornada : null,
                'estado' => isset($row->estado) && $row->estado !== null ? (string) $row->estado : null,
            ];
        }

        return $out;
    }

    /**
     * @return array{id:int,tituloActividad:?string,tipoActividad:?string,competencia:?array<string,mixed>,rap:?array<string,mixed>}
     */
    private function contextoActividad(?Actividad $actividad): array
    {
        if (! $actividad) {
            return [
                'id' => 0,
                'tituloActividad' => null,
                'tipoActividad' => null,
                'competencia' => null,
                'rap' => null,
            ];
        }

        $idRap = (int) ($actividad->idMateria ?? 0);
        $rap = $idRap > 0 && Schema::hasTable('materia')
            ? DB::table('materia')->where('id', $idRap)->first(['id', 'nombreMateria', 'codigo', 'idMateriaPadre'])
            : null;
        $idCompetencia = $rap && $rap->idMateriaPadre ? (int) $rap->idMateriaPadre : 0;
        $comp = $idCompetencia > 0
            ? DB::table('materia')->where('id', $idCompetencia)->first(['id', 'nombreMateria', 'codigo'])
            : null;

        return [
            'id' => (int) $actividad->id,
            'tituloActividad' => $actividad->tituloActividad,
            'tipoActividad' => $actividad->tipoActividad,
            'competencia' => $comp ? [
                'id' => (int) $comp->id,
                'nombreMateria' => $comp->nombreMateria,
                'codigo' => $comp->codigo,
            ] : null,
            'rap' => $rap ? [
                'id' => (int) $rap->id,
                'nombreMateria' => $rap->nombreMateria,
                'codigo' => $rap->codigo,
            ] : null,
        ];
    }

    private function paginadoVacio(int $page, int $perPage): JsonResponse
    {
        return response()->json([
            'data' => [],
            'meta' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => 0,
                'last_page' => 1,
                'from' => 0,
                'to' => 0,
            ],
        ]);
    }
}
