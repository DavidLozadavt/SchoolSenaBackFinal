<?php

namespace App\Http\Controllers\gestion_actividades;

use App\Http\Controllers\Controller;
use App\Models\Actividad;
use App\Models\TipoActividad;
use App\Models\ClasificacionActividad;
use App\Models\PlaneacionActividad;
use App\Models\Materia;
use App\Models\Status;
use App\Models\AsignacionMaterialApoyoActividad;
use App\Models\MaterialApoyoActividad;
use App\Models\MaterialApoyoRap;
use App\Models\Pregunta;
use App\Models\TipoPregunta;
use App\Models\Respuesta;
use App\Util\KeyUtil;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class ActividadController extends Controller
{
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

            $query = DB::table($tablaMaterial . ' as mar')
                ->leftJoin('materia as mat', 'mar.idMateria', '=', 'mat.id')
                ->leftJoin('materia as rap', 'mar.idRap', '=', 'rap.id')
                ->leftJoin('ficha as f', 'mar.idFicha', '=', 'f.id')
                ->whereIn('mar.idFicha', $fichaIds->all());

            $idFicha = (int) $request->query('idFicha', 0);
            $idMateria = (int) $request->query('idMateria', 0);
            $idRap = (int) $request->query('idRap', 0);

            if ($idFicha > 0) {
                $query->where('mar.idFicha', $idFicha);
            }
            if ($idMateria > 0) {
                $query->where('mar.idMateria', $idMateria);
            }
            if ($idRap > 0) {
                $query->where('mar.idRap', $idRap);
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
                'mar.created_at',
                'mat.nombreMateria as materiaNombre',
                'rap.nombreMateria as rapNombre',
                'f.codigo as fichaCodigo',
            ];
            if (Schema::hasColumn($tablaMaterial, 'urlVideo')) {
                $selectCols[] = 'mar.urlVideo';
            }

            $rows = $query
                ->select($selectCols)
                ->orderByDesc('mar.id')
                ->get();

            $tieneColVideo = Schema::hasColumn($tablaMaterial, 'urlVideo');

            $data = $rows->map(function ($row) use ($tieneColVideo) {
                $idRapResolved = (int) $row->idRap;
                $urlVideo = $tieneColVideo ? ($row->urlVideo ?? null) : null;

                return [
                    'id' => (int) $row->id,
                    'titulo' => $row->titulo,
                    'descripcion' => $row->descripcion,
                    'urlDocumento' => $row->urlDocumento,
                    'urlDocumentoUrl' => $this->publicUrl($row->urlDocumento),
                    'urlAdicional' => $row->urlAdicional,
                    'urlVideo' => $urlVideo,
                    'urlVideoUrl' => $this->publicUrl($urlVideo),
                    'idMateria' => (int) $row->idMateria,
                    'materiaNombre' => $row->materiaNombre,
                    'idFicha' => (int) $row->idFicha,
                    'fichaCodigo' => $row->fichaCodigo,
                    'idRap' => $idRapResolved > 0 ? $idRapResolved : null,
                    'rapNombre' => $row->rapNombre ?: $row->materiaNombre,
                    'legacySinRap' => false,
                    'created_at' => $row->created_at,
                ];
            })->values();

            return response()->json($data);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
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
                    DB::raw($colFicha ? ('ma.' . $colFicha . ' as idFichaContext') : 'NULL as idFichaContext'),
                ])
                ->orderBy('ca.id')
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
                            'respuestas' => $p->respuestas ? $p->respuestas->map(fn ($r) => [
                                'id' => $r->id,
                                'descripcionRespuesta' => $r->descripcionRespuesta,
                                'chkCorrecta' => $r->chkCorrecta,
                            ])->values() : [],
                        ])->values();
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
                        ->select('idCalificacion')
                        ->distinct()
                        ->pluck('idCalificacion');
                }
            }

            $materialesPorRap = [];
            $tablaMar = (new MaterialApoyoRap())->getTable();
            if ($idsActividad->isNotEmpty() && Schema::hasTable($tablaMar)) {
                $fichaIds = $registros->pluck('idFichaContext')->filter()->map(fn ($v) => (int) $v)->unique()->values();
                $rapIds = $registros->pluck('idMateria')->filter()->map(fn ($v) => (int) $v)->unique()->values();

                if ($fichaIds->isNotEmpty() && $rapIds->isNotEmpty()) {
                    $qMatRap = MaterialApoyoRap::query()
                        ->whereIn('idFicha', $fichaIds->all())
                        ->whereIn('idRap', $rapIds->all());
                    $materialesRap = $qMatRap->orderByDesc('id')->get();

                    foreach ($materialesRap as $mat) {
                        $key = ((int) $mat->idFicha) . '_' . ((int) $mat->idRap);
                        $urlVid = Schema::hasColumn($tablaMar, 'urlVideo') ? ($mat->urlVideo ?? null) : null;
                        $materialesPorRap[$key][] = [
                            'id' => (int) $mat->id,
                            'titulo' => $mat->titulo,
                            'descripcion' => $mat->descripcion,
                            'urlDocumento' => $mat->urlDocumento,
                            'urlDocumentoUrl' => $this->publicUrl($mat->urlDocumento),
                            'urlAdicional' => $mat->urlAdicional,
                            'urlVideo' => $urlVid,
                            'urlVideoUrl' => $this->publicUrl($urlVid),
                        ];
                    }
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

                // Estado calculado solo por: fecha inicio, fecha límite y hora actual
                $now = now();
                $fechaVencida = false;
                $fechaInactiva = false;
                if ($row->fechaFinal) {
                    try {
                        $fechaFinal = \Carbon\Carbon::parse($row->fechaFinal);
                        $fechaVencida = $now->greaterThan($fechaFinal);
                    } catch (\Exception $e) {
                        $fechaVencida = false;
                    }
                }
                if ($row->fechaInicial) {
                    try {
                        $fechaInicial = \Carbon\Carbon::parse($row->fechaInicial);
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
                        'rutaFotoUrl' => $this->publicUrl($row->autorRutaFoto),
                    ],
                    'materialesApoyo' => $materialesPorRap[((int) ($row->idFichaContext ?? 0)) . '_' . ((int) ($row->idMateria ?? 0))] ?? [],
                    'estadoVisual' => $estadoVisual,
                    'fechaVencida' => $fechaVencida,
                    'fechaInactiva' => $fechaInactiva,
                    'puedeResponder' => (strtoupper(trim($row->estadoActividad ?? 'ACTIVO')) === 'ACTIVO')
                        && !$fechaVencida
                        && !$fechaInactiva
                        && in_array($estadoVisual, ['PENDIENTE', 'SIN_ENTREGAR'], true),
                    'estadoActividad' => $row->estadoActividad ?? 'ACTIVO',
                    'activa' => (strtoupper(trim($row->estadoActividad ?? 'ACTIVO')) === 'ACTIVO') && !$fechaVencida && !$fechaInactiva,
                    'esGrupal' => !empty($row->idGrupo),
                    'idGrupo' => $row->idGrupo,
                    'preguntas' => ($row->tipoActividad ?? '') === 'cuestionario' ? ($preguntasPorActividad[$row->idActividad] ?? []) : null,
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
             * - id_materia_clase: materia del horario (RAP). Se incluye la competencia (padre) y todos los RAP
             *   hijos de esa competencia, para listar actividades creadas en cualquier RAP de la misma competencia.
             * - id_programa: restringe a materias del programa vía gradoMateria / gradoPrograma.
             */
            $idMateriaClase = $request->query('id_materia_clase');
            $idPrograma = $request->query('id_programa');
            $idMateriaExacta = (int) $request->query('idMateria', 0);
            $idRapExacto = (int) $request->query('idRap', 0);
            $materiaIdsFilter = null;

            if ($idMateriaClase !== null && $idMateriaClase !== '') {
                $mc = (int) $idMateriaClase;
                if ($mc > 0) {
                    $mRow = Materia::query()->find($mc);
                    if ($mRow) {
                        $idCompetencia = ! empty($mRow->idMateriaPadre) ? (int) $mRow->idMateriaPadre : (int) $mRow->id;
                        $materiaIdsFilter = Materia::query()
                            ->where(function ($q) use ($idCompetencia) {
                                $q->where('id', $idCompetencia)
                                    ->orWhere('idMateriaPadre', $idCompetencia);
                            })
                            ->pluck('id')
                            ->map(fn ($id) => (int) $id)
                            ->unique()
                            ->values()
                            ->all();
                    } else {
                        $materiaIdsFilter = [];
                    }
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

            // Filtro estricto por materia/RAP (si llega en el request).
            // En este módulo el RAP se modela en materia.id (actividad.idMateria).
            if ($idRapExacto > 0) {
                $query->where('idMateria', $idRapExacto);
            } elseif ($idMateriaExacta > 0) {
                $query->where('idMateria', $idMateriaExacta);
            }

            $actividades = $query->orderBy('id', 'asc')->get();
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

            $validated = $request->validate([
                'comentarioEstudiante' => 'nullable|string|max:3000',
                'archivo' => 'nullable|file|mimes:pdf,doc,docx,png,jpg,jpeg,zip,rar|max:10240',
            ]);

            $tableMa = Schema::hasTable('matriculaAcademica') ? 'matriculaAcademica' : 'matriculaacademica';

            $registro = DB::table('calificacionActividad as ca')
                ->join($tableMa . ' as ma', 'ca.idAMartriculaAcademica', '=', 'ma.id')
                ->join('matricula as m', 'ma.idMatricula', '=', 'm.id')
                ->join('actividades as a', 'ca.idActividad', '=', 'a.id')
                ->leftJoin('estado as e', 'a.idEstado', '=', 'e.id')
                ->where('ca.id', $idCalificacionActividad)
                ->where('m.idPersona', $idPersona)
                ->select('ca.id', 'ca.archivo', 'ca.fechaFinal', 'a.tipoActividad', 'e.estado as estadoActividad')
                ->first();

            if (!$registro) {
                return response()->json(['error' => 'Actividad no encontrada para este aprendiz'], 404);
            }

            if ($registro->fechaFinal && now()->greaterThan(\Carbon\Carbon::parse($registro->fechaFinal))) {
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

            DB::table('calificacionActividad')
                ->where('id', $idCalificacionActividad)
                ->update([
                    'ComentarioEstudiante' => $validated['comentarioEstudiante'] ?? null,
                    'archivo' => $archivoPath,
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

            if ($ca->fechaFinal && now()->greaterThan(\Carbon\Carbon::parse($ca->fechaFinal))) {
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

            $tblRc = Schema::hasTable('respuestaCuestionarios') ? 'respuestaCuestionarios' : (Schema::hasTable('respuesta_cuestionarios') ? 'respuesta_cuestionarios' : null);
            if (!$tblRc) {
                return response()->json(['error' => 'Tabla de respuestas de cuestionario no disponible'], 500);
            }

            foreach ($validated['respuestas'] as $r) {
                $idRespuesta = isset($r['idRespuesta']) && $r['idRespuesta'] ? (int) $r['idRespuesta'] : null;
                $respuestaTexto = trim($r['respuesta'] ?? '');
                if ($idRespuesta === null && $respuestaTexto === '') {
                    continue;
                }

                $existe = DB::table($tblRc)
                    ->where('idCalificacion', $idCalificacionActividad)
                    ->where('idPregunta', $r['idPregunta'])
                    ->exists();
                if ($existe) {
                    DB::table($tblRc)
                        ->where('idCalificacion', $idCalificacionActividad)
                        ->where('idPregunta', $r['idPregunta'])
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

            $actividad = Actividad::create($validated);
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

            $actividad->update($validated);
            return response()->json($actividad);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function show(int $id): JsonResponse
    {
        try {
            $actividad = Actividad::with(['materia', 'estado', 'clasificacion', 'persona'])->findOrFail($id);
            if ($actividad->tipoActividad === 'cuestionario') {
                $actividad->load(['preguntas.tipoPregunta', 'preguntas.respuestas']);
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
            $request->validate([
                'documento' => 'required|file|mimes:pdf,doc,docx|max:10240',
            ]);

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
            $request->validate([
                'documento' => 'required|file|mimes:pdf,doc,docx|max:10240',
            ]);

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
             * Si se envía id_materia_clase (RAP de la clase), limitar resultados a esa competencia y sus RAP hijos,
             * para no mezclar actividades de otro RAP aunque compartan planeación o ficha.
             */
            $mcPlaneacion = $request->query('id_materia_clase');
            if ($items->isNotEmpty() && $mcPlaneacion !== null && $mcPlaneacion !== '' && (int) $mcPlaneacion > 0) {
                $mRowP = Materia::query()->find((int) $mcPlaneacion);
                if ($mRowP) {
                    $idCompP = ! empty($mRowP->idMateriaPadre) ? (int) $mRowP->idMateriaPadre : (int) $mRowP->id;
                    $idsMateriaRap = Materia::query()
                        ->where(function ($q) use ($idCompP) {
                            $q->where('id', $idCompP)
                                ->orWhere('idMateriaPadre', $idCompP);
                        })
                        ->pluck('id')
                        ->map(fn ($mid) => (int) $mid)
                        ->unique()
                        ->values()
                        ->all();
                    $items = $items->filter(function ($item) use ($idsMateriaRap) {
                        $idM = (int) ($item->idMateria ?? (isset($item->actividad) ? ($item->actividad->idMateria ?? 0) : 0));

                        return $idM > 0 && in_array($idM, $idsMateriaRap, true);
                    })->values();
                }
            }

            // Filtro estricto por materia/RAP cuando el cliente lo envía explícitamente.
            $idMateriaExacta = (int) $request->query('idMateria', 0);
            $idRapExacto = (int) $request->query('idRap', 0);
            if ($items->isNotEmpty() && ($idMateriaExacta > 0 || $idRapExacto > 0)) {
                $idMateriaFiltro = $idRapExacto > 0 ? $idRapExacto : $idMateriaExacta;
                $items = $items->filter(function ($item) use ($idMateriaFiltro) {
                    $idM = (int) ($item->idMateria ?? (isset($item->actividad) ? ($item->actividad->idMateria ?? 0) : 0));
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
            return response()->json(['message' => 'Actividad quitada de la planeaci?n']);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function materialesApoyo(int $idActividad): JsonResponse
    {
        try {
            if (! Schema::hasTable('asignacionMaterialApoyoActividad')) {
                return response()->json([]);
            }

            Actividad::findOrFail($idActividad);

            $asigs = AsignacionMaterialApoyoActividad::query()
                ->where('idActividad', $idActividad)
                ->whereNotNull('idMaterialApoyo')
                ->get();

            $out = [];
            foreach ($asigs as $a) {
                $m = MaterialApoyoActividad::find($a->idMaterialApoyo);
                if ($m) {
                    $out[] = [
                        'id' => (int) $m->id,
                        'titulo' => $m->titulo,
                        'descripcion' => $m->descripcion,
                        'urlDocumento' => $m->urlDocumento,
                        'urlDocumentoUrl' => $this->publicUrl($m->urlDocumento),
                        'urlAdicional' => $m->urlAdicional,
                    ];
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

            $request->validate([
                'titulo' => 'required|string|max:255',
                'descripcion' => 'nullable|string|max:3000',
                'documento' => 'nullable|file|mimes:pdf|max:10240',
                'urlAdicional' => 'nullable|string|max:500',
            ]);

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
                return response()->json(['errors' => ['Se requiere documento PDF o enlace']], 422);
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

    private function resolverEstadoActividadAprendiz(object $row, bool $tieneRespuestasCuestionario = false): string
    {
        $calificacion = trim((string) ($row->calificacionNumerica ?? ''));
        $archivo = trim((string) ($row->archivoEntrega ?? $row->archivo ?? ''));
        $fechaFinal = $row->fechaFinal ? \Carbon\Carbon::parse($row->fechaFinal) : null;
        $esCuestionario = (strtolower(trim($row->tipoActividad ?? '')) === 'cuestionario');

        if ($calificacion !== '') {
            return 'CALIFICADO';
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

        if ($fechaFinal && now()->greaterThan($fechaFinal)) {
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

        if ($preguntasVariasOpciones->isEmpty()) {
            return;
        }

        $totalPreguntas = $preguntasVariasOpciones->count();
        $respuestasAlumno = DB::table($tblRc)
            ->where('idCalificacion', $idCalificacionActividad)
            ->whereIn('idPregunta', $preguntasVariasOpciones->all())
            ->get()
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
            if ($respuestaCorrecta ?? false) {
                $correctas++;
            }
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
