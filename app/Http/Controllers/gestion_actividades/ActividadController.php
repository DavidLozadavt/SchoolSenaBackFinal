<?php

namespace App\Http\Controllers\gestion_actividades;

use App\Http\Controllers\Concerns\ValidatesMaterialDocumentUpload;
use App\Http\Controllers\Controller;
use App\Http\Controllers\ambiente_virtual\CalificacionActividadController;
use App\Models\Actividad;
use App\Models\TipoActividad;
use App\Models\ClasificacionActividad;
use App\Models\PlaneacionActividad;
use App\Models\Materia;
use App\Models\Status;
use App\Models\AsignacionMaterialApoyoActividad;
use App\Models\MaterialApoyoActividad;
use App\Models\Ficha;
use App\Models\MaterialApoyoRap;
use App\Models\Pregunta;
use App\Models\TipoPregunta;
use App\Models\Respuesta;
use App\Support\DiagnosticoActividadesRapHistoricas;
use App\Util\CuestionarioAsignacionUtil;
use App\Util\KeyUtil;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ActividadController extends Controller
{
    use ValidatesMaterialDocumentUpload;

    /** Rechazo mover RAP cuando el destino no es de la misma ficha o la actividad no pertenece a esa ficha. */
    private const ERROR_MOVER_RAP_FICHA_DISTINTA = 'No puedes mover esta actividad a un RAP de otra ficha.';

    public function materialApoyoAprendiz(Request $request): JsonResponse
    {
        try {
            $user = KeyUtil::user();
            $idPersona = $user?->idpersona;
            if (!$idPersona) {
                return response()->json(['error' => 'Usuario autenticado sin persona asociada'], 401);
            }

            $tablaMa = Schema::hasTable('matriculaAcademica') ? 'matriculaAcademica' : 'matriculaacademica';
            if (!Schema::hasTable($tablaMa)) {
                return response()->json([]);
            }
            $colFicha = Schema::hasColumn($tablaMa, 'idFicha')
                ? 'idFicha'
                : (Schema::hasColumn($tablaMa, 'idAsignacionPeriodoProgramaJornada') ? 'idAsignacionPeriodoProgramaJornada' : null);
            if (!$colFicha) {
                return response()->json([]);
            }

            $fichaIds = DB::table($tablaMa . ' as ma')
                ->join('matricula as m', 'ma.idMatricula', '=', 'm.id')
                ->where('m.idPersona', $idPersona)
                ->whereNotNull('ma.' . $colFicha)
                ->distinct()
                ->pluck('ma.' . $colFicha)
                ->map(fn ($id) => (int) $id)
                ->filter(fn ($id) => $id > 0)
                ->values();

            if ($fichaIds->isEmpty()) {
                return response()->json([]);
            }

            $tablaMaterial = (new MaterialApoyoRap())->getTable();
            if (! Schema::hasTable($tablaMaterial)) {
                return response()->json([]);
            }

            /** @var array<int, int> */
            $fichaIdsPrograma = $this->expandFichaIdsMismoPrograma($fichaIds->all());

            $query = DB::table($tablaMaterial . ' as mar')
                ->leftJoin('materia as mat', 'mar.idMateria', '=', 'mat.id')
                ->leftJoin('materia as rap', 'mar.idRap', '=', 'rap.id')
                ->leftJoin('materia as comp', 'mat.idMateriaPadre', '=', 'comp.id')
                ->leftJoin('ficha as f', 'mar.idFicha', '=', 'f.id')
                ->leftJoin('persona as creador', 'mar.idPersona', '=', 'creador.id')
                ->whereIn('mar.idFicha', $fichaIdsPrograma);

            $idFicha = (int) $request->query('idFicha', 0);
            $idMateria = (int) $request->query('idMateria', 0);
            $idRap = (int) $request->query('idRap', 0);
            $idCompetencia = (int) $request->query('idCompetencia', 0);

            if ($idFicha > 0) {
                $query->where('mar.idFicha', $idFicha);
            }
            if ($idMateria > 0) {
                $query->where('mar.idMateria', $idMateria);
            }
            if ($idRap > 0) {
                $query->where('mar.idRap', $idRap);
            }
            if ($idCompetencia > 0) {
                // Competencia = padre del RAP (mismo criterio que Mis Actividades).
                $query->where(function ($q) use ($idCompetencia) {
                    $q->where('rap.idMateriaPadre', $idCompetencia)
                        ->orWhere(function ($q2) use ($idCompetencia) {
                            $q2->whereNull('rap.idMateriaPadre')
                                ->where(function ($q3) use ($idCompetencia) {
                                    $q3->where('mat.idMateriaPadre', $idCompetencia)
                                        ->orWhere('mat.id', $idCompetencia);
                                });
                        });
                });
            }

            $selectCols = [
                'mar.id',
                'mar.titulo',
                'mar.descripcion',
                'mar.urlDocumento',
                'mar.urlAdicional',
                'mar.idMateria',
                'mar.idFicha',
                'mar.idRap',
                'mar.idPersona',
                'mar.created_at',
                'mat.nombreMateria as materiaNombre',
                'mat.idMateriaPadre as idCompetenciaMat',
                'rap.nombreMateria as rapNombre',
                'rap.idMateriaPadre as idCompetenciaRap',
                'f.codigo as fichaCodigo',
                'creador.nombre1 as creadorNombre1',
                'creador.nombre2 as creadorNombre2',
                'creador.apellido1 as creadorApellido1',
                'creador.apellido2 as creadorApellido2',
                'creador.email as creadorEmail',
                'creador.rutaFoto as creadorRutaFoto',
                DB::raw('COALESCE(comp.nombreMateria, mat.nombreMateria) as competenciaNombre'),
            ];
            if (Schema::hasColumn($tablaMaterial, 'urlVideo')) {
                $selectCols[] = 'mar.urlVideo';
            }
            if (Schema::hasColumn($tablaMaterial, 'tipoMaterial')) {
                $selectCols[] = 'mar.tipoMaterial';
            }

            $rows = $query
                ->select($selectCols)
                ->orderByDesc('mar.id')
                ->get();

            $tieneColVideo = Schema::hasColumn($tablaMaterial, 'urlVideo');
            $tieneColTipo = Schema::hasColumn($tablaMaterial, 'tipoMaterial');

            $data = $rows->map(function ($row) use ($tieneColVideo, $tieneColTipo) {
                $idRapResolved = (int) $row->idRap;
                $urlVideo = $tieneColVideo ? ($row->urlVideo ?? null) : null;
                $tipoMaterial = $tieneColTipo ? ($row->tipoMaterial ?? null) : null;
                $idCompetenciaResolved = (int) ($row->idCompetenciaRap ?? 0);
                if ($idCompetenciaResolved <= 0) {
                    $idCompetenciaResolved = (int) ($row->idCompetenciaMat ?? 0);
                }
                if ($idCompetenciaResolved <= 0 && ! empty($row->idMateria)) {
                    $idCompetenciaResolved = (int) $row->idMateria;
                }

                $nombreCreador = trim(implode(' ', array_filter([
                    $row->creadorNombre1 ?? '',
                    $row->creadorNombre2 ?? '',
                    $row->creadorApellido1 ?? '',
                    $row->creadorApellido2 ?? '',
                ])));

                $creador = null;
                if (! empty($row->idPersona)) {
                    $creador = [
                        'idPersona' => (int) $row->idPersona,
                        'nombreCompleto' => $nombreCreador !== '' ? $nombreCreador : null,
                        'email' => $row->creadorEmail ?? null,
                        'rutaFoto' => $row->creadorRutaFoto ?? null,
                        'rutaFotoUrl' => $this->resolvePersonaPublicFotoUrl($row->creadorRutaFoto ?? null),
                    ];
                }

                return [
                    'id' => (int) $row->id,
                    'titulo' => $row->titulo,
                    'descripcion' => $row->descripcion,
                    'tipoMaterial' => $tipoMaterial,
                    'urlDocumento' => $row->urlDocumento,
                    'urlDocumentoUrl' => $this->publicUrl($row->urlDocumento),
                    'urlAdicional' => $row->urlAdicional,
                    'urlVideo' => $urlVideo,
                    'urlVideoUrl' => $this->publicUrl($urlVideo),
                    'idMateria' => (int) $row->idMateria,
                    'materiaNombre' => $row->materiaNombre,
                    'competenciaNombre' => $row->competenciaNombre,
                    'idCompetencia' => $idCompetenciaResolved > 0 ? $idCompetenciaResolved : null,
                    'idFicha' => (int) $row->idFicha,
                    'fichaCodigo' => $row->fichaCodigo,
                    'idRap' => $idRapResolved > 0 ? $idRapResolved : null,
                    'rapNombre' => $row->rapNombre ?: $row->materiaNombre,
                    'legacySinRap' => false,
                    'created_at' => $row->created_at,
                    'idPersona' => $row->idPersona ? (int) $row->idPersona : null,
                    'creador' => $creador,
                ];
            })->values();

            return response()->json($data);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Amplía las fichas del aprendiz a todas las fichas que comparten el mismo programa
     * (ficha.idAsignacion → aperturarprograma.idPrograma), sin modificar la base de datos.
     *
     * @param  array<int, int>  $fichaIds
     * @return array<int, int>
     */
    private function expandFichaIdsMismoPrograma(array $fichaIds): array
    {
        $fichaIds = array_values(array_unique(array_map('intval', $fichaIds)));
        if ($fichaIds === []) {
            return [];
        }
        if (! Schema::hasTable('ficha') || ! Schema::hasTable('aperturarprograma')) {
            return $fichaIds;
        }

        $programIds = DB::table('ficha as f')
            ->join('aperturarprograma as ap', 'f.idAsignacion', '=', 'ap.id')
            ->whereIn('f.id', $fichaIds)
            ->whereNotNull('ap.idPrograma')
            ->distinct()
            ->pluck('ap.idPrograma')
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->values();

        if ($programIds->isEmpty()) {
            return $fichaIds;
        }

        $expanded = DB::table('ficha as f2')
            ->join('aperturarprograma as ap2', 'f2.idAsignacion', '=', 'ap2.id')
            ->whereIn('ap2.idPrograma', $programIds->all())
            ->distinct()
            ->pluck('f2.id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->values()
            ->all();

        return array_values(array_unique(array_merge($fichaIds, $expanded)));
    }

    public function actividadesAprendiz(Request $request): JsonResponse
    {
        try {
            if (!Schema::hasTable('calificacionActividad')) {
                return response()->json(['data' => [], 'total' => 0, 'meta' => ['current_page' => 1, 'per_page' => 15, 'total' => 0]]);
            }

            $user = KeyUtil::user();
            $idPersona = $user?->idpersona;

            if (!$idPersona) {
                return response()->json(['error' => 'Usuario autenticado sin persona asociada'], 401);
            }

            $perPage = (int) $request->query('per_page', 15);
            $perPage = min(max($perPage, 5), 50);
            $page = max(1, (int) $request->query('page', 1));

            $tableMa = Schema::hasTable('matriculaAcademica') ? 'matriculaAcademica' : 'matriculaacademica';
            $colFicha = Schema::hasColumn($tableMa, 'idFicha')
                ? 'idFicha'
                : (Schema::hasColumn($tableMa, 'idAsignacionPeriodoProgramaJornada') ? 'idAsignacionPeriodoProgramaJornada' : null);

            $query = DB::table('calificacionActividad as ca')
                ->join($tableMa . ' as ma', 'ca.idAMartriculaAcademica', '=', 'ma.id')
                ->join('matricula as m', 'ma.idMatricula', '=', 'm.id')
                ->join('actividades as a', 'ca.idActividad', '=', 'a.id')
                ->leftJoin('materia as mat', 'a.idMateria', '=', 'mat.id')
                ->leftJoin('area_conocimiento as ac', 'mat.idAreaConocimiento', '=', 'ac.id')
                ->leftJoin('persona as p', 'a.idPersona', '=', 'p.id')
                ->leftJoin('persona as p_inst', 'ca.idPersona', '=', 'p_inst.id')
                ->where('m.idPersona', $idPersona);

            $total = (clone $query)->count();

            $registros = (clone $query)
                ->leftJoin('estado as e', 'a.idEstado', '=', 'e.id')
                ->select([
                    'ca.id as idCalificacionActividad',
                    'ca.idActividad',
                    'ca.archivo as archivoEntrega',
                    'ca.fechaInicial',
                    'ca.fechaFinal',
                    'ca.fechaCalificacion',
                    'ca.calificacionNumerica',
                    'ca.calificacionEstandart',
                    'ca.ComentarioDocente',
                    'ca.ComentarioEstudiante',
                    'ca.idGrupo',
                    'ca.idEstado as idEstadoCalificacion',
                    'a.idEstado as idEstadoActividad',
                    'e.estado as estadoActividad',
                    'a.tituloActividad',
                    'a.descripcionActividad',
                    'a.pathDocumentoActividad',
                    'a.tipoActividad',
                    'a.estrategia',
                    'a.entregables',
                    'mat.id as idMateria',
                    'mat.codigo as codigoMateria',
                    'mat.nombreMateria',
                    'ac.id as idArea',
                    'ac.nombreAreaConocimiento',
                    'p.nombre1 as autorNombre1',
                    'p.nombre2 as autorNombre2',
                    'p.apellido1 as autorApellido1',
                    'p.apellido2 as autorApellido2',
                    'p.rutaFoto as autorRutaFoto',
                    'p_inst.rutaFoto as instructorPersonaRutaFoto',
                    DB::raw($colFicha ? ('ma.' . $colFicha . ' as idFichaContext') : 'NULL as idFichaContext'),
                ])
                ->orderByDesc('ca.id')
                ->offset(($page - 1) * $perPage)
                ->limit($perPage)
                ->get();

            $idsActividad = $registros->pluck('idActividad')->unique()->filter()->values();
            $materialesPorActividad = [];
            $preguntasPorActividad = [];

            if ($idsActividad->isNotEmpty()) {
                $idsCuestionarios = $registros->where('tipoActividad', 'cuestionario')->pluck('idActividad')->unique()->filter()->values();
                if ($idsCuestionarios->isNotEmpty()) {
                    $preguntas = \App\Models\Pregunta::with(['tipoPregunta', 'respuestas'])
                        ->whereIn('idActividad', $idsCuestionarios)
                        ->orderBy('id')
                        ->get()
                        ->groupBy('idActividad');
                    foreach ($preguntas as $idAct => $items) {
                        $preguntasPorActividad[$idAct] = $items->map(fn ($p) => [
                            'id' => $p->id,
                            'descripcion' => $p->descripcion,
                            'tipoPregunta' => $p->tipoPregunta ? ['tipoPregunta' => $p->tipoPregunta->tipoPregunta ?? null] : null,
                            'urlDocumento' => $p->urlDocumento,
                            // No exponer chkCorrecta al aprendiz antes de la revisión del intento.
                            'respuestas' => $p->respuestas ? $p->respuestas->map(fn ($r) => [
                                'id' => $r->id,
                                'descripcionRespuesta' => $r->descripcionRespuesta,
                            ])->values() : [],
                        ])->values()->all();
                    }
                }
            }

            $cuestionariosConRespuestas = collect();
            $tblRc = Schema::hasTable('respuestaCuestionarios') ? 'respuestaCuestionarios' : (Schema::hasTable('respuesta_cuestionarios') ? 'respuesta_cuestionarios' : null);
            if ($tblRc) {
                $idsCalifCuestionarios = $registros->where('tipoActividad', 'cuestionario')->pluck('idCalificacionActividad')->unique()->filter()->values();
                if ($idsCalifCuestionarios->isNotEmpty()) {
                    $cuestionariosConRespuestas = DB::table($tblRc)
                        ->whereIn('idCalificacion', $idsCalifCuestionarios->all())
                        ->where(function ($q) {
                            $q->whereNotNull('idRespuesta')
                                ->orWhereRaw("TRIM(COALESCE(respuesta, '')) <> ''");
                        })
                        ->select('idCalificacion')
                        ->distinct()
                        ->pluck('idCalificacion');
                }
            }

            // Material de apoyo propio de cada actividad (materialApoyoActividad + asignacionMaterialApoyoActividad).
            // No mezclar con materialApoyoRap (Biblioteca de conocimiento).
            if ($idsActividad->isNotEmpty()
                && Schema::hasTable('asignacionMaterialApoyoActividad')
                && Schema::hasTable((new MaterialApoyoActividad())->getTable())) {
                $tablaMa = (new MaterialApoyoActividad())->getTable();
                $filasMaterial = DB::table('asignacionMaterialApoyoActividad as ama')
                    ->join($tablaMa.' as ma', 'ama.idMaterialApoyo', '=', 'ma.id')
                    ->whereIn('ama.idActividad', $idsActividad->all())
                    ->whereNotNull('ama.idMaterialApoyo')
                    ->select([
                        'ama.idActividad',
                        'ma.id',
                        'ma.titulo',
                        'ma.descripcion',
                        'ma.urlDocumento',
                        'ma.urlAdicional',
                    ])
                    ->orderBy('ma.id')
                    ->get();

                foreach ($filasMaterial as $mat) {
                    $idAct = (int) $mat->idActividad;
                    $materialesPorActividad[$idAct][] = [
                        'id' => (int) $mat->id,
                        'titulo' => $mat->titulo,
                        'descripcion' => $mat->descripcion,
                        'urlDocumento' => $mat->urlDocumento,
                        'urlDocumentoUrl' => $this->publicUrl($mat->urlDocumento),
                        'urlAdicional' => $mat->urlAdicional,
                    ];
                }
            }

            $data = $registros->map(function ($row) use ($materialesPorActividad, $preguntasPorActividad, $cuestionariosConRespuestas) {
                $tieneRespuestasCuestionario = ($row->tipoActividad ?? '') === 'cuestionario'
                    && $cuestionariosConRespuestas->contains($row->idCalificacionActividad);
                $estadoVisual = $this->resolverEstadoActividadAprendiz($row, $tieneRespuestasCuestionario);
                $autor = trim(implode(' ', array_filter([
                    $row->autorNombre1,
                    $row->autorNombre2,
                    $row->autorApellido1,
                    $row->autorApellido2,
                ])));

                $autRuta = trim((string) ($row->autorRutaFoto ?? ''));
                $instRuta = trim((string) ($row->instructorPersonaRutaFoto ?? ''));
                $rutaFotoPersonaCruda = $autRuta !== '' ? trim((string) $row->autorRutaFoto) : ($instRuta !== '' ? trim((string) $row->instructorPersonaRutaFoto) : null);

                // Estado calculado solo por: fecha inicio, fecha límite y hora actual (`fechaFinal` = límite individual en calificacionActividad).
                $tz = config('app.timezone');
                $now = now($tz);
                $fechaVencida = false;
                $fechaInactiva = false;
                if ($row->fechaFinal) {
                    try {
                        $fechaFinal = \Carbon\Carbon::parse((string) $row->fechaFinal, $tz);
                        $fechaVencida = $now->greaterThan($fechaFinal);
                    } catch (\Exception $e) {
                        $fechaVencida = false;
                    }
                }
                if ($row->fechaInicial) {
                    try {
                        $fechaInicial = \Carbon\Carbon::parse((string) $row->fechaInicial, $tz);
                        $fechaInactiva = $now->lessThan($fechaInicial);
                    } catch (\Exception $e) {
                        $fechaInactiva = false;
                    }
                }

                return [
                    'idCalificacionActividad' => $row->idCalificacionActividad,
                    'idActividad' => $row->idActividad,
                    'tituloActividad' => $row->tituloActividad,
                    'descripcionActividad' => $row->descripcionActividad,
                    'tipoActividad' => $row->tipoActividad,
                    'estrategia' => $row->estrategia,
                    'entregables' => $row->entregables,
                    'fechaInicial' => $row->fechaInicial,
                    'fechaFinal' => $row->fechaFinal,
                    'fechaCalificacion' => $row->fechaCalificacion,
                    'calificacionNumerica' => $row->calificacionNumerica,
                    'calificacionEstandart' => $row->calificacionEstandart,
                    'comentarioDocente' => $row->ComentarioDocente,
                    'comentarioEstudiante' => $row->ComentarioEstudiante,
                    'archivoEntrega' => $row->archivoEntrega,
                    'archivoEntregaUrl' => $this->publicUrl($row->archivoEntrega),
                    'pathDocumentoActividad' => $row->pathDocumentoActividad,
                    'documentoActividadUrl' => $this->publicUrl($row->pathDocumentoActividad),
                    'materia' => [
                        'id' => $row->idMateria,
                        'codigo' => $row->codigoMateria,
                        'nombreMateria' => $row->nombreMateria,
                    ],
                    'area' => [
                        'id' => $row->idArea,
                        'nombre' => $row->nombreAreaConocimiento,
                    ],
                    'autor' => [
                        'nombreCompleto' => $autor ?: 'Sin asignar',
                        /** Misma regla que App\Models\Person::getRutaFotoUrl (url()), no solo Storage::url sobre /storage/… */
                        'rutaFotoUrl' => $this->resolvePersonaPublicFotoUrl($rutaFotoPersonaCruda),
                    ],
                    'materialesApoyo' => $materialesPorActividad[(int) $row->idActividad] ?? [],
                    'estadoVisual' => $estadoVisual,
                    'fechaVencida' => $fechaVencida,
                    'fechaInactiva' => $fechaInactiva,
                    'puedeResponder' => (strtoupper(trim($row->estadoActividad ?? 'ACTIVO')) === 'ACTIVO')
                        && !$fechaVencida
                        && !$fechaInactiva
                        && (
                            in_array($estadoVisual, ['PENDIENTE', 'SIN_ENTREGAR'], true)
                            || $estadoVisual === 'CORRECCION_SOLICITADA'
                            || (
                                strtolower(trim($row->tipoActividad ?? '')) !== 'cuestionario'
                                && $estadoVisual === 'POR_EVALUAR'
                            )
                        ),
                    'estadoActividad' => $row->estadoActividad ?? 'ACTIVO',
                    'activa' => (strtoupper(trim($row->estadoActividad ?? 'ACTIVO')) === 'ACTIVO') && !$fechaVencida && !$fechaInactiva,
                    'esGrupal' => !empty($row->idGrupo),
                    'idGrupo' => $row->idGrupo,
                    'tieneRespuestasCuestionario' => $tieneRespuestasCuestionario,
                    'preguntas' => ($row->tipoActividad ?? '') === 'cuestionario'
                        ? CuestionarioAsignacionUtil::filtrarPreguntasPayload(
                            $preguntasPorActividad[$row->idActividad] ?? [],
                            (int) $row->idCalificacionActividad,
                            (int) $row->idActividad
                        )
                        : null,
                ];
            })->values();

            return response()->json([
                'data' => $data,
                'total' => $total,
                'meta' => [
                    'current_page' => $page,
                    'per_page' => $perPage,
                    'total' => $total,
                    'last_page' => (int) ceil($total / $perPage),
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $idCompany = KeyUtil::idCompany();
            $query = Actividad::with(['materia', 'estado', 'clasificacion', 'persona'])
                ->where('idCompany', $idCompany);

            if ($request->has('search') && $request->search) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('tituloActividad', 'like', "%{$search}%")
                        ->orWhere('descripcionActividad', 'like', "%{$search}%");
                });
            }

            /**
             * Filtro por contexto de clase (ambiente virtual):
             * - id_materia_clase: identificador del RAP de la clase abierta (materia.id).
             *   Filtra estrictamente por ese RAP; no incluye otros RAP de la misma competencia.
             * - id_programa: restringe a materias del programa vía gradoMateria / gradoPrograma.
             * - idRap / idMateria: filtro estricto adicional (misma semántica: actividades.idMateria).
             */
            $idMateriaClase = $request->query('id_materia_clase');
            $idPrograma = $request->query('id_programa');
            $idMateriaExacta = (int) $request->query('idMateria', 0);
            $idRapExacto = (int) $request->query('idRap', 0);
            $materiaIdsFilter = null;

            if ($idMateriaClase !== null && $idMateriaClase !== '') {
                $mc = (int) $idMateriaClase;
                if ($mc > 0) {
                    $materiaIdsFilter = [$mc];
                } else {
                    $materiaIdsFilter = [];
                }
            }

            if ($idPrograma !== null && $idPrograma !== '') {
                $ip = (int) $idPrograma;
                if ($ip > 0 && Schema::hasTable('gradoMateria') && Schema::hasTable('gradoPrograma')) {
                    $idsProg = DB::table('gradoMateria as gm')
                        ->join('gradoPrograma as gp', 'gm.idGradoPrograma', '=', 'gp.id')
                        ->where('gp.idPrograma', $ip)
                        ->pluck('gm.idMateria')
                        ->map(fn ($id) => (int) $id)
                        ->unique()
                        ->values()
                        ->all();
                    if ($materiaIdsFilter === null) {
                        $materiaIdsFilter = $idsProg;
                    } else {
                        $materiaIdsFilter = array_values(array_intersect($materiaIdsFilter, $idsProg));
                    }
                }
            }

            /**
             * Coherencia programa + materia de clase: evita que un cliente envíe id_programa de un programa
             * e id_materia_clase de otra competencia/RAP y obtenga un listado inconsistente.
             */
            if ($idMateriaClase !== null && $idMateriaClase !== '' && $idPrograma !== null && $idPrograma !== '') {
                $mc = (int) $idMateriaClase;
                $ip = (int) $idPrograma;
                if ($mc > 0 && $ip > 0 && Schema::hasTable('gradoMateria') && Schema::hasTable('gradoPrograma')) {
                    $pertenece = DB::table('gradoMateria as gm')
                        ->join('gradoPrograma as gp', 'gm.idGradoPrograma', '=', 'gp.id')
                        ->where('gp.idPrograma', $ip)
                        ->where('gm.idMateria', $mc)
                        ->exists();
                    if (! $pertenece) {
                        return response()->json([
                            'error' => 'La materia de la clase no pertenece al programa indicado.',
                            'code' => 'PROGRAMA_MATERIA_INCONSISTENTE',
                        ], 422);
                    }
                }
            }

            /**
             * Sin id_materia_clase ni id_programa no se debe exponer el banco completo de la empresa
             * (riesgo de mezclar programas/RAPs). El cliente debe acotar contexto.
             */
            if ($materiaIdsFilter === null && $idMateriaExacta <= 0 && $idRapExacto <= 0) {
                return response()->json([]);
            }

            if (is_array($materiaIdsFilter)) {
                if (count($materiaIdsFilter) > 0) {
                    $query->whereIn('idMateria', $materiaIdsFilter);
                } else {
                    $query->whereRaw('1 = 0');
                }
            }

            // Filtro estricto por RAP (actividades.idMateria = id del RAP).
            if ($idRapExacto > 0) {
                $query->where('idMateria', $idRapExacto);
            } elseif ($idMateriaExacta > 0) {
                $query->where('idMateria', $idMateriaExacta);
            }

            $actividades = $query->orderBy('id', 'asc')->get();

            // Compatibilidad histórica: si el listado queda vacío, diagnosticar RAP hermanos.
            // No se añaden al listado; solo se registran logs de diagnóstico.
            if ($actividades->isEmpty()) {
                $idRapDiag = $idRapExacto > 0
                    ? $idRapExacto
                    : ($idMateriaExacta > 0
                        ? $idMateriaExacta
                        : (is_array($materiaIdsFilter) && count($materiaIdsFilter) === 1
                            ? (int) $materiaIdsFilter[0]
                            : 0));
                if ($idRapDiag > 0) {
                    DiagnosticoActividadesRapHistoricas::diagnosticarListadoVacio(
                        $idRapDiag,
                        (int) $idCompany,
                        null,
                        'GET actividades'
                    );
                }
            }

            return response()->json($actividades);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function porEvaluar(): JsonResponse
    {
        try {
            if (!Schema::hasTable('calificacionActividad')) {
                return response()->json(['data' => []]);
            }

            $user = KeyUtil::user();
            $idPersona = $user?->idpersona;

            if (!$idPersona) {
                return response()->json(['error' => 'Usuario autenticado sin persona asociada'], 401);
            }

            $tableMa = Schema::hasTable('matriculaAcademica') ? 'matriculaAcademica' : 'matriculaacademica';

            $calificaciones = DB::table('calificacionActividad as ca')
                ->join('actividades as a', 'ca.idActividad', '=', 'a.id')
                ->join($tableMa . ' as ma', 'ca.idAMartriculaAcademica', '=', 'ma.id')
                ->join('matricula as m', 'ma.idMatricula', '=', 'm.id')
                ->join('persona as p_estudiante', 'm.idPersona', '=', 'p_estudiante.id')
                ->leftJoin('materia as mat', 'a.idMateria', '=', 'mat.id')
                ->where('ca.idPersona', $idPersona)
                ->where(function ($q) {
                    $q->whereNull('ca.calificacionNumerica')
                      ->orWhere('ca.calificacionNumerica', '');
                })
                ->where(function ($q) {
                    $q->where(function ($subQ) {
                        $subQ->whereNotNull('ca.archivo')
                             ->where('ca.archivo', '!=', '');
                    })->orWhere(function ($subQ) {
                        $subQ->whereNotNull('ca.ComentarioEstudiante')
                             ->where('ca.ComentarioEstudiante', '!=', '');
                    });
                })
                ->select([
                    'ca.id',
                    'ca.idActividad',
                    'a.tituloActividad',
                    'mat.nombreMateria',
                    'p_estudiante.nombre1',
                    'p_estudiante.apellido1',
                    'ca.fechaInicial',
                    'ca.fechaFinal',
                    'ca.archivo',
                    'ca.ComentarioEstudiante',
                ])
                ->orderByDesc('ca.updated_at')
                ->get();

            $result = [];
            foreach ($calificaciones as $c) {
                $nombreEstudiante = trim(($c->nombre1 ?? '') . ' ' . ($c->apellido1 ?? ''));
                $result[] = [
                    'id' => $c->id,
                    'estado' => 'ENVIADO',
                    'tituloActividad' => $c->tituloActividad ?? 'Sin título',
                    'descripcionActividad' => 'Enviado por: ' . (!empty($nombreEstudiante) ? $nombreEstudiante : 'Estudiante'),
                    'materia' => ['nombreMateria' => $c->nombreMateria],
                    'fechaFin' => $c->fechaFinal,
                ];
            }

            return response()->json(['data' => $result]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Instructor/admin: actividades creadas o asignadas, agrupadas por actividad + ficha (una fila por grupo).
     */
    public function misActividadesInstructor(Request $request): JsonResponse
    {
        try {
            if (! Schema::hasTable('calificacionActividad')) {
                return response()->json(['data' => []]);
            }

            $user = KeyUtil::user();
            $idPersona = $user?->idpersona;
            if (! $idPersona) {
                return response()->json(['error' => 'Usuario autenticado sin persona asociada'], 401);
            }

            $tableMa = Schema::hasTable('matriculaAcademica') ? 'matriculaAcademica' : 'matriculaacademica';
            $colFicha = Schema::hasColumn($tableMa, 'idFicha')
                ? 'idFicha'
                : (Schema::hasColumn($tableMa, 'idAsignacionPeriodoProgramaJornada')
                    ? 'idAsignacionPeriodoProgramaJornada'
                    : 'idFicha');

            $selectHm = Schema::hasTable('horarioMateria')
                ? DB::raw('(
                    SELECT hm2.id FROM horarioMateria hm2
                    INNER JOIN gradoMateria gm2 ON hm2.idGradoMateria = gm2.id
                    WHERE hm2.idFicha = ma.'.$colFicha.' AND gm2.idMateria = a.idMateria
                    ORDER BY hm2.id DESC LIMIT 1
                ) as idHorarioMateria')
                : DB::raw('NULL as idHorarioMateria');

            $qb = DB::table('calificacionActividad as ca')
                ->join('actividades as a', 'ca.idActividad', '=', 'a.id')
                ->join($tableMa.' as ma', 'ca.idAMartriculaAcademica', '=', 'ma.id')
                ->join('matricula as m', 'ma.idMatricula', '=', 'm.id')
                ->join('ficha as f', 'ma.'.$colFicha, '=', 'f.id')
                ->leftJoin('materia as mat', 'a.idMateria', '=', 'mat.id')
                ->leftJoin('materia as mat_padre', 'mat.idMateriaPadre', '=', 'mat_padre.id')
                ->leftJoin('persona as p_creador', 'a.idPersona', '=', 'p_creador.id')
                ->where(function ($q) use ($idPersona) {
                    $q->where('ca.idPersona', $idPersona)
                        ->orWhere('a.idPersona', $idPersona);
                });

            $registros = (clone $qb)
                ->select([
                    'ca.id as idCalificacionActividad',
                    'ca.idActividad',
                    'ca.idGrupo',
                    'ca.archivo',
                    'ca.ComentarioEstudiante',
                    'ca.ComentarioDocente',
                    'ca.calificacionNumerica',
                    'ca.fechaInicial',
                    'ca.fechaFinal',
                    'm.id as idMatricula',
                    'ma.'.$colFicha.' as idFicha',
                    'f.codigo as codigoFicha',
                    'a.tituloActividad',
                    'a.descripcionActividad',
                    'a.tipoActividad',
                    'a.idMateria',
                    'mat.codigo as codigoMateria',
                    'mat.nombreMateria',
                    'mat.idMateriaPadre',
                    DB::raw('CASE
                        WHEN mat.idMateriaPadre IS NOT NULL AND mat.idMateriaPadre > 0 AND mat_padre.id IS NOT NULL THEN mat_padre.nombreMateria
                        ELSE mat.nombreMateria
                    END as competenciaNombre'),
                    DB::raw('CASE
                        WHEN mat.idMateriaPadre IS NOT NULL AND mat.idMateriaPadre > 0 AND mat_padre.id IS NOT NULL THEN mat.nombreMateria
                        ELSE NULL
                    END as rapNombre'),
                    DB::raw('CASE
                        WHEN mat.idMateriaPadre IS NOT NULL AND mat.idMateriaPadre > 0 AND mat_padre.id IS NOT NULL THEN mat.id
                        ELSE NULL
                    END as idRap'),
                    DB::raw('CASE
                        WHEN mat.idMateriaPadre IS NOT NULL AND mat.idMateriaPadre > 0 AND mat_padre.id IS NOT NULL THEN mat.codigo
                        ELSE mat.codigo
                    END as codigoRap'),
                    'p_creador.id as idPersonaCreador',
                    'p_creador.nombre1 as creadorNombre1',
                    'p_creador.nombre2 as creadorNombre2',
                    'p_creador.apellido1 as creadorApellido1',
                    'p_creador.apellido2 as creadorApellido2',
                    'p_creador.rutaFoto as creadorRutaFoto',
                    $selectHm,
                ])
                ->orderByDesc('ca.id')
                ->get();

            $idsCalifCuestionarios = $registros
                ->where('tipoActividad', 'cuestionario')
                ->pluck('idCalificacionActividad')
                ->unique()
                ->filter()
                ->values();
            $cuestionariosConRespuestas = collect();
            $tblRc = Schema::hasTable('respuestaCuestionarios') ? 'respuestaCuestionarios' : (Schema::hasTable('respuesta_cuestionarios') ? 'respuesta_cuestionarios' : null);
            if ($tblRc && $idsCalifCuestionarios->isNotEmpty()) {
                $cuestionariosConRespuestas = DB::table($tblRc)
                    ->whereIn('idCalificacion', $idsCalifCuestionarios->all())
                    ->where(function ($q) {
                        $q->whereNotNull('idRespuesta')
                            ->orWhereRaw("TRIM(COALESCE(respuesta, '')) <> ''");
                    })
                    ->select('idCalificacion')
                    ->distinct()
                    ->pluck('idCalificacion');
            }

            $grupos = [];
            foreach ($registros as $row) {
                $idAct = (int) $row->idActividad;
                $idFicha = (int) ($row->idFicha ?? 0);
                if ($idAct <= 0 || $idFicha <= 0) {
                    continue;
                }
                $clave = $idAct.'_'.$idFicha;
                if (! isset($grupos[$clave])) {
                    $grupos[$clave] = [
                        'meta' => $row,
                        'vistosMatricula' => [],
                        'estados' => [],
                        'tieneGrupo' => false,
                        'fechaInicial' => null,
                        'fechaLimite' => null,
                    ];
                }
                $idMat = $row->idMatricula ?? null;
                if ($idMat !== null && isset($grupos[$clave]['vistosMatricula'][$idMat])) {
                    continue;
                }
                if ($idMat !== null) {
                    $grupos[$clave]['vistosMatricula'][$idMat] = true;
                }
                if (! empty($row->idGrupo)) {
                    $grupos[$clave]['tieneGrupo'] = true;
                }
                $fi = $row->fechaInicial ? (string) $row->fechaInicial : null;
                $ff = $row->fechaFinal ? (string) $row->fechaFinal : null;
                if ($fi && ($grupos[$clave]['fechaInicial'] === null || $fi < $grupos[$clave]['fechaInicial'])) {
                    $grupos[$clave]['fechaInicial'] = $fi;
                }
                if ($ff && ($grupos[$clave]['fechaLimite'] === null || $ff > $grupos[$clave]['fechaLimite'])) {
                    $grupos[$clave]['fechaLimite'] = $ff;
                }
                $tieneRespuestasCuestionario = ($row->tipoActividad ?? '') === 'cuestionario'
                    && $cuestionariosConRespuestas->contains($row->idCalificacionActividad);
                $rowEstado = $row;
                $comentarioEst = trim((string) ($row->ComentarioEstudiante ?? ''));
                if ($comentarioEst !== '' && trim((string) ($row->archivo ?? '')) === ''
                    && strtolower((string) ($row->tipoActividad ?? '')) !== 'cuestionario') {
                    $rowEstado = (object) array_merge((array) $row, ['archivo' => $comentarioEst]);
                }
                $estado = $this->resolverEstadoActividadAprendiz($rowEstado, $tieneRespuestasCuestionario);
                $grupos[$clave]['estados'][] = $estado;
            }

            $tz = config('app.timezone');
            $now = now($tz);
            $data = [];
            foreach ($grupos as $g) {
                $row = $g['meta'];
                $estados = $g['estados'];
                $totalAsignados = count($estados);
                $conteo = array_count_values($estados);
                $totalCalificados = (int) ($conteo['CALIFICADO'] ?? 0);
                $totalPorEvaluar = (int) ($conteo['POR_EVALUAR'] ?? 0);
                $totalPendientes = (int) ($conteo['PENDIENTE'] ?? 0);
                $totalSinEntregar = (int) ($conteo['SIN_ENTREGAR'] ?? 0);
                $totalCorreccion = (int) ($conteo['CORRECCION_SOLICITADA'] ?? 0);
                $totalEntregaron = $totalPorEvaluar + $totalCalificados + $totalCorreccion;

                $fechaLimite = $g['fechaLimite'];
                $vencida = false;
                if ($fechaLimite) {
                    $vencida = $now->greaterThan(\Carbon\Carbon::parse($fechaLimite, $tz));
                }

                if ($totalAsignados === 0) {
                    $estadoGeneral = 'no_asignada';
                } elseif ($totalPorEvaluar > 0 || $totalCorreccion > 0) {
                    $estadoGeneral = 'por_evaluar';
                } elseif ($totalCalificados === $totalAsignados) {
                    $estadoGeneral = 'calificada';
                } elseif ($totalCalificados > 0) {
                    $estadoGeneral = 'parcial';
                } elseif ($vencida) {
                    $estadoGeneral = 'vencida';
                } else {
                    $estadoGeneral = 'activa';
                }

                $nombreCreador = trim(implode(' ', array_filter([
                    $row->creadorNombre1,
                    $row->creadorNombre2,
                    $row->creadorApellido1,
                    $row->creadorApellido2,
                ])));

                $data[] = [
                    'idActividad' => (int) $row->idActividad,
                    'titulo' => $row->tituloActividad ?? 'Sin título',
                    'descripcion' => $row->descripcionActividad,
                    'idFicha' => (int) ($row->idFicha ?? 0),
                    'codigoFicha' => $row->codigoFicha,
                    'idMateria' => (int) ($row->idMateria ?? 0),
                    'materiaNombre' => $row->competenciaNombre ?? $row->nombreMateria,
                    'idCompetencia' => ! empty($row->idMateriaPadre) ? (int) $row->idMateriaPadre : null,
                    'competenciaNombre' => $row->competenciaNombre ?? $row->nombreMateria,
                    'idRap' => $row->idRap ? (int) $row->idRap : (int) ($row->idMateria ?? 0),
                    'rapNombre' => $row->rapNombre ?? $row->nombreMateria,
                    'codigoRap' => $row->codigoRap,
                    'numeroRap' => Materia::numeroOrdenRap(
                        $row->codigoRap ?? $row->codigoMateria ?? null,
                        $row->rapNombre ?? $row->nombreMateria ?? null
                    ),
                    'fechaInicio' => $g['fechaInicial'],
                    'fechaLimite' => $fechaLimite,
                    'estadoGeneral' => $estadoGeneral,
                    'vencida' => $vencida,
                    'tipoActividad' => $row->tipoActividad,
                    'modalidad' => $g['tieneGrupo'] ? 'grupal' : 'individual',
                    'totalAsignados' => $totalAsignados,
                    'totalEntregaron' => $totalEntregaron,
                    'totalPendientes' => $totalPendientes,
                    'totalCalificados' => $totalCalificados,
                    'totalPorEvaluar' => $totalPorEvaluar,
                    'totalSinEntregar' => $totalSinEntregar,
                    'totalCorreccionSolicitada' => $totalCorreccion,
                    'idHorarioMateria' => $row->idHorarioMateria ? (int) $row->idHorarioMateria : null,
                    'creador' => [
                        'idPersona' => $row->idPersonaCreador ? (int) $row->idPersonaCreador : null,
                        'nombre' => $nombreCreador !== '' ? $nombreCreador : 'Instructor',
                        'fotoPerfil' => $this->resolvePersonaPublicFotoUrl($row->creadorRutaFoto ?? null),
                    ],
                ];
            }

            usort($data, function ($a, $b) {
                $fa = $a['fechaLimite'] ?? '';
                $fb = $b['fechaLimite'] ?? '';

                return strcmp($fb, $fa);
            });

            return response()->json(['data' => $data]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage(), 'data' => []], 500);
        }
    }

    public function responderActividadAprendiz(Request $request, int $idCalificacionActividad): JsonResponse
    {
        try {
            if (!Schema::hasTable('calificacionActividad')) {
                return response()->json(['error' => 'Tabla no disponible'], 500);
            }

            $user = KeyUtil::user();
            $idPersona = $user?->idpersona;

            if (!$idPersona) {
                return response()->json(['error' => 'Usuario autenticado sin persona asociada'], 401);
            }

            $validator = Validator::make($request->all(), [
                'comentarioEstudiante' => 'nullable|string|max:3000',
                'archivo' => 'nullable|file|max:51200',
            ], [
                'archivo.max' => 'El archivo supera el tamaño máximo permitido de 50 MB.',
            ]);

            $validator->after(function ($v) use ($request) {
                if (! $request->hasFile('archivo')) {
                    return;
                }

                $file = $request->file('archivo');
                if (! $file) {
                    return;
                }

                $allowedExtensions = ['pdf', 'doc', 'docx', 'png', 'jpg', 'jpeg', 'zip', 'rar', 'sql'];
                $ext = strtolower((string) $file->getClientOriginalExtension());

                if ($ext === '' || ! in_array($ext, $allowedExtensions, true)) {
                    $v->errors()->add('archivo', 'Tipo de archivo no permitido. Solo se permiten: PDF, DOC, DOCX, PNG, JPG, JPEG, ZIP, RAR, SQL.');
                    return;
                }

                if ($ext === 'sql') {
                    $mime = strtolower((string) ($file->getMimeType() ?? ''));
                    $allowedSqlMimes = [
                        'text/plain',
                        'text/x-sql',
                        'application/sql',
                        'application/x-sql',
                        'application/octet-stream',
                    ];

                    // MIME vacío: permitido SOLO si la extensión ya es .sql
                    if ($mime !== '' && ! in_array($mime, $allowedSqlMimes, true)) {
                        $v->errors()->add('archivo', 'El archivo SQL no tiene un tipo válido.');
                    }

                    return;
                }

                $entregaMsg = 'Tipo de archivo no permitido. Solo se permiten: PDF, DOC, DOCX, PNG, JPG, JPEG, ZIP, RAR, SQL.';

                // Word: no usar mimes: (falla con octet-stream / ZIP / OLE detectados por finfo).
                if ($this->assertWordFileByExtensionAndMime($v, $file, 'archivo', $entregaMsg)) {
                    return;
                }

                // Para los demás tipos, mantenemos la validación segura por "mimes" (sin sql/doc/docx).
                $secondary = Validator::make(['archivo' => $file], [
                    'archivo' => 'mimes:pdf,png,jpg,jpeg,zip,rar',
                ]);

                if ($secondary->fails()) {
                    $v->errors()->add('archivo', $entregaMsg);
                }
            });

            $validated = $validator->validate();

            $tableMa = Schema::hasTable('matriculaAcademica') ? 'matriculaAcademica' : 'matriculaacademica';

            $registro = DB::table('calificacionActividad as ca')
                ->join($tableMa . ' as ma', 'ca.idAMartriculaAcademica', '=', 'ma.id')
                ->join('matricula as m', 'ma.idMatricula', '=', 'm.id')
                ->join('actividades as a', 'ca.idActividad', '=', 'a.id')
                ->leftJoin('estado as e', 'a.idEstado', '=', 'e.id')
                ->where('ca.id', $idCalificacionActividad)
                ->where('m.idPersona', $idPersona)
                ->select('ca.id', 'ca.archivo', 'ca.fechaFinal', 'ca.ComentarioDocente', 'a.tipoActividad', 'e.estado as estadoActividad')
                ->first();

            if (!$registro) {
                return response()->json(['error' => 'Actividad no encontrada para este aprendiz'], 404);
            }

            $tz = config('app.timezone');
            if ($registro->fechaFinal && now($tz)->greaterThan(\Carbon\Carbon::parse((string) $registro->fechaFinal, $tz))) {
                return response()->json(['error' => 'El tiempo de entrega ha finalizado. No puedes entregar esta actividad.'], 422);
            }

            if (strtoupper(trim($registro->estadoActividad ?? 'ACTIVO')) !== 'ACTIVO') {
                return response()->json(['error' => 'Esta actividad no está activa. No puedes entregar hasta que el instructor la habilite.'], 422);
            }

            $esCuestionario = strtolower(trim($registro->tipoActividad ?? '')) === 'cuestionario';
            if ($esCuestionario) {
                return response()->json(['error' => 'Esta actividad es un cuestionario. Usa la opción de responder cuestionario.'], 422);
            }

            $archivoPath = $registro->archivo;
            $tieneArchivoActual = !empty(trim((string) $archivoPath)) && Storage::disk('public')->exists($archivoPath);

            if (!$request->hasFile('archivo') && !$tieneArchivoActual) {
                return response()->json(['error' => 'Debes adjuntar un archivo como evidencia para poder entregar la actividad.'], 422);
            }

            if ($request->hasFile('archivo')) {
                if ($archivoPath && Storage::disk('public')->exists($archivoPath)) {
                    Storage::disk('public')->delete($archivoPath);
                }

                $file = $request->file('archivo');
                $dir = "actividades/entregas/{$idCalificacionActividad}";
                if (!Storage::disk('public')->exists($dir)) {
                    Storage::disk('public')->makeDirectory($dir, 0755, true);
                }

                $filename = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $file->getClientOriginalName());
                $archivoPath = $file->storeAs($dir, $filename, 'public');
            }

            $comDocLimpio = $this->comentarioDocenteSinMarcaCorreccion($registro->ComentarioDocente ?? null);

            DB::table('calificacionActividad')
                ->where('id', $idCalificacionActividad)
                ->update([
                    'ComentarioEstudiante' => $validated['comentarioEstudiante'] ?? null,
                    'archivo' => $archivoPath,
                    'ComentarioDocente' => $comDocLimpio,
                    'updated_at' => now(),
                ]);

            return response()->json([
                'message' => 'Entrega registrada correctamente',
                'archivoUrl' => $this->publicUrl($archivoPath),
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Responder cuestionario: el aprendiz envía sus respuestas a cada pregunta.
     */
    public function responderCuestionarioAprendiz(Request $request, int $idCalificacionActividad): JsonResponse
    {
        try {
            if (!Schema::hasTable('calificacionActividad')) {
                return response()->json(['error' => 'Tabla no disponible'], 500);
            }

            $user = KeyUtil::user();
            $idPersona = $user?->idpersona;
            if (!$idPersona) {
                return response()->json(['error' => 'Usuario autenticado sin persona asociada'], 401);
            }

            $tableMa = Schema::hasTable('matriculaAcademica') ? 'matriculaAcademica' : 'matriculaacademica';
            $ca = DB::table('calificacionActividad as ca')
                ->join($tableMa . ' as ma', 'ca.idAMartriculaAcademica', '=', 'ma.id')
                ->join('matricula as m', 'ma.idMatricula', '=', 'm.id')
                ->join('actividades as a', 'ca.idActividad', '=', 'a.id')
                ->leftJoin('estado as e', 'a.idEstado', '=', 'e.id')
                ->where('ca.id', $idCalificacionActividad)
                ->where('m.idPersona', $idPersona)
                ->select('ca.id', 'ca.idActividad', 'ca.fechaFinal', 'e.estado as estadoActividad')
                ->first();

            if (!$ca) {
                return response()->json(['error' => 'Actividad no encontrada para este aprendiz'], 404);
            }

            $tzC = config('app.timezone');
            if ($ca->fechaFinal && now($tzC)->greaterThan(\Carbon\Carbon::parse((string) $ca->fechaFinal, $tzC))) {
                return response()->json(['error' => 'El tiempo de entrega ha finalizado. No puedes responder este cuestionario.'], 422);
            }

            if (strtoupper(trim($ca->estadoActividad ?? 'ACTIVO')) !== 'ACTIVO') {
                return response()->json(['error' => 'Este cuestionario no está activo. No puedes responder hasta que el instructor lo habilite.'], 422);
            }

            $actividad = Actividad::with(['preguntas.respuestas', 'preguntas.tipoPregunta'])->find($ca->idActividad);
            if (!$actividad || ($actividad->tipoActividad ?? '') !== 'cuestionario') {
                return response()->json(['error' => 'Esta actividad no es un cuestionario'], 422);
            }

            $validated = $request->validate([
                'respuestas' => 'required|array',
                'respuestas.*.idPregunta' => 'required|integer|exists:preguntas,id',
                'respuestas.*.idRespuesta' => 'nullable|integer|exists:respuestas,id',
                'respuestas.*.respuesta' => 'nullable|string|max:5000',
            ]);

            $idsPermitidos = CuestionarioAsignacionUtil::idsParaCalificacion($idCalificacionActividad);

            $tblRc = Schema::hasTable('respuestaCuestionarios') ? 'respuestaCuestionarios' : (Schema::hasTable('respuesta_cuestionarios') ? 'respuesta_cuestionarios' : null);
            if (!$tblRc) {
                return response()->json(['error' => 'Tabla de respuestas de cuestionario no disponible'], 500);
            }

            foreach ($validated['respuestas'] as $r) {
                if ($idsPermitidos !== null && !in_array((int) $r['idPregunta'], $idsPermitidos, true)) {
                    continue;
                }
                $idRespuesta = isset($r['idRespuesta']) && $r['idRespuesta'] ? (int) $r['idRespuesta'] : null;
                $respuestaTexto = trim($r['respuesta'] ?? '');
                if ($idRespuesta === null && $respuestaTexto === '') {
                    continue;
                }

                $filasPregunta = DB::table($tblRc)
                    ->where('idCalificacion', $idCalificacionActividad)
                    ->where('idPregunta', $r['idPregunta'])
                    ->get();
                $filaRespuesta = $filasPregunta->first(fn ($row) => !CuestionarioAsignacionUtil::esMarcador($row));

                if ($filaRespuesta) {
                    DB::table($tblRc)
                        ->where('id', $filaRespuesta->id)
                        ->update(['idRespuesta' => $idRespuesta, 'respuesta' => $respuestaTexto ?: null]);
                } else {
                    DB::table($tblRc)->insert([
                        'idCalificacion' => $idCalificacionActividad,
                        'idPregunta' => $r['idPregunta'],
                        'idRespuesta' => $idRespuesta,
                        'respuesta' => $respuestaTexto ?: null,
                        'calificado' => false,
                    ]);
                }
            }

            DB::table('calificacionActividad')
                ->where('id', $idCalificacionActividad)
                ->update(['ComentarioEstudiante' => 'Cuestionario respondido', 'updated_at' => now()]);

            // Calificación automática para preguntas de selección múltiple (Varias opciones)
            $this->calificarCuestionarioAutomatico($idCalificacionActividad, $ca->idActividad, $tblRc);

            return response()->json(['message' => 'Cuestionario respondido correctamente']);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Revisión de intento de cuestionario (solo lectura) para el aprendiz dueño de la calificación.
     */
    public function revisionCuestionarioAprendiz(int $idCalificacionActividad): JsonResponse
    {
        try {
            if (!Schema::hasTable('calificacionActividad')) {
                return response()->json(['error' => 'Tabla no disponible'], 500);
            }

            $user = KeyUtil::user();
            $idPersona = $user?->idpersona;
            if (!$idPersona) {
                return response()->json(['error' => 'Usuario autenticado sin persona asociada'], 401);
            }

            $tableMa = Schema::hasTable('matriculaAcademica') ? 'matriculaAcademica' : 'matriculaacademica';
            $ca = DB::table('calificacionActividad as ca')
                ->join($tableMa . ' as ma', 'ca.idAMartriculaAcademica', '=', 'ma.id')
                ->join('matricula as m', 'ma.idMatricula', '=', 'm.id')
                ->join('actividades as a', 'ca.idActividad', '=', 'a.id')
                ->where('ca.id', $idCalificacionActividad)
                ->where('m.idPersona', $idPersona)
                ->select([
                    'ca.id',
                    'ca.idActividad',
                    'ca.calificacionNumerica',
                    'ca.fechaCalificacion',
                    'ca.ComentarioDocente',
                    'ca.ComentarioEstudiante',
                    'a.tituloActividad',
                    'a.tipoActividad',
                ])
                ->first();

            if (!$ca) {
                return response()->json(['error' => 'Intento no encontrado para este aprendiz'], 404);
            }

            if (($ca->tipoActividad ?? '') !== 'cuestionario') {
                return response()->json(['error' => 'Esta actividad no es un cuestionario'], 422);
            }

            $tblRc = Schema::hasTable('respuestaCuestionarios')
                ? 'respuestaCuestionarios'
                : (Schema::hasTable('respuesta_cuestionarios') ? 'respuesta_cuestionarios' : null);
            if (!$tblRc) {
                return response()->json(['error' => 'Tabla de respuestas de cuestionario no disponible'], 500);
            }

            $respuestasAlumno = DB::table($tblRc)
                ->where('idCalificacion', $idCalificacionActividad)
                ->get()
                ->filter(fn ($row) => !CuestionarioAsignacionUtil::esMarcador($row))
                ->keyBy('idPregunta');

            if ($respuestasAlumno->isEmpty()) {
                return response()->json(['error' => 'Aún no hay un intento respondido para este cuestionario'], 404);
            }

            $idsAsignados = CuestionarioAsignacionUtil::idsParaCalificacion($idCalificacionActividad);

            // Tras haber respondido, se muestran las correctas (no existe flag de configuración en el modelo).
            $mostrarRespuestasCorrectas = true;

            $preguntasQuery = Pregunta::with(['tipoPregunta', 'respuestas'])
                ->where('idActividad', $ca->idActividad)
                ->orderBy('id');
            if ($idsAsignados !== null) {
                $preguntasQuery->whereIn('id', $idsAsignados);
            }
            $preguntas = CuestionarioAsignacionUtil::ordenarColeccionPreguntas(
                $preguntasQuery->get(),
                $idCalificacionActividad,
                (int) $ca->idActividad
            );

            $correctas = 0;
            $incorrectas = 0;
            $pendientes = 0;

            $preguntasPayload = $preguntas->map(function ($pregunta) use (
                $respuestasAlumno,
                $mostrarRespuestasCorrectas,
                $ca,
                &$correctas,
                &$incorrectas,
                &$pendientes
            ) {
                $tipo = trim((string) ($pregunta->tipoPregunta->tipoPregunta ?? 'Párrafo'));
                $esOpcionMultiple = strcasecmp($tipo, 'Varias opciones') === 0;
                $respAlumno = $respuestasAlumno->get($pregunta->id);

                $opciones = [];
                $respuestaCorrecta = null;
                $respuestaAprendiz = null;
                $estado = 'pendiente';
                $puntaje = null;
                $retroalimentacion = null;

                if ($esOpcionMultiple) {
                    $idSeleccionada = $respAlumno?->idRespuesta ? (int) $respAlumno->idRespuesta : null;
                    $textoSeleccionada = null;

                    foreach ($pregunta->respuestas ?? [] as $op) {
                        $esCorrecta = (bool) $op->chkCorrecta;
                        $seleccionada = $idSeleccionada !== null && (int) $op->id === $idSeleccionada;
                        if ($seleccionada) {
                            $textoSeleccionada = $op->descripcionRespuesta;
                        }
                        if ($esCorrecta) {
                            $respuestaCorrecta = [
                                'id' => (int) $op->id,
                                'texto' => $op->descripcionRespuesta,
                            ];
                        }
                        $opciones[] = [
                            'id' => (int) $op->id,
                            'texto' => $op->descripcionRespuesta,
                            'seleccionada' => $seleccionada,
                            'esCorrecta' => $mostrarRespuestasCorrectas ? $esCorrecta : null,
                        ];
                    }

                    $respuestaAprendiz = [
                        'idRespuesta' => $idSeleccionada,
                        'texto' => $textoSeleccionada,
                    ];

                    if ($idSeleccionada === null) {
                        $estado = 'incorrecta';
                        $incorrectas++;
                    } elseif ($respuestaCorrecta && $idSeleccionada === (int) $respuestaCorrecta['id']) {
                        $estado = 'correcta';
                        $correctas++;
                    } else {
                        $estado = 'incorrecta';
                        $incorrectas++;
                    }

                    if (!$mostrarRespuestasCorrectas) {
                        $respuestaCorrecta = null;
                    }
                } else {
                    $texto = trim((string) ($respAlumno->respuesta ?? ''));
                    $respuestaAprendiz = ['texto' => $texto !== '' ? $texto : null];

                    $calificadoPregunta = (bool) ($respAlumno->calificado ?? false);
                    $puntajePregunta = $respAlumno->puntaje ?? null;

                    // Párrafo: solo se considera calificado si el instructor marcó la pregunta
                    // (la nota automática del cuestionario no califica párrafos).
                    if ($calificadoPregunta) {
                        $estado = 'calificada';
                        $puntaje = $puntajePregunta !== null ? (float) $puntajePregunta : null;
                        $retroalimentacion = $ca->ComentarioDocente ?: null;
                    } else {
                        $estado = 'pendiente';
                        $pendientes++;
                    }
                }

                return [
                    'id' => (int) $pregunta->id,
                    'descripcion' => $pregunta->descripcion,
                    'tipoPregunta' => $tipo,
                    'urlDocumento' => $pregunta->urlDocumento,
                    'urlDocumentoUrl' => $this->publicUrl($pregunta->urlDocumento),
                    'estado' => $estado,
                    'puntaje' => $puntaje,
                    'retroalimentacion' => $retroalimentacion,
                    'respuestaAprendiz' => $respuestaAprendiz,
                    'respuestaCorrecta' => $respuestaCorrecta,
                    'opciones' => $opciones,
                ];
            })->values();

            $notaFinal = null;
            if ($ca->calificacionNumerica !== null && trim((string) $ca->calificacionNumerica) !== '') {
                $notaFinal = (float) $ca->calificacionNumerica;
            }
            $porcentaje = $notaFinal !== null ? round(($notaFinal / 5) * 100, 1) : null;

            return response()->json([
                'idCalificacionActividad' => (int) $ca->id,
                'idActividad' => (int) $ca->idActividad,
                'tituloActividad' => $ca->tituloActividad,
                'notaFinal' => $notaFinal,
                'porcentaje' => $porcentaje,
                'comentarioDocente' => $ca->ComentarioDocente,
                'mostrarRespuestasCorrectas' => $mostrarRespuestasCorrectas,
                'resumen' => [
                    'totalPreguntas' => $preguntasPayload->count(),
                    'correctas' => $correctas,
                    'incorrectas' => $incorrectas,
                    'pendientes' => $pendientes,
                ],
                'preguntas' => $preguntasPayload,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'tituloActividad' => 'required|string',
                'descripcionActividad' => 'required|string',
                'pathDocumentoActividad' => 'nullable|string|max:255',
                'autor' => 'nullable|string|max:255',
                'tipoActividad' => 'required|in:sin evidencia,con evidencia,cuestionario',
                'idMateria' => 'required|exists:materia,id',
                'idEstado' => 'nullable|exists:estado,id',
                'idCompany' => 'required|exists:empresa,id',
                'idPersona' => 'nullable|exists:persona,id',
                'idClasificacion' => 'nullable|exists:clasificacionActividad,id',
                'estrategia' => 'required|string',
                'entregables' => 'required|string',
            ]);
            if (empty($validated['idPersona'])) {
                $user = KeyUtil::user();
                $validated['idPersona'] = $user?->idpersona ?? null;
            }

            $this->assertIdMateriaEsRap((int) $validated['idMateria']);

            $actividad = Actividad::create($validated);
            DiagnosticoActividadesRapHistoricas::logCreacionActividad(
                (int) $actividad->id,
                (int) $actividad->idMateria,
                'store'
            );

            return response()->json($actividad, 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function update(Request $request, int $id): JsonResponse
    {
        try {
            $actividad = Actividad::findOrFail($id);
            $validated = $request->validate([
                'tituloActividad' => 'sometimes|required|string',
                'descripcionActividad' => 'sometimes|required|string',
                'pathDocumentoActividad' => 'nullable|string|max:255',
                'autor' => 'nullable|string|max:255',
                'tipoActividad' => 'sometimes|required|in:sin evidencia,con evidencia,cuestionario',
                'idMateria' => 'sometimes|required|exists:materia,id',
                'idEstado' => 'nullable|exists:estado,id',
                'idCompany' => 'sometimes|required|exists:empresa,id',
                'idPersona' => 'nullable|exists:persona,id',
                'idClasificacion' => 'nullable|exists:clasificacionActividad,id',
                'estrategia' => 'sometimes|required|string',
                'entregables' => 'sometimes|required|string',
            ]);

            if (array_key_exists('idMateria', $validated)) {
                $this->assertIdMateriaEsRap((int) $validated['idMateria']);
            }

            $actividad->update($validated);
            return response()->json($actividad);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function show(Request $request, int $id): JsonResponse
    {
        try {
            $actividad = Actividad::with(['materia', 'estado', 'clasificacion', 'persona'])->findOrFail($id);
            if ($actividad->tipoActividad === 'cuestionario') {
                $actividad->load(['preguntas.tipoPregunta', 'preguntas.respuestas']);
                $idCalif = $request->query('idCalificacionActividad');
                if ($idCalif) {
                    $preguntas = $actividad->preguntas;
                    $ids = CuestionarioAsignacionUtil::idsParaCalificacion((int) $idCalif);
                    if ($ids !== null) {
                        $preguntas = $preguntas->whereIn('id', $ids);
                    }
                    $actividad->setRelation(
                        'preguntas',
                        CuestionarioAsignacionUtil::ordenarColeccionPreguntas(
                            $preguntas,
                            (int) $idCalif,
                            (int) $actividad->id
                        )
                    );
                }
            }
            return response()->json($actividad);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 404);
        }
    }

    public function destroy(int $id): JsonResponse
    {
        try {
            $actividad = Actividad::findOrFail($id);
            $tieneAsignacion = PlaneacionActividad::where('idActividad', $id)->exists();
            if ($tieneAsignacion) {
                return response()->json(['error' => 'No se puede eliminar una actividad que est? asignada a una clase. Qu?tela primero de la clase.'], 422);
            }
            $actividad->delete();
            return response()->json(['message' => 'Actividad eliminada']);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function tipoActividades(): JsonResponse
    {
        try {
            $idCompany = KeyUtil::idCompany();
            $tipos = TipoActividad::where('idCompany', $idCompany)->get();
            return response()->json($tipos);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function clasificaciones(): JsonResponse
    {
        try {
            $idCompany = KeyUtil::idCompany();
            $clasificaciones = ClasificacionActividad::where('idCompany', $idCompany)
                ->orWhereNull('idCompany')
                ->get();
            return response()->json($clasificaciones);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function materias(): JsonResponse
    {
        try {
            $idCompany = KeyUtil::idCompany();
            $materias = Materia::where('idEmpresa', $idCompany)->get();
            return response()->json($materias);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function estados(): JsonResponse
    {
        try {
            $estados = Status::all();
            return response()->json($estados);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function uploadDocumento(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'documento' => 'required|file|max:51200',
            ], [
                'documento.max' => 'El archivo no puede superar los 50 MB.',
            ]);

            $validator->after(function ($v) use ($request) {
                $this->assertActividadDocumentoFile($v, $request->file('documento'), 'documento');
            });

            $validator->validate();

            $file = $request->file('documento');
            $dir = 'actividades/documentos';
            if (!Storage::disk('public')->exists($dir)) {
                Storage::disk('public')->makeDirectory($dir);
            }
            $filename = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $file->getClientOriginalName());
            $path = $file->storeAs($dir, $filename, 'public');
            $url = Storage::disk('public')->url($path);
            return response()->json(['path' => $path, 'url' => $url]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Sube el documento principal de una actividad en actividades/{id}/
     */
    public function uploadDocumentoActividad(Request $request, int $id): JsonResponse
    {
        try {
            $actividad = Actividad::findOrFail($id);
            $validator = Validator::make($request->all(), [
                'documento' => 'required|file|max:51200',
            ], [
                'documento.max' => 'El archivo no puede superar los 50 MB.',
            ]);

            $validator->after(function ($v) use ($request) {
                $this->assertActividadDocumentoFile($v, $request->file('documento'), 'documento');
            });

            $validator->validate();

            $file = $request->file('documento');
            $dir = "actividades/{$id}";
            if (!Storage::disk('public')->exists($dir)) {
                Storage::disk('public')->makeDirectory($dir);
            }
            $filename = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $file->getClientOriginalName());
            $path = $file->storeAs($dir, $filename, 'public');
            $actividad->update(['pathDocumentoActividad' => $path]);
            $url = Storage::disk('public')->url($path);
            return response()->json(['path' => $path, 'url' => $url]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function planeacionPorFicha(int $idFicha): JsonResponse
    {
        try {
            $ficha = \App\Models\Ficha::with('asignacion')->findOrFail($idFicha);
            if (!\Illuminate\Support\Facades\Schema::hasTable('planeacion')) {
                return response()->json(['id' => null]);
            }
            $idContrato = null;
            if (\Illuminate\Support\Facades\Schema::hasTable('horarioMateria') && \Illuminate\Support\Facades\Schema::hasColumn('horarioMateria', 'idContrato')) {
                $horario = \Illuminate\Support\Facades\DB::table('horarioMateria')
                    ->where('idFicha', $idFicha)
                    ->whereNotNull('idContrato')
                    ->first();
                $idContrato = $horario->idContrato ?? null;
            }
            if (!$idContrato || !\Illuminate\Support\Facades\Schema::hasColumn('planeacion', 'idContrato')) {
                return response()->json(['id' => null]);
            }
            $planeacion = \Illuminate\Support\Facades\DB::table('planeacion')
                ->where('idContrato', $idContrato)->first();
            return response()->json($planeacion ?? ['id' => null]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function planeacionActividadesPorFicha(Request $request, int $id): JsonResponse
    {
        try {
            $idFicha = $id;
            $ficha = \App\Models\Ficha::with('asignacion')->findOrFail($idFicha);
            $items = collect();

            if (\Illuminate\Support\Facades\Schema::hasTable('planeacion')) {
                $idContrato = null;
                if (\Illuminate\Support\Facades\Schema::hasTable('horarioMateria') && \Illuminate\Support\Facades\Schema::hasColumn('horarioMateria', 'idContrato')) {
                    $idHmParam = $request->query('id_horario_materia');
                    $idHm = ($idHmParam !== null && $idHmParam !== '') ? (int) $idHmParam : 0;
                    $horario = null;
                    // Preferir el horario de la clase actual (misma ficha) para no tomar otro contrato/planeación por accidente.
                    if ($idHm > 0) {
                        $horario = \Illuminate\Support\Facades\DB::table('horarioMateria')
                            ->where('id', $idHm)
                            ->where('idFicha', $idFicha)
                            ->whereNotNull('idContrato')
                            ->first();
                    }
                    if (! $horario) {
                        $horario = \Illuminate\Support\Facades\DB::table('horarioMateria')
                            ->where('idFicha', $idFicha)
                            ->whereNotNull('idContrato')
                            ->orderBy('id')
                            ->first();
                    }
                    $idContrato = $horario->idContrato ?? null;
                }
                $idPlaneacion = null;
                if ($idContrato && \Illuminate\Support\Facades\Schema::hasColumn('planeacion', 'idContrato')) {
                    $planeacion = \Illuminate\Support\Facades\DB::table('planeacion')
                        ->where('idContrato', $idContrato)->first();
                    $idPlaneacion = $planeacion->id ?? null;
                }
                if ($idPlaneacion) {
                    $items = PlaneacionActividad::with(['actividad.persona', 'actividad.materia', 'actividad.estado'])
                        ->where('idPlaneacion', $idPlaneacion)
                        ->orderBy('id', 'asc')
                        ->get();
                }
            }

            // Fallback: actividades asignadas en calificacionActividad (sin planeación)
            if ($items->isEmpty() && \Illuminate\Support\Facades\Schema::hasTable('calificacionActividad')) {
                $tableMa = \Illuminate\Support\Facades\Schema::hasTable('matriculaAcademica') ? 'matriculaAcademica' : 'matriculaacademica';
                $colFicha = \Illuminate\Support\Facades\Schema::hasColumn($tableMa, 'idFicha') ? 'idFicha' : (\Illuminate\Support\Facades\Schema::hasColumn($tableMa, 'idAsignacionPeriodoProgramaJornada') ? 'idAsignacionPeriodoProgramaJornada' : 'idFicha');
                $idsActividad = \Illuminate\Support\Facades\DB::table('calificacionActividad as ca')
                    ->join($tableMa . ' as ma', 'ca.idAMartriculaAcademica', '=', 'ma.id')
                    ->where('ma.' . $colFicha, $idFicha)
                    ->distinct()
                    ->pluck('ca.idActividad');
                if ($idsActividad->isNotEmpty()) {
                    $actividades = Actividad::with(['persona', 'materia', 'estado'])
                        ->whereIn('id', $idsActividad)
                        ->orderBy('id', 'asc')
                        ->get();
                    foreach ($actividades as $act) {
                        $items->push((object) [
                            'id' => $act->id,
                            'idActividad' => $act->id,
                            'idMateria' => $act->idMateria,
                            'actividad' => $act,
                        ]);
                    }
                }
            }

            /**
             * Si se envía id_materia_clase (RAP de la clase), limitar resultados exactamente a ese RAP.
             * Fuente de verdad: actividades.idMateria (no el idMateria histórico de planeacionActividades).
             */
            $mcPlaneacion = $request->query('id_materia_clase');
            if ($items->isNotEmpty() && $mcPlaneacion !== null && $mcPlaneacion !== '' && (int) $mcPlaneacion > 0) {
                $idRapClase = (int) $mcPlaneacion;
                $items = $items->filter(function ($item) use ($idRapClase) {
                    $idM = (int) (
                        (isset($item->actividad) ? ($item->actividad->idMateria ?? 0) : 0)
                        ?: ($item->idMateria ?? 0)
                    );

                    return $idM > 0 && $idM === $idRapClase;
                })->values();
            }

            // Filtro estricto por materia/RAP cuando el cliente lo envía explícitamente.
            $idMateriaExacta = (int) $request->query('idMateria', 0);
            $idRapExacto = (int) $request->query('idRap', 0);
            if ($items->isNotEmpty() && ($idMateriaExacta > 0 || $idRapExacto > 0)) {
                $idMateriaFiltro = $idRapExacto > 0 ? $idRapExacto : $idMateriaExacta;
                $items = $items->filter(function ($item) use ($idMateriaFiltro) {
                    $idM = (int) (
                        (isset($item->actividad) ? ($item->actividad->idMateria ?? 0) : 0)
                        ?: ($item->idMateria ?? 0)
                    );

                    return $idM > 0 && $idM === $idMateriaFiltro;
                })->values();
            }

            // Evita filas repetidas de la misma actividad (posibles duplicados históricos en planeación).
            if ($items->isNotEmpty()) {
                $items = $items->unique(function ($item) {
                    return (int) ($item->idActividad ?? (isset($item->actividad) ? ($item->actividad->id ?? 0) : 0));
                })->values();
            }

            // Enriquecer con fechaInicial/fechaFinal y fechaVencida desde calificacionActividad
            // Usar MAX(fechaFinal) y MIN(fechaInicial) por actividad para respetar ampliaciones
            // y evitar inconsistencias cuando hay múltiples calificaciones por actividad
            if ($items->isNotEmpty() && \Illuminate\Support\Facades\Schema::hasTable('calificacionActividad')) {
                $tableMa = \Illuminate\Support\Facades\Schema::hasTable('matriculaAcademica') ? 'matriculaAcademica' : 'matriculaacademica';
                $colFicha = \Illuminate\Support\Facades\Schema::hasColumn($tableMa, 'idFicha') ? 'idFicha' : (\Illuminate\Support\Facades\Schema::hasColumn($tableMa, 'idAsignacionPeriodoProgramaJornada') ? 'idAsignacionPeriodoProgramaJornada' : 'idFicha');
                $idsActividad = $items->pluck('idActividad')->filter()->unique()->values();
                $fechasPorActividad = \Illuminate\Support\Facades\DB::table('calificacionActividad as ca')
                    ->join($tableMa . ' as ma', 'ca.idAMartriculaAcademica', '=', 'ma.id')
                    ->whereIn('ca.idActividad', $idsActividad)
                    ->where('ma.' . $colFicha, $idFicha)
                    ->select(
                        'ca.idActividad',
                        \Illuminate\Support\Facades\DB::raw('MIN(ca.fechaInicial) as fechaInicial'),
                        \Illuminate\Support\Facades\DB::raw('MAX(ca.fechaFinal) as fechaFinal')
                    )
                    ->groupBy('ca.idActividad')
                    ->get()
                    ->keyBy('idActividad');
                $now = now();
                foreach ($items as $item) {
                    $idAct = $item->idActividad ?? (isset($item->actividad) ? $item->actividad->id : null);
                    $fechas = $idAct ? $fechasPorActividad->get($idAct) : null;
                    $item->fechaInicial = $fechas ? ($fechas->fechaInicial ?? null) : null;
                    $item->fechaFinal = $fechas ? ($fechas->fechaFinal ?? null) : null;
                    $fechaInicial = $item->fechaInicial ? \Carbon\Carbon::parse($item->fechaInicial) : null;
                    $fechaFinal = $item->fechaFinal ? \Carbon\Carbon::parse($item->fechaFinal) : null;
                    // Estado: solo fecha límite y hora actual (ampliación no debe alterar otras actividades)
                    $item->fechaVencida = $fechaFinal ? $now->greaterThan($fechaFinal) : false;
                    $item->fechaInactiva = $fechaInicial ? $now->lessThan($fechaInicial) : false;
                }
            }

            // Compatibilidad histórica: listado vacío → diagnosticar hermanos (solo logs; no altera el JSON).
            if ($items->isEmpty()) {
                $idRapDiag = $idRapExacto > 0
                    ? $idRapExacto
                    : ($idMateriaExacta > 0
                        ? $idMateriaExacta
                        : ((int) ($mcPlaneacion ?: 0)));
                if ($idRapDiag > 0) {
                    DiagnosticoActividadesRapHistoricas::diagnosticarListadoVacio(
                        $idRapDiag,
                        null,
                        $idFicha,
                        'GET planeacionactividades/ficha'
                    );
                }
            }

            return response()->json($items);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function asignarPlaneacionActividad(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'idActividad' => 'required|exists:actividades,id',
                'idMateria' => 'required|exists:materia,id',
                'idPlaneacion' => 'required',
            ]);
            $idPlaneacion = $validated['idPlaneacion'];
            if (is_array($idPlaneacion)) {
                $idPlaneacion = $idPlaneacion['id'] ?? $idPlaneacion[0] ?? null;
            }
            $validated['idPlaneacion'] = (int) $idPlaneacion;

            // Fuente de verdad: actividades.idMateria (RAP propietario). Evita desfase histórico.
            $actividad = Actividad::query()->findOrFail((int) $validated['idActividad']);
            $idMateriaRap = (int) ($actividad->idMateria ?? 0);
            $this->assertIdMateriaEsRap($idMateriaRap);
            if ((int) $validated['idMateria'] !== $idMateriaRap) {
                $validated['idMateria'] = $idMateriaRap;
            }

            $item = PlaneacionActividad::firstOrCreate([
                'idActividad' => $validated['idActividad'],
                'idMateria' => $validated['idMateria'],
                'idPlaneacion' => $validated['idPlaneacion'],
            ]);
            return response()->json($item, 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function quitarPlaneacionActividad(int $id): JsonResponse
    {
        try {
            $item = PlaneacionActividad::findOrFail($id);
            $item->delete();
            return response()->json(['message' => 'Actividad quitada de la planeación']);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Lista únicamente RAPs (no competencias) desde horarios de esta ficha.
     * Ordenados por número de RAP (01, 02, …), nunca por id de BD.
     */
    public function rapsHorarioFicha(Request $request, int $idFicha): JsonResponse
    {
        try {
            \App\Models\Ficha::query()->findOrFail($idFicha);

            if (! Schema::hasTable('horarioMateria') || ! Schema::hasTable('gradoMateria')) {
                return response()->json([]);
            }

            $rows = DB::table('horarioMateria as hm')
                ->join('gradoMateria as gm', 'hm.idGradoMateria', '=', 'gm.id')
                ->join('materia as m', 'gm.idMateria', '=', 'm.id')
                ->leftJoin('materia as comp', 'm.idMateriaPadre', '=', 'comp.id')
                ->where('hm.idFicha', $idFicha)
                ->whereNotNull('m.idMateriaPadre')
                ->where('m.idMateriaPadre', '>', 0)
                ->select(
                    'm.id',
                    'm.nombreMateria',
                    'm.codigo',
                    'm.idMateriaPadre',
                    'comp.nombreMateria as competenciaNombre',
                    'comp.codigo as competenciaCodigo'
                )
                ->distinct()
                ->get();

            $mapped = $rows->map(static function ($r) {
                $numero = Materia::numeroOrdenRap($r->codigo ?? null, $r->nombreMateria ?? null);

                return [
                    'id' => (int) $r->id,
                    'nombreMateria' => $r->nombreMateria,
                    'codigo' => $r->codigo,
                    'idCompetencia' => $r->idMateriaPadre ? (int) $r->idMateriaPadre : null,
                    'competenciaNombre' => $r->competenciaNombre,
                    'competenciaCodigo' => $r->competenciaCodigo,
                    'numeroRap' => $numero === PHP_INT_MAX ? null : $numero,
                ];
            })->sortBy([
                fn ($r) => $r['numeroRap'] ?? PHP_INT_MAX,
                fn ($r) => (string) ($r['codigo'] ?? ''),
                fn ($r) => (string) ($r['nombreMateria'] ?? ''),
            ])->values();

            return response()->json($mapped);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Mueve una actividad existente a otro RAP (en este modelo el RAP es `actividades.idMateria`).
     * No crea registros nuevos ni duplica la actividad; actualiza `planeacionActividades` cuando aplica la misma planeación que la clase.
     */
    public function moverActividadRap(Request $request, int $idActividad): JsonResponse
    {
        $idOrigen = 0;
        $idMateriaDestino = 0;
        $idFicha = 0;
        try {
            $validated = $request->validate([
                'idFicha' => 'required|integer|exists:ficha,id',
                'id_horario_materia' => 'sometimes|nullable|integer',
                'idRapDestino' => 'sometimes|nullable|exists:materia,id',
                'idMateriaDestino' => 'sometimes|nullable|exists:materia,id',
            ]);

            $idMateriaDestino = isset($validated['idRapDestino']) ? (int) $validated['idRapDestino'] : ((int) ($validated['idMateriaDestino'] ?? 0));
            if ($idMateriaDestino <= 0) {
                throw ValidationException::withMessages([
                    'idRapDestino' => ['Debe enviar idRapDestino o idMateriaDestino válido.'],
                ]);
            }

            $idCompany = KeyUtil::idCompany();
            $actividad = Actividad::query()->findOrFail($idActividad);

            if ((int) ($actividad->idCompany ?? 0) !== (int) $idCompany) {
                return response()->json(['error' => 'No autorizado para modificar esta actividad'], 403);
            }

            $idFicha = (int) $validated['idFicha'];
            $idHm = isset($validated['id_horario_materia']) ? (int) $validated['id_horario_materia'] : 0;

            if ($idHm > 0 && Schema::hasTable('horarioMateria')) {
                $hmPerteneceFicha = DB::table('horarioMateria')
                    ->where('id', $idHm)
                    ->where('idFicha', $idFicha)
                    ->exists();
                if (! $hmPerteneceFicha) {
                    return response()->json(['error' => self::ERROR_MOVER_RAP_FICHA_DISTINTA], 422);
                }
            }

            if (! self::actividadPerteneceOFichaContextoMovimiento($idActividad, $idFicha)) {
                return response()->json(['error' => self::ERROR_MOVER_RAP_FICHA_DISTINTA], 422);
            }

            if (! self::materiaEnHorariosDeFicha($idFicha, $idMateriaDestino)) {
                return response()->json(['error' => self::ERROR_MOVER_RAP_FICHA_DISTINTA], 422);
            }

            // Destino debe ser un RAP (hijo de competencia), nunca la competencia.
            $this->assertIdMateriaEsRap($idMateriaDestino);

            $idPlaneacion = self::resolverIdPlaneacionPorFichaYHorario($idFicha, $idHm > 0 ? $idHm : null);

            $idOrigen = (int) ($actividad->idMateria ?? 0);
            if ($idOrigen === $idMateriaDestino) {
                throw ValidationException::withMessages([
                    'idRapDestino' => ['El RAP destino debe ser distinto del RAP actual.'],
                ]);
            }

            DB::transaction(function () use ($actividad, $idActividad, $idOrigen, $idMateriaDestino, $idPlaneacion) {
                // Una actividad pertenece a un único RAP: actualizar la FK real.
                $actividad->idMateria = $idMateriaDestino;
                $actividad->save();

                if (! Schema::hasTable('planeacionActividades')) {
                    return;
                }

                // Todas las filas de planeación de esta actividad deben apuntar al RAP destino (sin duplicar).
                $queryPa = PlaneacionActividad::query()->where('idActividad', $idActividad);
                if ($idPlaneacion) {
                    $queryPa->where('idPlaneacion', $idPlaneacion);
                }

                $rows = $queryPa->get();
                if ($rows->isEmpty() && $idPlaneacion) {
                    // Si hay planeación de la ficha pero aún no hay vínculo, no se crea uno nuevo aquí:
                    // el movimiento solo reasigna; la asignación a aprendices es otro flujo.
                    return;
                }

                $byPlaneacion = $rows->groupBy(fn ($r) => (int) ($r->idPlaneacion ?? 0));
                foreach ($byPlaneacion as $pid => $group) {
                    if ((int) $pid <= 0) {
                        continue;
                    }

                    $withNew = $group->filter(fn ($r) => (int) ($r->idMateria ?? 0) === $idMateriaDestino)->values();
                    $withOld = $group->filter(fn ($r) => (int) ($r->idMateria ?? 0) === $idOrigen)->values();
                    $others = $group->filter(function ($r) use ($idOrigen, $idMateriaDestino) {
                        $m = (int) ($r->idMateria ?? 0);

                        return $m !== $idOrigen && $m !== $idMateriaDestino;
                    })->values();

                    if ($withNew->isNotEmpty()) {
                        // Ya existe vínculo al destino: eliminar origen y cualquier resto duplicado.
                        foreach ($withOld as $stale) {
                            $stale->delete();
                        }
                        foreach ($others as $stale) {
                            $stale->delete();
                        }
                        foreach ($withNew->slice(1) as $dup) {
                            $dup->delete();
                        }
                    } elseif ($withOld->isNotEmpty()) {
                        $first = $withOld->first();
                        $first->idMateria = $idMateriaDestino;
                        $first->save();
                        foreach ($withOld->slice(1) as $dup) {
                            $dup->delete();
                        }
                        foreach ($others as $stale) {
                            $stale->delete();
                        }
                    } else {
                        // Filas con otro idMateria: reasignar la primera y limpiar el resto.
                        $first = $group->first();
                        if ($first) {
                            $first->idMateria = $idMateriaDestino;
                            $first->save();
                            foreach ($group->slice(1) as $dup) {
                                $dup->delete();
                            }
                        }
                    }
                }

                // Seguridad: cualquier fila residual de esta actividad fuera del destino se actualiza o elimina.
                PlaneacionActividad::query()
                    ->where('idActividad', $idActividad)
                    ->when($idPlaneacion, fn ($q) => $q->where('idPlaneacion', $idPlaneacion))
                    ->where('idMateria', '!=', $idMateriaDestino)
                    ->update(['idMateria' => $idMateriaDestino]);

                // Deduplicar (idActividad, idPlaneacion, idMateria) tras el UPDATE masivo.
                $dedupe = PlaneacionActividad::query()
                    ->where('idActividad', $idActividad)
                    ->when($idPlaneacion, fn ($q) => $q->where('idPlaneacion', $idPlaneacion))
                    ->orderBy('id')
                    ->get()
                    ->groupBy(fn ($r) => (int) ($r->idPlaneacion ?? 0).'-'.(int) ($r->idMateria ?? 0));

                foreach ($dedupe as $group) {
                    foreach ($group->slice(1) as $dup) {
                        $dup->delete();
                    }
                }
            });

            DiagnosticoActividadesRapHistoricas::logMovimientoRap(
                $idActividad,
                $idOrigen,
                $idMateriaDestino,
                $idFicha,
                true
            );

            return response()->json([
                'message' => 'Actividad movida correctamente al RAP seleccionado.',
                'idActividad' => $actividad->id,
                'idMateria' => $idMateriaDestino,
            ]);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            DiagnosticoActividadesRapHistoricas::logMovimientoRap(
                $idActividad,
                $idOrigen,
                $idMateriaDestino,
                $idFicha,
                false,
                $e->getMessage()
            );
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Impide mover usando idFicha de una clase cuando la actividad solo existe en planeación/asignaciones de otra ficha,
     * salvo actividad de banco (sin planeacionActividades ni calificacionActividad en BD).
     */
    private static function actividadPerteneceOFichaContextoMovimiento(int $idActividad, int $idFicha): bool
    {
        if (self::tieneCalificacionActividadEnFicha($idActividad, $idFicha)) {
            return true;
        }

        $idsPlanesFicha = self::idsPlaneacionesDeContratosHorariosDeFicha($idFicha);
        if ($idsPlanesFicha !== [] && Schema::hasTable('planeacionActividades')) {
            if (PlaneacionActividad::query()
                ->where('idActividad', $idActividad)
                ->whereIn('idPlaneacion', $idsPlanesFicha)
                ->exists()) {
                return true;
            }
        }

        $algoPlaneacionGlobal = Schema::hasTable('planeacionActividades')
            && PlaneacionActividad::query()->where('idActividad', $idActividad)->exists();
        $algoCalificacionGlobal = Schema::hasTable('calificacionActividad')
            && DB::table('calificacionActividad')->where('idActividad', $idActividad)->exists();

        return ! $algoPlaneacionGlobal && ! $algoCalificacionGlobal;
    }

    /** @return list<int> */
    private static function idsPlaneacionesDeContratosHorariosDeFicha(int $idFicha): array
    {
        if (! Schema::hasTable('planeacion') || ! Schema::hasTable('horarioMateria')) {
            return [];
        }
        if (! Schema::hasColumn('horarioMateria', 'idContrato') || ! Schema::hasColumn('planeacion', 'idContrato')) {
            return [];
        }

        $idsContrato = DB::table('horarioMateria')
            ->where('idFicha', $idFicha)
            ->whereNotNull('idContrato')
            ->distinct()
            ->pluck('idContrato');
        if ($idsContrato->isEmpty()) {
            return [];
        }

        return DB::table('planeacion')
            ->whereIn('idContrato', $idsContrato->all())
            ->pluck('id')
            ->map(fn ($pid) => (int) $pid)
            ->unique()
            ->values()
            ->all();
    }

    private static function tieneCalificacionActividadEnFicha(int $idActividad, int $idFicha): bool
    {
        if (! Schema::hasTable('calificacionActividad')) {
            return false;
        }

        [$tableMa, $colFicha] = self::resolverTablaYColumnaFichaMatriculaAcademica();
        if (! $colFicha) {
            return false;
        }

        return DB::table('calificacionActividad as ca')
            ->join($tableMa.' as ma', 'ca.idAMartriculaAcademica', '=', 'ma.id')
            ->where('ca.idActividad', $idActividad)
            ->where('ma.'.$colFicha, $idFicha)
            ->exists();
    }

    /** @return array{0:string,1:?string} [tabla, columna_ficha|null] */
    private static function resolverTablaYColumnaFichaMatriculaAcademica(): array
    {
        $tableMa = Schema::hasTable('matriculaAcademica') ? 'matriculaAcademica' : null;
        if (! $tableMa && Schema::hasTable('matriculaacademica')) {
            $tableMa = 'matriculaacademica';
        }
        if (! $tableMa) {
            return ['matriculaAcademica', null];
        }
        $colFicha = Schema::hasColumn($tableMa, 'idFicha')
            ? 'idFicha'
            : (Schema::hasColumn($tableMa, 'idAsignacionPeriodoProgramaJornada') ? 'idAsignacionPeriodoProgramaJornada' : null);

        return [$tableMa, $colFicha];
    }

    /** @internal */
    private static function materiaEnHorariosDeFicha(int $idFicha, int $idMateria): bool
    {
        if (! Schema::hasTable('horarioMateria') || ! Schema::hasTable('gradoMateria')) {
            return false;
        }

        return DB::table('horarioMateria as hm')
            ->join('gradoMateria as gm', 'hm.idGradoMateria', '=', 'gm.id')
            ->where('hm.idFicha', $idFicha)
            ->where('gm.idMateria', $idMateria)
            ->exists();
    }

    /**
     * Garantiza que idMateria sea un RAP (tiene competencia padre).
     * Las actividades no pueden asociarse directamente a una competencia.
     */
    private function assertIdMateriaEsRap(int $idMateria): void
    {
        if ($idMateria <= 0) {
            throw ValidationException::withMessages([
                'idMateria' => ['Debe indicar un RAP válido.'],
            ]);
        }

        $materia = Materia::query()->find($idMateria);
        if (! $materia) {
            throw ValidationException::withMessages([
                'idMateria' => ['El RAP indicado no existe.'],
            ]);
        }

        if (! $materia->esRap()) {
            throw ValidationException::withMessages([
                'idMateria' => ['Las actividades pertenecen a un RAP, no a una competencia. Seleccione un RAP.'],
                'idRapDestino' => ['El destino debe ser un RAP, no una competencia.'],
            ]);
        }
    }

    /** Replica la detección de planeación en `planeacionActividadesPorFicha`. */
    private static function resolverIdPlaneacionPorFichaYHorario(int $idFicha, ?int $idHorarioMateria): ?int
    {
        if (! Schema::hasTable('planeacion')) {
            return null;
        }

        $idContrato = null;
        if (Schema::hasTable('horarioMateria') && Schema::hasColumn('horarioMateria', 'idContrato')) {
            $horario = null;
            if ($idHorarioMateria !== null && $idHorarioMateria > 0) {
                $horario = DB::table('horarioMateria')
                    ->where('id', $idHorarioMateria)
                    ->where('idFicha', $idFicha)
                    ->whereNotNull('idContrato')
                    ->first();
            }
            if (! $horario) {
                $horario = DB::table('horarioMateria')
                    ->where('idFicha', $idFicha)
                    ->whereNotNull('idContrato')
                    ->orderBy('id')
                    ->first();
            }
            $idContrato = $horario->idContrato ?? null;
        }

        if (! $idContrato || ! Schema::hasColumn('planeacion', 'idContrato')) {
            return null;
        }

        $planeacion = DB::table('planeacion')->where('idContrato', $idContrato)->first();

        return $planeacion ? (int) $planeacion->id : null;
    }

    public function materialesApoyo(Request $request, int $idActividad): JsonResponse
    {
        try {
            if (! Schema::hasTable('asignacionMaterialApoyoActividad')) {
                return response()->json([]);
            }

            $actividad = Actividad::findOrFail($idActividad);
            $idFicha = $request->query('idFicha') ? (int) $request->query('idFicha') : null;
            $idRap = (int) $actividad->idMateria;
            $puedeMarcarBiblioteca = $idFicha > 0 && $idRap > 0 && Schema::hasTable((new MaterialApoyoRap())->getTable());

            $asigs = AsignacionMaterialApoyoActividad::query()
                ->where('idActividad', $idActividad)
                ->whereNotNull('idMaterialApoyo')
                ->get();

            $out = [];
            foreach ($asigs as $a) {
                $m = MaterialApoyoActividad::find($a->idMaterialApoyo);
                if ($m) {
                    $item = [
                        'id' => (int) $m->id,
                        'titulo' => $m->titulo,
                        'descripcion' => $m->descripcion,
                        'urlDocumento' => $m->urlDocumento,
                        'urlDocumentoUrl' => $this->publicUrl($m->urlDocumento),
                        'urlAdicional' => $m->urlAdicional,
                    ];
                    if ($puedeMarcarBiblioteca) {
                        $item['enBiblioteca'] = $this->materialActividadYaEnBiblioteca($m, $idFicha, $idRap);
                    }
                    $out[] = $item;
                }
            }

            return response()->json($out);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function storeMaterialApoyo(Request $request, int $idActividad): JsonResponse
    {
        try {
            $actividad = Actividad::findOrFail($idActividad);

            $validator = Validator::make($request->all(), array_merge([
                'titulo' => 'required|string|max:255',
                'descripcion' => 'nullable|string|max:3000',
                'urlAdicional' => 'nullable|string|max:500',
            ], $this->materialDocumentoFileRules(true)), $this->materialDocumentoValidationMessages());
            $validator->after(function ($v) use ($request) {
                if ($request->hasFile('documento')) {
                    $this->assertMaterialDocumentoFile($v, $request->file('documento'));
                }
            });
            $validator->validate();

            $path = null;
            if ($request->hasFile('documento')) {
                $file = $request->file('documento');
                $dir = "actividades/{$idActividad}/material-apoyo";
                if (! Storage::disk('public')->exists($dir)) {
                    Storage::disk('public')->makeDirectory($dir, 0755, true);
                }
                $filename = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $file->getClientOriginalName());
                $path = $file->storeAs($dir, $filename, 'public');
            }

            if (! $path && empty($request->urlAdicional)) {
                return response()->json(['errors' => ['Se requiere documento o enlace']], 422);
            }

            $material = MaterialApoyoActividad::create([
                'titulo' => $request->titulo,
                'descripcion' => $request->descripcion ?? null,
                'urlDocumento' => $path,
                'urlAdicional' => $request->urlAdicional ? trim((string) $request->urlAdicional) : null,
                'idMateria' => $actividad->idMateria,
            ]);

            AsignacionMaterialApoyoActividad::create([
                'idActividad' => $idActividad,
                'idMaterialApoyo' => $material->id,
            ]);

            return response()->json([
                'id' => (int) $material->id,
                'titulo' => $material->titulo,
                'descripcion' => $material->descripcion,
                'urlDocumento' => $material->urlDocumento,
                'urlDocumentoUrl' => $this->publicUrl($material->urlDocumento),
                'urlAdicional' => $material->urlAdicional,
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['error' => 'Material o ficha no encontrado'], 404);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function destroyMaterialApoyo(int $idActividad, int $idMaterialApoyo): JsonResponse
    {
        try {
            if (! Schema::hasTable('asignacionMaterialApoyoActividad')) {
                return response()->json(['error' => 'No disponible'], 503);
            }

            Actividad::findOrFail($idActividad);

            $asig = AsignacionMaterialApoyoActividad::query()
                ->where('idActividad', $idActividad)
                ->where('idMaterialApoyo', $idMaterialApoyo)
                ->firstOrFail();

            $idMat = (int) $asig->idMaterialApoyo;
            $asig->delete();

            $otros = AsignacionMaterialApoyoActividad::where('idMaterialApoyo', $idMat)->count();
            if ($otros === 0) {
                $m = MaterialApoyoActividad::find($idMat);
                if ($m) {
                    if ($m->urlDocumento && Storage::disk('public')->exists($m->urlDocumento)) {
                        Storage::disk('public')->delete($m->urlDocumento);
                    }
                    $m->delete();
                }
            }

            return response()->json(['message' => 'Material de apoyo eliminado de la actividad']);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['error' => 'No encontrado'], 404);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Agrega un material de apoyo de actividad a la Biblioteca del Conocimiento (materialApoyoRap).
     * El material permanece en la actividad; se crea una copia/registro en biblioteca.
     */
    public function moverMaterialApoyoBiblioteca(Request $request, int $idActividad, int $idMaterialApoyo): JsonResponse
    {
        try {
            if (! Schema::hasTable('asignacionMaterialApoyoActividad')
                || ! Schema::hasTable((new MaterialApoyoRap())->getTable())) {
                return response()->json(['error' => 'Biblioteca de conocimiento no disponible.'], 503);
            }

            $validated = Validator::make($request->all(), [
                'idFicha' => 'required|integer|exists:ficha,id',
            ])->validate();

            $idFicha = (int) $validated['idFicha'];
            Ficha::findOrFail($idFicha);

            $actividad = Actividad::findOrFail($idActividad);
            $idRap = (int) $actividad->idMateria;
            if ($idRap <= 0) {
                return response()->json(['error' => 'La actividad no tiene un RAP asociado.'], 422);
            }

            Materia::findOrFail($idRap);

            AsignacionMaterialApoyoActividad::query()
                ->where('idActividad', $idActividad)
                ->where('idMaterialApoyo', $idMaterialApoyo)
                ->firstOrFail();

            $material = MaterialApoyoActividad::findOrFail($idMaterialApoyo);

            if (! $material->urlDocumento && empty($material->urlAdicional)) {
                return response()->json(['error' => 'El material no tiene documento ni enlace para agregar a biblioteca.'], 422);
            }

            if ($this->materialActividadYaEnBiblioteca($material, $idFicha, $idRap)) {
                return response()->json([
                    'error' => 'Este material ya se encuentra en la Biblioteca del Conocimiento.',
                ], 409);
            }

            $user = KeyUtil::user();
            $idPersonaCreador = $user?->idpersona ? (int) $user->idpersona : null;

            DB::beginTransaction();

            $urlDocumentoBiblioteca = $this->copiarArchivoMaterialActividadABiblioteca($material->urlDocumento);

            $payloadBiblioteca = [
                'titulo' => $material->titulo,
                'descripcion' => $material->descripcion,
                'urlDocumento' => $urlDocumentoBiblioteca,
                'urlAdicional' => $material->urlAdicional ? trim((string) $material->urlAdicional) : null,
                'urlVideo' => null,
                'idFicha' => $idFicha,
                'idMateria' => $idRap,
                'idRap' => $idRap,
                'idPersona' => $idPersonaCreador,
            ];

            $tablaRap = (new MaterialApoyoRap())->getTable();
            if (Schema::hasColumn($tablaRap, 'activo')) {
                $payloadBiblioteca['activo'] = true;
            }

            $materialBiblioteca = MaterialApoyoRap::create($payloadBiblioteca);

            DB::commit();

            return response()->json([
                'message' => 'Material agregado correctamente a la Biblioteca del Conocimiento.',
                'data' => [
                    'idBiblioteca' => (int) $materialBiblioteca->id,
                    'idActividad' => $idActividad,
                    'idMaterialActividad' => (int) $material->id,
                    'idRap' => $idRap,
                    'idFicha' => $idFicha,
                    'enBiblioteca' => true,
                ],
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();

            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();

            return response()->json(['error' => 'Material o actividad no encontrado'], 404);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Determina si un material de actividad ya fue agregado a biblioteca (misma ficha + RAP + metadatos).
     */
    private function materialActividadYaEnBiblioteca(MaterialApoyoActividad $material, int $idFicha, int $idRap): bool
    {
        $query = MaterialApoyoRap::query()
            ->where('idFicha', $idFicha)
            ->where('idRap', $idRap)
            ->where('titulo', $material->titulo);

        $descripcion = $material->descripcion;
        if ($descripcion === null || trim((string) $descripcion) === '') {
            $query->where(function ($q) {
                $q->whereNull('descripcion')->orWhere('descripcion', '');
            });
        } else {
            $query->where('descripcion', $descripcion);
        }

        $urlAdicional = $material->urlAdicional ? trim((string) $material->urlAdicional) : null;
        if ($urlAdicional) {
            $query->where('urlAdicional', $urlAdicional);
        } else {
            $query->where(function ($q) {
                $q->whereNull('urlAdicional')->orWhere('urlAdicional', '');
            });
        }

        if ($material->urlDocumento) {
            $path = (string) $material->urlDocumento;
            if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
                $query->where('urlDocumento', $path);
            } else {
                $basename = basename($path);
                $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $basename);
                $query->where('urlDocumento', 'like', '%' . $escaped);
            }
        } else {
            $query->where(function ($q) {
                $q->whereNull('urlDocumento')->orWhere('urlDocumento', '');
            });
        }

        return $query->exists();
    }

    /**
     * Copia un archivo local de actividad al directorio de biblioteca; URLs externas se conservan.
     * El archivo original en actividades/{id}/material-apoyo/ no se elimina.
     */
    private function copiarArchivoMaterialActividadABiblioteca(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        if (! Storage::disk('public')->exists($path)) {
            return $path;
        }

        $dir = 'material-apoyo-rap/documentos';
        if (! Storage::disk('public')->exists($dir)) {
            Storage::disk('public')->makeDirectory($dir, 0755, true);
        }

        $basename = basename($path);
        $newPath = $dir . '/' . time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $basename);

        Storage::disk('public')->copy($path, $newPath);

        return $newPath;
    }

    public function storeCuestionario(Request $request): JsonResponse
    {
        try {
            $preguntasData = is_string($request->preguntas) ? json_decode($request->preguntas, true) : $request->preguntas;
            $request->merge(['preguntas' => $preguntasData ?? []]);

            $request->validate([
                'titulo' => 'required|string|max:500',
                'clasificacion' => 'nullable|string|max:255',
                'descripcion' => 'nullable|string',
                'idMateria' => 'required|exists:materia,id',
                'preguntas' => 'required|array|min:1',
                'preguntas.*.tipo' => 'required|in:Párrafo,Varias opciones',
                'preguntas.*.titulo' => 'required|string|max:1000',
            ]);

            $user = KeyUtil::user();
            $idCompany = KeyUtil::idCompany();

            $this->assertIdMateriaEsRap((int) $request->idMateria);

            $actividad = Actividad::create([
                'tituloActividad' => $request->titulo,
                'descripcionActividad' => $request->descripcion ?? null,
                'pathDocumentoActividad' => null,
                'autor' => $request->clasificacion ?? null,
                'tipoActividad' => 'cuestionario',
                'idMateria' => $request->idMateria,
                'idEstado' => 1,
                'idCompany' => $idCompany,
                'idPersona' => $user?->idpersona ?? null,
                'idClasificacion' => null,
                'estrategia' => 'Cuestionario',
                'entregables' => 'Respuestas al cuestionario',
            ]);

            DiagnosticoActividadesRapHistoricas::logCreacionActividad(
                (int) $actividad->id,
                (int) $actividad->idMateria,
                'storeCuestionario'
            );

            $tiposPregunta = TipoPregunta::pluck('id', 'tipoPregunta')->toArray();
            if (empty($tiposPregunta)) {
                TipoPregunta::firstOrCreate(['tipoPregunta' => 'Párrafo'], ['tipoPregunta' => 'Párrafo']);
                TipoPregunta::firstOrCreate(['tipoPregunta' => 'Varias opciones'], ['tipoPregunta' => 'Varias opciones']);
                $tiposPregunta = TipoPregunta::pluck('id', 'tipoPregunta')->toArray();
            }

            foreach ($request->preguntas as $i => $p) {
                $idTipo = $tiposPregunta[$p['tipo']] ?? $tiposPregunta['Párrafo'] ?? null;
                if ($idTipo === null) {
                    throw new \InvalidArgumentException("Tipo de pregunta '{$p['tipo']}' no encontrado. Ejecute: php artisan migrate");
                }
                $urlDoc = null;

                $fileKey = "foto_pregunta_{$i}";
                if ($request->hasFile($fileKey)) {
                    $file = $request->file($fileKey);
                    $dir = "cuestionarios/{$actividad->id}/preguntas";
                    if (!Storage::disk('public')->exists($dir)) {
                        Storage::disk('public')->makeDirectory($dir, 0755, true);
                    }
                    $filename = time() . '_' . $i . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $file->getClientOriginalName());
                    $urlDoc = $file->storeAs($dir, $filename, 'public');
                }

                $pregunta = Pregunta::create([
                    'descripcion' => $p['titulo'],
                    'puntaje' => 1,
                    'idTipoPregunta' => $idTipo,
                    'idActividad' => $actividad->id,
                    'urlDocumento' => $urlDoc,
                ]);

                if (($p['tipo'] ?? '') === 'Varias opciones' && !empty($p['opciones'])) {
                    foreach ($p['opciones'] as $op) {
                        if (!empty(trim($op['texto'] ?? ''))) {
                            Respuesta::create([
                                'idPregunta' => $pregunta->id,
                                'descripcionRespuesta' => $op['texto'],
                                'chkCorrecta' => (bool)($op['esCorrecta'] ?? false),
                                'puntaje' => ($op['esCorrecta'] ?? false) ? 1 : 0,
                            ]);
                        }
                    }
                }
            }

            return response()->json($actividad->load(['materia', 'estado', 'persona']), 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function updateCuestionario(Request $request, int $id): JsonResponse
    {
        try {
            $actividad = Actividad::findOrFail($id);
            if ($actividad->tipoActividad !== 'cuestionario') {
                return response()->json(['error' => 'La actividad no es un cuestionario'], 422);
            }

            $preguntasData = is_string($request->preguntas) ? json_decode($request->preguntas, true) : $request->preguntas;
            $request->merge(['preguntas' => $preguntasData ?? []]);

            $request->validate([
                'titulo' => 'required|string|max:500',
                'clasificacion' => 'nullable|string|max:255',
                'descripcion' => 'nullable|string',
                'idMateria' => 'required|exists:materia,id',
                'preguntas' => 'required|array|min:1',
                'preguntas.*.tipo' => 'required|in:Párrafo,Varias opciones',
                'preguntas.*.titulo' => 'required|string|max:1000',
            ]);

            $actividad->update([
                'tituloActividad' => $request->titulo,
                'descripcionActividad' => $request->descripcion ?? null,
                'autor' => $request->clasificacion ?? null,
                'idMateria' => $request->idMateria,
            ]);

            $idsPreguntas = $actividad->preguntas()->pluck('id')->toArray();
            if (!empty($idsPreguntas) && \Illuminate\Support\Facades\Schema::hasTable('respuesta_cuestionarios')) {
                \Illuminate\Support\Facades\DB::table('respuesta_cuestionarios')->whereIn('idPregunta', $idsPreguntas)->delete();
            }
            foreach ($actividad->preguntas as $preg) {
                Respuesta::where('idPregunta', $preg->id)->delete();
            }
            $actividad->preguntas()->delete();

            $tiposPregunta = TipoPregunta::pluck('id', 'tipoPregunta')->toArray();
            if (empty($tiposPregunta)) {
                TipoPregunta::firstOrCreate(['tipoPregunta' => 'Párrafo'], ['tipoPregunta' => 'Párrafo']);
                TipoPregunta::firstOrCreate(['tipoPregunta' => 'Varias opciones'], ['tipoPregunta' => 'Varias opciones']);
                $tiposPregunta = TipoPregunta::pluck('id', 'tipoPregunta')->toArray();
            }

            foreach ($request->preguntas as $i => $p) {
                $idTipo = $tiposPregunta[$p['tipo']] ?? $tiposPregunta['Párrafo'] ?? null;
                if ($idTipo === null) {
                    throw new \InvalidArgumentException("Tipo de pregunta '{$p['tipo']}' no encontrado. Ejecute: php artisan migrate");
                }
                $urlDoc = null;

                $fileKey = "foto_pregunta_{$i}";
                if ($request->hasFile($fileKey)) {
                    $file = $request->file($fileKey);
                    $dir = "cuestionarios/{$actividad->id}/preguntas";
                    if (!Storage::disk('public')->exists($dir)) {
                        Storage::disk('public')->makeDirectory($dir, 0755, true);
                    }
                    $filename = time() . '_' . $i . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $file->getClientOriginalName());
                    $urlDoc = $file->storeAs($dir, $filename, 'public');
                }

                $pregunta = Pregunta::create([
                    'descripcion' => $p['titulo'],
                    'puntaje' => 1,
                    'idTipoPregunta' => $idTipo,
                    'idActividad' => $actividad->id,
                    'urlDocumento' => $urlDoc,
                ]);

                if (($p['tipo'] ?? '') === 'Varias opciones' && !empty($p['opciones'])) {
                    foreach ($p['opciones'] as $op) {
                        if (!empty(trim($op['texto'] ?? ''))) {
                            Respuesta::create([
                                'idPregunta' => $pregunta->id,
                                'descripcionRespuesta' => $op['texto'],
                                'chkCorrecta' => (bool)($op['esCorrecta'] ?? false),
                                'puntaje' => ($op['esCorrecta'] ?? false) ? 1 : 0,
                            ]);
                        }
                    }
                }
            }

            return response()->json($actividad->load(['materia', 'estado', 'persona', 'preguntas.respuestas']));
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Al reenviar evidencia tras una solicitud de corrección, se elimina el prefijo interno pero se conserva el texto de observación.
     */
    private function comentarioDocenteSinMarcaCorreccion(?string $comentarioDocente): ?string
    {
        $t = trim((string) $comentarioDocente);
        if ($t === '') {
            return null;
        }
        $m = CalificacionActividadController::MARCA_SOLICITUD_CORRECCION;
        if (! str_starts_with($t, $m)) {
            return $comentarioDocente;
        }
        $rest = ltrim(substr($t, strlen($m)), "\r\n ");

        return $rest !== '' ? $rest : null;
    }

    private function resolverEstadoActividadAprendiz(object $row, bool $tieneRespuestasCuestionario = false): string
    {
        $calificacion = trim((string) ($row->calificacionNumerica ?? ''));
        $archivo = trim((string) ($row->archivoEntrega ?? $row->archivo ?? ''));
        $tz = config('app.timezone');
        $fechaFinal = $row->fechaFinal ? \Carbon\Carbon::parse((string) $row->fechaFinal, $tz) : null;
        $esCuestionario = (strtolower(trim($row->tipoActividad ?? '')) === 'cuestionario');

        if ($calificacion !== '') {
            return 'CALIFICADO';
        }

        if (CalificacionActividadController::comentarioIndicaCorreccionPendiente($row->ComentarioDocente ?? null)) {
            return 'CORRECCION_SOLICITADA';
        }

        if ($esCuestionario) {
            if ($tieneRespuestasCuestionario) {
                return 'POR_EVALUAR';
            }
        } else {
            if ($archivo !== '') {
                return 'POR_EVALUAR';
            }
        }

        if ($fechaFinal && now($tz)->greaterThan($fechaFinal)) {
            return 'SIN_ENTREGAR';
        }

        return 'PENDIENTE';
    }

    private function publicUrl(?string $path): ?string
    {
        if (!$path) {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return Storage::disk('public')->url($path);
    }

    /**
     * Foto `persona.rutaFoto`: en BD suele guardarse como "/storage/persona/..."; Person usa {@see url()},
     * mientras que {@see publicUrl()} con Storage puede generar URL incorrecta o duplicar prefijos.
     */
    private function resolvePersonaPublicFotoUrl(?string $path): ?string
    {
        if ($path === null) {
            return null;
        }
        $path = trim($path);
        if ($path === '' || strcasecmp($path, 'null') === 0) {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return url($path);
    }

    /**
     * Calificación automática para preguntas de selección múltiple (Varias opciones).
     * Correctas = valor proporcional, incorrectas = 0.
     * Ejemplo: 4 correctas de 5 preguntas → nota = (4/5)*5 = 4.0
     */
    private function calificarCuestionarioAutomatico(int $idCalificacionActividad, int $idActividad, string $tblRc): void
    {
        $preguntasVariasOpciones = DB::table('preguntas as p')
            ->join('tipoPreguntas as tp', 'p.idTipoPregunta', '=', 'tp.id')
            ->where('p.idActividad', $idActividad)
            ->whereRaw("LOWER(TRIM(tp.tipoPregunta)) = 'varias opciones'")
            ->pluck('p.id');

        $idsAsignados = CuestionarioAsignacionUtil::idsParaCalificacion($idCalificacionActividad);
        if ($idsAsignados !== null) {
            $setAsignados = array_flip($idsAsignados);
            $preguntasVariasOpciones = $preguntasVariasOpciones->filter(fn ($id) => isset($setAsignados[(int) $id]))->values();
        }

        if ($preguntasVariasOpciones->isEmpty()) {
            return;
        }

        $totalPreguntas = $preguntasVariasOpciones->count();
        $respuestasAlumno = DB::table($tblRc)
            ->where('idCalificacion', $idCalificacionActividad)
            ->whereIn('idPregunta', $preguntasVariasOpciones->all())
            ->get()
            ->filter(fn ($row) => !CuestionarioAsignacionUtil::esMarcador($row))
            ->keyBy('idPregunta');

        $correctas = 0;
        $totalRespondidas = 0;

        foreach ($preguntasVariasOpciones as $idPregunta) {
            $resp = $respuestasAlumno->get($idPregunta);
            if (!$resp || !$resp->idRespuesta) {
                continue;
            }

            $respuestaCorrecta = DB::table('respuestas')
                ->where('id', $resp->idRespuesta)
                ->value('chkCorrecta');

            $totalRespondidas++;
            $esCorrecta = (bool) ($respuestaCorrecta ?? false);
            if ($esCorrecta) {
                $correctas++;
            }

            // Marcar cada respuesta MC como calificada (puntaje proporcional 0..5)
            $puntajePregunta = $esCorrecta && $totalPreguntas > 0
                ? round(5 / $totalPreguntas, 2)
                : 0;
            DB::table($tblRc)
                ->where('id', $resp->id)
                ->update([
                    'calificado' => true,
                    'puntaje' => $puntajePregunta,
                ]);
        }

        if ($totalRespondidas === 0) {
            $notaFinal = 1;
        } elseif ($correctas === 0) {
            $notaFinal = 1;
        } else {
            $notaFinal = round(($correctas / $totalPreguntas) * 5, 1);
            $notaFinal = min(5, max(0, $notaFinal));
        }

        DB::table('calificacionActividad')
            ->where('id', $idCalificacionActividad)
            ->update([
                'calificacionNumerica' => (string) $notaFinal,
                'fechaCalificacion' => now(),
                'updated_at' => now(),
            ]);
    }
}
