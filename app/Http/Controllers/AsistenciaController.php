<?php
namespace App\Http\Controllers;

use App\Models\Asistencia;
use App\Models\MatriculaAcademica;
use App\Models\JustificacionInasistencia;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class AsistenciaController extends Controller
{
    // Método eliminado para cumplir con la regla de no depender de la tabla sesiones
    // El cálculo ahora se basa exclusivamente en los registros existentes en la tabla asistencia.

    public function store(Request $request): JsonResponse
    {
        try {

            $request->validate([
                'idMatriculaAcademica' => 'required|exists:matriculaAcademica,id',
                'fecha' => 'required|date',
                'asistio' => 'required|boolean'
            ]);

            $asistencia = Asistencia::create([
                'idMatriculaAcademica' => $request->idMatriculaAcademica,
                'fecha' => $request->fecha,
                'asistio' => $request->asistio
            ]);

            return response()->json([
                'message' => 'Asistencia creada correctamente',
                'data' => $asistencia
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error general',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getEstadisticasAsistencia(Request $request): JsonResponse
    {
        try {

            $idFicha = $request->input('idFicha');

            if (!$idFicha) {
                return response()->json([
                    'message' => 'El idFicha es requerido'
                ], 400);
            }

            $matriculas = MatriculaAcademica::with([
                'matricula.person',
                'materia',
                'asistencias.sesionMateria',
            ])
                ->where('idFicha', $idFicha)
                ->get();

            if ($matriculas->isEmpty()) {
                return response()->json([
                    'message' => 'No hay registros'
                ], 404);
            }

            $aprendiz = $matriculas->first()->matricula->person;

            $countAsistencia = 0;
            $countFaltas = 0;
            $countJustificadas = 0;
            $materiaStats = [];

            // Eager load justificacion info
            $matriculas->load('asistencias.justificacion');

            foreach ($matriculas as $matricula) {

                // Cálculo basado exclusivamente en la tabla asistencia
                $asistidas = $matricula->asistencias->where('asistio', true)->count();
                $faltas = $matricula->asistencias->where('asistio', false)->count();

                // Identificar justificadas dentro de las inasistencias existentes
                $justificadas = $matricula->asistencias->where('asistio', false)->filter(function ($asistencia) {
                    return $asistencia->justificacion && $asistencia->justificacion->estado === 'APROBADO';
                })->count();

                $countAsistencia += $asistidas;
                // Para el reporte de estadísticas, restamos las justificadas de las faltas totales
                $faltasNetas = max(0, $faltas - $justificadas);
                $countFaltas += $faltasNetas;
                $countJustificadas += $justificadas;

                $totalSesionesMateria = $asistidas + $faltas;

                $materiaStats[] = [
                    'idMateria' => $matricula->idMateria,
                    'nombreMateria' => $matricula->materia->nombreMateria ?? 'N/A',
                    'asistencias' => $asistidas,
                    'inasistencias' => $faltas,
                    'justificadas' => $justificadas,
                    'total' => $totalSesionesMateria,
                    'detalle' => $matricula->asistencias->filter(function ($a) {
                        return $a->asistio === true || $a->asistio === false;
                    })->map(function ($a) {
                        return [
                            'fecha' => $a->sesionMateria->fechaSesion ?? null,
                            'numeroSesion' => $a->sesionMateria->numeroSesion ?? null,
                            'asistio' => (bool) $a->asistio,
                            'estado' => $a->asistio ? 'Presente' : 'Ausente',
                        ];
                    })->values()
                ];
            }

            return response()->json([
                'countAsistencia' => $countAsistencia,
                'countFaltas' => $countFaltas,
                'countAsistenciasJustificadas' => $countJustificadas,
                'countTotalAsistencias' => $countAsistencia + $countFaltas + $countJustificadas,
                'aprendiz' => $aprendiz,
                'materiaStats' => $materiaStats,
                'idFicha' => $idFicha
            ]);

        } catch (\Exception $e) {

            return response()->json([
                'message' => 'Error general',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getEstadisticasPorEstudiante(Request $request): JsonResponse
    {
        try {

            $idMatricula = $request->input('idMatricula');

            if (!$idMatricula) {
                return response()->json([
                    'message' => 'El idMatricula es requerido'
                ], 400);
            }

            $matriculas = MatriculaAcademica::with([
                'matricula.person',
                'materia',
                'asistencias.sesionMateria',
            ])
                ->where('idMatricula', $idMatricula)
                ->get();

            if ($matriculas->isEmpty()) {
                return response()->json([
                    'message' => 'No hay registros'
                ], 404);
            }

            $aprendiz = $matriculas->first()->matricula->person;

            $countAsistencia = 0;
            $countFaltas = 0;
            $countJustificadas = 0;
            $materiaStats = [];

            // Eager load the justificacion relation to avoid N+1 queries
            $matriculas->load('asistencias.justificacion');

            foreach ($matriculas as $matricula) {

                // Cálculo basado exclusivamente en la tabla asistencia
                $asistidas = $matricula->asistencias->where('asistio', true)->count();
                $faltas = $matricula->asistencias->where('asistio', false)->count();

                // Identificar justificadas dentro de las inasistencias reales
                $justificadas = $matricula->asistencias->where('asistio', false)->filter(function ($asistencia) {
                    return $asistencia->justificacion && $asistencia->justificacion->estado === 'APROBADO';
                })->count();

                $countAsistencia += $asistidas;
                // Para el reporte de estadísticas por estudiante, restamos las justificadas de las faltas
                $faltasNetas = max(0, $faltas - $justificadas);
                $countFaltas += $faltasNetas;
                $countJustificadas += $justificadas;

                $materiaStats[] = [
                    'nombreMateria' => optional($matricula->materia)->nombreMateria ?? 'SIN MATERIA',
                    'faltas' => $faltas,
                    'retrasos' => 0
                ];
            }

            return response()->json([
                'countAsistencia' => $countAsistencia,
                'countFaltas' => $countFaltas,
                'countAsistenciasJustificadas' => $countJustificadas,
                'countTotalAsistencias' => $countAsistencia + $countFaltas + $countJustificadas,
                'aprendiz' => $aprendiz,
                'materiaStats' => $materiaStats
            ]);

        } catch (\Exception $e) {

            return response()->json([
                'message' => 'Error general',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    public function getAllAssistance(Request $request): JsonResponse
    {

        $jsonData = $request->input('data');

        $data = json_decode($jsonData, true);

        $matriculaAcademicaWithAssistance = MatriculaAcademica::with('matricula.persona.usuario.persona', 'asistencias')
            ->where('idAsignacionPeriodoProgramaJornada', $data['idAsignacionPeriodoProgramaJornada'])
            ->where('idMateria', $data['idMateria'])
            ->where('idMatricula', $data['idMatricula'])
            ->first();

        return response()->json($matriculaAcademicaWithAssistance, 200);
    }
    public function updateAssistance(Request $request): JsonResponse
    {

        $validatedData = $request->validate([
            'idMateria' => 'nullable|integer',
            'idMatricula' => 'required|integer',
            'idHorarioMateria' => 'nullable|integer|exists:horarioMateria,id',
            'idMatriculaAcademica' => 'nullable|integer'
        ]);

        $idMatricula = $validatedData['idMatricula'];
        $requestMateriaId = $validatedData['idMateria'] ?? null;
        $idHorarioMateria = $validatedData['idHorarioMateria'] ?? null;
        $idMatriculaAcademica = $validatedData['idMatriculaAcademica'] ?? $request->input('idMatriculaAcademica');

        $matriculaAcademica = null;

        // 1. Buscar matrícula académica
        if ($idMatriculaAcademica) {
            $matriculaAcademica = MatriculaAcademica::with('ficha')->find($idMatriculaAcademica);
        }

        if (!$matriculaAcademica) {
            $matriculaAcademica = MatriculaAcademica::with('ficha')
                ->where('idMatricula', $idMatricula)
                ->when($requestMateriaId, function($q) use ($requestMateriaId) {
                    return $q->where('idMateria', $requestMateriaId);
                })
                ->first();
        }

        if (!$matriculaAcademica) {
            return response()->json(['message' => 'Matrícula académica no encontrada'], 404);
        }

        // Resolver IDs necesarios
        $idMateria = $requestMateriaId ?? $matriculaAcademica->idMateria;
        $idFicha = $matriculaAcademica->idFicha;

        $hoy = now();
        $dbIdDia = ($hoy->dayOfWeek == 0) ? 7 : $hoy->dayOfWeek;

        // ── Buscar el HorarioMateria ──────────────────────────────────────────
        if ($idHorarioMateria) {
            $horarioMateria = \App\Models\HorarioMateria::find($idHorarioMateria);
        } else {
            $horarioMateria = \App\Models\HorarioMateria::where('idFicha', $idFicha)
                ->whereHas('gradoMateria', function ($query) use ($idMateria) {
                    $query->where('idMateria', $idMateria);
                })
                ->where('idDia', $dbIdDia)
                ->first();

            if (!$horarioMateria) {
                $horarioMateria = \App\Models\HorarioMateria::where('idFicha', $idFicha)
                    ->whereHas('gradoMateria', function ($query) use ($idMateria) {
                        $query->where('idMateria', $idMateria);
                    })
                    ->first();
            }
        }

        if (!$horarioMateria) {
            return response()->json(['message' => 'No se encontró un horario asignado'], 404);
        }

        // ── Buscar / crear SesionMateria ──────────────────────────────────────
        $sesionMateria = \App\Models\SesionMateria::where('idHorarioMateria', $horarioMateria->id)
            ->whereDate('fechaSesion', today())
            ->first();

        if (!$sesionMateria && $horarioMateria->idDia == $dbIdDia) {
            $lastSession = \App\Models\SesionMateria::where('idHorarioMateria', $horarioMateria->id)
                ->max('numeroSesion') ?? 0;

            $sesionMateria = \App\Models\SesionMateria::create([
                'numeroSesion' => $lastSession + 1,
                'idHorarioMateria' => $horarioMateria->id,
                'fechaSesion' => today()->toDateString(),
            ]);
        }

        if (!$sesionMateria) {
            $sesionMateria = \App\Models\SesionMateria::where('idHorarioMateria', $horarioMateria->id)
                ->orderBy('fechaSesion', 'desc')
                ->first();
        }

        if (!$sesionMateria) {
            return response()->json(['message' => 'No hay sesiones programadas'], 404);
        }

        // ── Crear o actualizar la asistencia del estudiante ───────────────────
        $asistencia = Asistencia::where('idMatriculaAcademica', $matriculaAcademica->id)
            ->where('idSesionMateria', $sesionMateria->id)
            ->first();

        if (!$asistencia) {
            $asistencia = Asistencia::create([
                'idMatriculaAcademica' => $matriculaAcademica->id,
                'idSesionMateria' => $sesionMateria->id,
                'horaLLegada' => now(),
                'asistio' => $request['asistio'] ?? 0
            ]);
        } else {
            $asistencia->update([
                'asistio' => $request['asistio'] ?? 0
            ]);
        }

        // ─── AUTO-REGISTRO DE INASISTENCIAS PARA EL RESTO DEL GRUPO ───────────
        try {
            // Obtenemos todos los demás alumnos de la MISMA materia y ficha
            $todasLasMatriculas = MatriculaAcademica::where('idFicha', $idFicha)
                ->where('idMateria', $idMateria)
                ->where('id', '!=', $matriculaAcademica->id)
                ->get();

            foreach ($todasLasMatriculas as $otraMatricula) {
                $existe = Asistencia::where('idMatriculaAcademica', $otraMatricula->id)
                    ->where('idSesionMateria', $sesionMateria->id)
                    ->exists();

                if (!$existe) {
                    Asistencia::create([
                        'idMatriculaAcademica' => $otraMatricula->id,
                        'idSesionMateria' => $sesionMateria->id,
                        'horaLLegada' => null,
                        'asistio' => false,
                    ]);
                }
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error en auto-registro: ' . $e->getMessage());
        }
        // ──────────────────────────────────────────────────────────────────────

        // ── Justificación ────────────────────────────────────────────────────
        if ($request->has('justificada') && $request->justificada && !$request->asistio) {
            $excusa = \App\Models\Excusa::create([
                'tipoExcusa' => $request->tipoExcusa ?? 'FUERZA MAYOR',
                'observacion' => $request->observacionExcusa ?? null,
                'urlDocumento' => $request->urlDocumento ?? null,
                'fechaInicialJustificacion' => today(),
                'fechaFinalJustificacion' => today(),
            ]);

            \App\Models\JustificacionInasistencia::create([
                'idAsistencia' => $asistencia->id,
                'idExcusa' => $excusa->id,
                'idMatriculaAcademica' => $matriculaAcademica->id,
                'idPersona' => auth()->user()->idpersona ?? null,
                'estado' => 'APROBADO',
                'observacion' => 'Justificada desde el registro de asistencia de clase'
            ]);
        }
        // ─────────────────────────────────────────────────────────────────────

        return response()->json($asistencia, $request->isMethod('put') ? 200 : 201);
    }

    /**
     * Endpoint temporal para subir documento de excusa
     * TODO: Mover a un controlador específico de excusas cuando se implemente la funcionalidad completa
     */
    public function subirDocumentoExcusa(Request $request, $idExcusa): JsonResponse
    {
        try {
            $excusa = \App\Models\Excusa::findOrFail($idExcusa);

            if (!$request->hasFile('documento')) {
                return response()->json(['error' => 'No se proporcionó ningún archivo'], 400);
            }

            $file = $request->file('documento');

            // Validar que sea PDF
            if ($file->getClientOriginalExtension() !== 'pdf' && $file->getMimeType() !== 'application/pdf') {
                return response()->json(['error' => 'Solo se permiten archivos PDF'], 400);
            }

            // Guardar el archivo
            $path = $file->store('excusas', ['disk' => 'public']);

            // Actualizar la excusa con la ruta del documento
            $excusa->urlDocumento = $path;
            $excusa->save();

            return response()->json([
                'message' => 'Documento subido correctamente',
                'excusa' => $excusa
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al subir el documento',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Inicia la clase: lee la sesión existente de hoy y crea registros de
     * inasistencia (asistio=false) para todos los estudiantes que aún no
     * tienen registro de asistencia en esa sesión.
     */
    public function iniciarClase(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'idHorarioMateria' => 'required|integer|exists:horarioMateria,id',
            ]);

            $idHorarioMateria = $request->idHorarioMateria;

            $horarioMateria = \App\Models\HorarioMateria::findOrFail($idHorarioMateria);

            // Buscar la sesión existente para hoy o CREARLA si no existe
            $sesionMateria = \App\Models\SesionMateria::where('idHorarioMateria', $idHorarioMateria)
                ->whereDate('fechaSesion', today())
                ->first();

            if (!$sesionMateria) {
                // Solo crear si hoy es el día del horario programado
                $hoy = now();
                $dbIdDia = ($hoy->dayOfWeek == 0) ? 7 : $hoy->dayOfWeek;

                if ($horarioMateria->idDia == $dbIdDia) {
                    $lastSession = \App\Models\SesionMateria::where('idHorarioMateria', $idHorarioMateria)
                        ->max('numeroSesion') ?? 0;

                    $sesionMateria = \App\Models\SesionMateria::create([
                        'numeroSesion' => $lastSession + 1,
                        'idHorarioMateria' => $idHorarioMateria,
                        'fechaSesion' => today()->toDateString(),
                    ]);
                } else {
                    return response()->json([
                        'message' => 'No existe una sesión programada para hoy en este horario y hoy no es el día asignado.',
                    ], 404);
                }
            }

            // Obtener idMateria desde gradoMateria
            $gradoMateria = \Illuminate\Support\Facades\DB::table('gradoMateria')
                ->where('id', $horarioMateria->idGradoMateria)
                ->first();

            if (!$gradoMateria) {
                return response()->json([
                    'message' => 'No se encontró la materia asociada al horario.',
                ], 404);
            }

            $idFicha = $horarioMateria->idFicha;
            $idMateria = $gradoMateria->idMateria;

            // Obtener todas las matrículas académicas de esta ficha y materia
            $matriculas = MatriculaAcademica::where('idFicha', $idFicha)
                ->where('idMateria', $idMateria)
                ->get();

            // Se eliminó la creación automática de inasistencias por defecto.
            // Ahora iniciar clase simplemente asegura que la sesión exista.
            // Las faltas se generarán automáticamente solo cuando se marque al menos a un estudiante.

            return response()->json([
                'message' => "Clase iniciada correctamente.",
                'sesion' => $sesionMateria,
                'totalEstudiantes' => $matriculas->count(),
                'inasistenciasCreadas' => 0,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al iniciar la clase',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener asistencias del estudiante autenticado agrupadas por área de conocimiento
     * Retorna estadísticas generales y por área
     */
    public function getAsistenciasPorArea(Request $request): JsonResponse
    {
        try {
            // Obtener el usuario autenticado
            $user = auth()->user();
            if (!$user || !$user->idpersona) {
                return response()->json([
                    'message' => 'Usuario no autenticado o sin persona asociada',
                    'data' => []
                ], 401);
            }

            $idPersona = $user->idpersona;

            // Obtener todas las matrículas académicas del estudiante
            $matriculas = MatriculaAcademica::with([
                'materia.areaConocimiento',
                'asistencias.sesionMateria'
            ])
                ->whereHas('matricula', function ($query) use ($idPersona) {
                    $query->where('idPersona', $idPersona);
                })
                ->get();

            if ($matriculas->isEmpty()) {
                return response()->json([
                    'message' => 'No se encontraron matrículas académicas',
                    'data' => [
                        'areas' => [],
                        'registros' => [],
                        'resumen' => [
                            'asistenciaGeneral' => 0,
                            'totalAsistencias' => 0,
                            'totalInasistencias' => 0,
                            'totalRegistros' => 0
                        ]
                    ]
                ], 200);
            }

            // Agrupar asistencias por área de conocimiento
            $areasMap = [];
            $registrosDetallados = [];
            $totalAsistencias = 0;
            $totalInasistencias = 0;

            foreach ($matriculas as $matricula) {
                $areaConocimiento = $matricula->materia->areaConocimiento ?? null;

                if (!$areaConocimiento) {
                    continue;
                }

                $idArea = $areaConocimiento->id;
                $nombreArea = $areaConocimiento->nombreAreaConocimiento;

                if (!isset($areasMap[$idArea])) {
                    $areasMap[$idArea] = [
                        'idArea' => $idArea,
                        'nombreArea' => $nombreArea,
                        'asistencias' => 0,
                        'inasistencias' => 0,
                        'total' => 0
                    ];
                }

                foreach ($matricula->asistencias as $asistencia) {
                    $sesionMateria = $asistencia->sesionMateria;
                    $fechaSesion = $sesionMateria ? $sesionMateria->fechaSesion : null;

                    // Verificar si tiene justificación aprobada
                    $justificacion = JustificacionInasistencia::where('idAsistencia', $asistencia->id)
                        ->where('estado', 'APROBADO')
                        ->with('excusa')
                        ->first();

                    $estaJustificada = $justificacion !== null;

                    if ($asistencia->asistio === true) {
                        $areasMap[$idArea]['asistencias']++;
                        $totalAsistencias++;
                        $areasMap[$idArea]['total']++;
                    } elseif ($asistencia->asistio === false) {
                        $areasMap[$idArea]['inasistencias']++;
                        $totalInasistencias++;
                        $areasMap[$idArea]['total']++;
                    }

                    if ($asistencia->asistio === true || $asistencia->asistio === false) {
                        $registro = [
                            'fecha' => $fechaSesion,
                            'numeroSesion' => $numeroSesion,
                            'idMateria' => $idMateria,
                            'nombreMateria' => $nombreMateria,
                            'idArea' => $idArea,
                            'nombreArea' => $nombreArea,
                            'asistio' => (bool) $asistencia->asistio,
                            'estado' => $asistencia->asistio ? 'Presente' : ($estaJustificada ? 'Inasistencia Justificada' : 'Ausente'),
                            'idAsistencia' => $asistencia->id
                        ];

                        if ($estaJustificada && $justificacion) {
                            $excusa = $justificacion->excusa;
                            $urlDocumento = null;
                            if ($excusa && $excusa->urlDocumento) {
                                $path = $excusa->urlDocumento;
                                if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
                                    $urlDocumento = $path;
                                } else {
                                    if (str_starts_with($path, 'storage/')) {
                                        $path = substr($path, 8);
                                    }
                                    if (str_starts_with($path, '/storage/')) {
                                        $path = substr($path, 9);
                                    }
                                    $urlDocumento = \Illuminate\Support\Facades\Storage::disk('public')->url($path);
                                }
                            }
                            $registro['justificacion'] = [
                                'id' => $justificacion->id,
                                'estado' => $justificacion->estado,
                                'observacion' => $justificacion->observacion,
                                'excusa' => [
                                    'id' => $excusa->id ?? null,
                                    'tipoExcusa' => $excusa->tipoExcusa ?? null,
                                    'observacion' => $excusa->observacion ?? null,
                                    'fechaInicialJustificacion' => $excusa->fechaInicialJustificacion ?? null,
                                    'fechaFinalJustificacion' => $excusa->fechaFinalJustificacion ?? null,
                                    'urlDocumento' => $urlDocumento
                                ]
                            ];
                        }

                        $registrosDetallados[] = $registro;
                    }
                }
            }

            $areasArray = array_values($areasMap);
            foreach ($areasArray as &$area) {
                $area['porcentaje'] = $area['total'] > 0
                    ? round(($area['asistencias'] / $area['total']) * 100)
                    : 0;
            }

            usort($registrosDetallados, function ($a, $b) {
                return strtotime($b['fecha']) - strtotime($a['fecha']);
            });

            $totalRegistros = $totalAsistencias + $totalInasistencias;
            $asistenciaGeneral = $totalRegistros > 0
                ? round(($totalAsistencias / $totalRegistros) * 100)
                : 0;

            return response()->json([
                'message' => 'Asistencias obtenidas correctamente',
                'data' => [
                    'areas' => $areasArray,
                    'registros' => $registrosDetallados,
                    'resumen' => [
                        'asistenciaGeneral' => $asistenciaGeneral,
                        'totalAsistencias' => $totalAsistencias,
                        'totalInasistencias' => $totalInasistencias,
                        'totalRegistros' => $totalRegistros
                    ]
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener asistencias por área',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener asistencias generales del estudiante autenticado.
     * Devuelve totales de asistencias, inasistencias, justificadas y porcentaje.
     */
    public function misAsistenciasGenerales(Request $request): JsonResponse
    {
        try {
            $user = \App\Util\KeyUtil::user() ?? auth()->user();
            $idPersona = $user?->idpersona ?? $user?->persona?->id;

            if (!$idPersona) {
                return response()->json([
                    'message' => 'Usuario no autenticado o sin persona asociada',
                    'data' => []
                ], 401);
            }

            $idMatriculaAcademica = $request->input('idMatriculaAcademica');
            $idMatricula = $request->input('idMatricula');
            $idMateria = $request->input('idMateria');

            // 1. Filtrar las matrículas académicas que pertenecen exclusivamente al estudiante autenticado
            $matriculasQuery = MatriculaAcademica::whereHas('matricula', function ($query) use ($idPersona) {
                $query->where('idPersona', $idPersona);
            });

            if ($idMatriculaAcademica) {
                $matriculasQuery->where('id', $idMatriculaAcademica);
            }
            if ($idMatricula) {
                $matriculasQuery->where('idMatricula', $idMatricula);
            }
            if ($idMateria) {
                $matriculasQuery->where('idMateria', $idMateria);
            }

            $idsMatriculaAcademica = $matriculasQuery->pluck('id')->toArray();

            if (empty($idsMatriculaAcademica)) {
                return response()->json([
                    'message' => 'No se encontraron matrículas para el estudiante',
                    'data' => []
                ], 200);
            }

            // 2. Obtener asistencias con todas las relaciones necesarias para el agrupamiento
            $asistencias = Asistencia::with([
                'sesionMateria.horarioMateria.gradoMateria.materia.areaConocimiento',
                'matriculaAcademica.materia.areaConocimiento',
                'justificacion'
            ])
                ->whereIn('idMatriculaAcademica', $idsMatriculaAcademica)
                ->whereNotNull('asistio') // Solo sesiones con estado real (Presente/Ausente)
                ->get();

            $agrupado = [];

            // 3. Lógica de agrupamiento por Área - Observación
            // 3. Lógica de agrupamiento por Área - Observación
            foreach ($asistencias as $asistencia) {
                $sesion = $asistencia->sesionMateria;
                $horario = $sesion?->horarioMateria;
                $materiaRel = $horario?->gradoMateria?->materia ?? $asistencia->matriculaAcademica?->materia;

                if (!$materiaRel)
                    continue;

                $nombreArea = $materiaRel->areaConocimiento?->nombreAreaConocimiento ?? 'Sin Área';
                $observacion = $horario?->observacion ?? 'General';

                // Verificar si tiene justificación aprobada
                $justificada = $asistencia->justificacion && $asistencia->justificacion->estado === 'APROBADO';

                // Clave legible: AREA - OBSERVACION
                $clave = "{$nombreArea} - {$observacion}";

                if (!isset($agrupado[$clave])) {
                    $agrupado[$clave] = [
                        'idMateria' => $materiaRel->id,
                        'nombreMateria' => $materiaRel->nombreMateria,
                        'areaConocimiento' => $nombreArea,
                        'asistencias' => [],
                        'resumen' => [
                            'totalSesiones' => 0,
                            'asistio' => 0,
                            'falto' => 0,
                            'justificadas' => 0,
                            'porcentajeAsistencia' => 0
                        ]
                    ];
                }

                // Registro detallado
                $agrupado[$clave]['asistencias'][] = [
                    'id' => $asistencia->id,
                    'fechaSesion' => $sesion?->fechaSesion,
                    'numeroSesion' => $sesion?->numeroSesion,
                    'asistio' => (bool) $asistencia->asistio,
                    'horaLLegada' => $asistencia->horaLLegada,
                    'estado' => $asistencia->asistio ? 'Presente' : ($justificada ? 'Inasistencia Justificada' : 'Ausente')
                ];

                // Actualización de contadores
                $agrupado[$clave]['resumen']['totalSesiones']++;
                if ($asistencia->asistio) {
                    $agrupado[$clave]['resumen']['asistio']++;
                } else {
                    $agrupado[$clave]['resumen']['falto']++;
                    if ($justificada) {
                        $agrupado[$clave]['resumen']['justificadas']++;
                    }
                }
            }

            // 4. Cálculo final de porcentajes redondeados
            foreach ($agrupado as &$item) {
                $total = $item['resumen']['totalSesiones'];
                if ($total > 0) {
                    $item['resumen']['porcentajeAsistencia'] = round(($item['resumen']['asistio'] / $total) * 100);
                }
            }

            return response()->json([
                'message' => 'Asistencias agrupadas por materia',
                'data' => $agrupado
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener asistencias generales',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Dashboard consolidado del aprendiz autenticado.
     * Combina asistencias-por-area + actividades-aprendiz en un solo endpoint.
     * No requiere parámetros.
     */
    public function getDashboardEstudiante(): JsonResponse
    {
        try {
            $user = \App\Util\KeyUtil::user() ?? auth()->user();
            $idPersona = $user?->idpersona ?? $user?->persona?->id;

            \Illuminate\Support\Facades\Log::info("Dashboard Estudiante API llamada. User ID: " . ($user?->id ?? 'null') . " - Persona ID: " . ($idPersona ?? 'null'));

            if (!$idPersona) {
                \Illuminate\Support\Facades\Log::warning("Estudiante sin persona asociada abortado en dashboard");
                return response()->json([
                    'asistencia' => null,
                    'actividades' => [],
                    'message' => 'Usuario sin persona asociada'
                ], 200);
            }

            // ── 1. Asistencias por área (solo filas cuya sesión ya ocurrió; coincide con mis-asistencias-generales)
            $matriculas = \App\Models\MatriculaAcademica::with([
                'materia.areaConocimiento',
                'asistencias.sesionMateria',
            ])
                ->whereHas('matricula', function ($query) use ($idPersona) {
                    $query->where('idPersona', $idPersona);
                })
                ->get();

            $areasMap = [];
            $totalAsistencias = 0;
            $totalInasistencias = 0;

            foreach ($matriculas as $matricula) {
                $areaConocimiento = $matricula->materia->areaConocimiento ?? null;

                if (!$areaConocimiento) {
                    continue;
                }

                $idArea = $areaConocimiento->id;
                $nombreArea = $areaConocimiento->nombreAreaConocimiento;

                if (!isset($areasMap[$idArea])) {
                    $areasMap[$idArea] = [
                        'idArea' => $idArea,
                        'nombreArea' => $nombreArea,
                        'asistencias' => 0,
                        'inasistencias' => 0,
                        'total' => 0,
                        'porcentaje' => 0
                    ];
                }

                foreach ($matricula->asistencias as $asistencia) {

                    if ($asistencia->asistio === true) {
                        $areasMap[$idArea]['asistencias']++;
                        $totalAsistencias++;
                        $areasMap[$idArea]['total']++;
                    } elseif ($asistencia->asistio === false) {
                        $areasMap[$idArea]['inasistencias']++;
                        $totalInasistencias++;
                        $areasMap[$idArea]['total']++;
                    }
                    // Ignorar NULL de acuerdo a la regla de negocio
                }
            }

            foreach ($areasMap as &$area) {
                $area['porcentaje'] = $area['total'] > 0
                    ? round(($area['asistencias'] / $area['total']) * 100)
                    : 0;
            }
            unset($area);

            $totalRegistros = $totalAsistencias + $totalInasistencias;
            $asistenciaGeneral = $totalRegistros > 0
                ? round(($totalAsistencias / $totalRegistros) * 100)
                : 0;

            $asistencia = [
                'areas' => array_values($areasMap),
                'resumen' => [
                    'asistenciaGeneral' => $asistenciaGeneral,
                    'totalAsistencias' => $totalAsistencias,
                    'totalInasistencias' => $totalInasistencias,
                    'totalRegistros' => $totalRegistros,
                ],
            ];

            // ── 2. Actividades del aprendiz ─────────────────────────────────────
            $tableMa = \Illuminate\Support\Facades\Schema::hasTable('matriculaAcademica')
                ? 'matriculaAcademica' : 'matriculaacademica';
            $actividades = [];

            if (\Illuminate\Support\Facades\Schema::hasTable('calificacionActividad')) {
                $rows = \Illuminate\Support\Facades\DB::table('calificacionActividad as ca')
                    ->join($tableMa . ' as ma', 'ca.idAMartriculaAcademica', '=', 'ma.id')
                    ->join('matricula as m', 'ma.idMatricula', '=', 'm.id')
                    ->join('actividades as a', 'ca.idActividad', '=', 'a.id')
                    ->leftJoin('materia as mat', 'a.idMateria', '=', 'mat.id')
                    ->leftJoin('area_conocimiento as ac', 'mat.idAreaConocimiento', '=', 'ac.id')
                    ->where('m.idPersona', $idPersona)
                    ->select([
                        'ca.id as idCalificacionActividad',
                        'ca.calificacionNumerica',
                        'ca.calificacionEstandart',
                        'ca.archivo as archivoEntrega',
                        'ca.ComentarioEstudiante',
                        'ca.fechaFinal',
                        'a.tituloActividad',
                        'mat.nombreMateria',
                        'ac.nombreAreaConocimiento as areaNombre',
                    ])
                    ->orderByDesc('ca.fechaFinal')
                    ->get();

                foreach ($rows as $row) {
                    $calificacion = trim((string) ($row->calificacionNumerica ?? ''));
                    $archivo = trim((string) ($row->archivoEntrega ?? ''));
                    $comentario = trim((string) ($row->ComentarioEstudiante ?? ''));
                    $fechaFinal = $row->fechaFinal ? \Carbon\Carbon::parse($row->fechaFinal) : null;

                    if ($calificacion !== '') {
                        $estadoVisual = 'CALIFICADO';
                    } elseif ($archivo !== '' || $comentario !== '') {
                        $estadoVisual = 'POR_EVALUAR';
                    } elseif ($fechaFinal && now()->greaterThan($fechaFinal)) {
                        $estadoVisual = 'SIN_ENTREGAR';
                    } else {
                        $estadoVisual = 'PENDIENTE';
                    }

                    $fechaVencida = $fechaFinal ? now()->greaterThan($fechaFinal) : false;

                    $actividades[] = [
                        'idCalificacionActividad' => $row->idCalificacionActividad,
                        'tituloActividad' => $row->tituloActividad,
                        'estadoVisual' => $estadoVisual,
                        'fechaFinal' => $row->fechaFinal,
                        'fechaVencida' => $fechaVencida,
                        'calificacionNumerica' => $row->calificacionNumerica,
                        'calificacionEstandart' => $row->calificacionEstandart ?? null,
                        'materia' => ['nombreMateria' => $row->nombreMateria],
                        'area' => ['nombre' => $row->areaNombre],
                    ];
                }
            }

            \Illuminate\Support\Facades\Log::info("Dashboard Estudiante finalizado. Actividades encontradas: " . count($actividades) . " | Asistencias encontradas en DB o calculadas: " . count($asistencia['areas']));

            return response()->json([
                'asistencia' => $asistencia,
                'actividades' => $actividades,
            ], 200);

        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error("Dashboard Estudiante Error: " . $e->getMessage() . "\n" . $e->getTraceAsString());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function getDashboardEstudiantePorId($idPersona, Request $request): JsonResponse
    {
        try {
            \Illuminate\Support\Facades\Log::info("Dashboard Estudiante API llamada por parametro. Persona Solicitada ID: " . $idPersona);

            // ── 1. Asistencias por área (solo sesiones ya ocurridas)
            $matriculas = \App\Models\MatriculaAcademica::with([
                'materia.areaConocimiento',
                'asistencias.sesionMateria',
            ])
                ->whereHas('matricula', function ($query) use ($idPersona) {
                    $query->where('idPersona', $idPersona);
                })
                ->get();

            $areasMap = [];
            $totalAsistencias = 0;
            $totalInasistencias = 0;

            foreach ($matriculas as $matricula) {
                $areaConocimiento = $matricula->materia->areaConocimiento ?? null;

                if (!$areaConocimiento) {
                    continue;
                }

                $idArea = $areaConocimiento->id;
                $nombreArea = $areaConocimiento->nombreAreaConocimiento;

                if (!isset($areasMap[$idArea])) {
                    $areasMap[$idArea] = [
                        'idArea' => $idArea,
                        'nombreArea' => $nombreArea,
                        'asistencias' => 0,
                        'inasistencias' => 0,
                        'total' => 0,
                        'porcentaje' => 0
                    ];
                }

                foreach ($matricula->asistencias as $asistencia) {

                    if ($asistencia->asistio === true) {
                        $areasMap[$idArea]['asistencias']++;
                        $totalAsistencias++;
                        $areasMap[$idArea]['total']++;
                    } elseif ($asistencia->asistio === false) {
                        $areasMap[$idArea]['inasistencias']++;
                        $totalInasistencias++;
                        $areasMap[$idArea]['total']++;
                    }
                    // Ignorar NULL de acuerdo a la regla de negocio
                }
            }

            foreach ($areasMap as &$area) {
                $area['porcentaje'] = $area['total'] > 0
                    ? round(($area['asistencias'] / $area['total']) * 100)
                    : 0;
            }
            unset($area);

            $totalRegistros = $totalAsistencias + $totalInasistencias;
            $asistenciaGeneral = $totalRegistros > 0
                ? round(($totalAsistencias / $totalRegistros) * 100)
                : 0;

            $asistencia = [
                'areas' => array_values($areasMap),
                'resumen' => [
                    'asistenciaGeneral' => $asistenciaGeneral,
                    'totalAsistencias' => $totalAsistencias,
                    'totalInasistencias' => $totalInasistencias,
                    'totalRegistros' => $totalRegistros,
                ],
            ];

            // ── 2. Actividades del aprendiz ─────────────────────────────────────
            $tableMa = \Illuminate\Support\Facades\Schema::hasTable('matriculaAcademica')
                ? 'matriculaAcademica' : 'matriculaacademica';
            $actividades = [];

            if (\Illuminate\Support\Facades\Schema::hasTable('calificacionActividad')) {
                $rows = \Illuminate\Support\Facades\DB::table('calificacionActividad as ca')
                    ->join($tableMa . ' as ma', 'ca.idAMartriculaAcademica', '=', 'ma.id')
                    ->join('matricula as m', 'ma.idMatricula', '=', 'm.id')
                    ->join('actividades as a', 'ca.idActividad', '=', 'a.id')
                    ->leftJoin('materia as mat', 'a.idMateria', '=', 'mat.id')
                    ->leftJoin('area_conocimiento as ac', 'mat.idAreaConocimiento', '=', 'ac.id')
                    ->where('m.idPersona', $idPersona)
                    ->select([
                        'ca.id as idCalificacionActividad',
                        'ca.calificacionNumerica',
                        'ca.calificacionEstandart',
                        'ca.archivo as archivoEntrega',
                        'ca.ComentarioEstudiante',
                        'ca.fechaFinal',
                        'a.tituloActividad',
                        'mat.nombreMateria',
                        'ac.nombreAreaConocimiento as areaNombre',
                    ])
                    ->orderByDesc('ca.fechaFinal')
                    ->get();

                foreach ($rows as $row) {
                    $calificacion = trim((string) ($row->calificacionNumerica ?? ''));
                    $archivo = trim((string) ($row->archivoEntrega ?? ''));
                    $comentario = trim((string) ($row->ComentarioEstudiante ?? ''));
                    $fechaFinal = $row->fechaFinal ? \Carbon\Carbon::parse($row->fechaFinal) : null;

                    if ($calificacion !== '') {
                        $estadoVisual = 'CALIFICADO';
                    } elseif ($archivo !== '' || $comentario !== '') {
                        $estadoVisual = 'POR_EVALUAR';
                    } elseif ($fechaFinal && now()->greaterThan($fechaFinal)) {
                        $estadoVisual = 'SIN_ENTREGAR';
                    } else {
                        $estadoVisual = 'PENDIENTE';
                    }

                    $fechaVencida = $fechaFinal ? now()->greaterThan($fechaFinal) : false;

                    $actividades[] = [
                        'idCalificacionActividad' => $row->idCalificacionActividad,
                        'tituloActividad' => $row->tituloActividad,
                        'estadoVisual' => $estadoVisual,
                        'fechaFinal' => $row->fechaFinal,
                        'fechaVencida' => $fechaVencida,
                        'calificacionNumerica' => $row->calificacionNumerica,
                        'calificacionEstandart' => $row->calificacionEstandart ?? null,
                        'materia' => ['nombreMateria' => $row->nombreMateria],
                        'area' => ['nombre' => $row->areaNombre],
                    ];
                }
            }

            \Illuminate\Support\Facades\Log::info("Dashboard Estudiante Por Id finalizado. Actividades encontradas: " . count($actividades) . " | Asistencias calculadas: " . count($asistencia['areas']));

            return response()->json([
                'asistencia' => $asistencia,
                'actividades' => $actividades,
            ], 200);

        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error("Dashboard Estudiante Por Id Error: " . $e->getMessage() . "\n" . $e->getTraceAsString());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
