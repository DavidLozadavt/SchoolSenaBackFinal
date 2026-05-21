<?php
namespace App\Http\Controllers;

use App\Models\Asistencia;
use App\Models\MatriculaAcademica;
use App\Models\JustificacionInasistencia;
use App\Models\HorarioMateria;
use App\Models\NotificacionSistema;
use App\Models\TipoNotificacion;
use App\Models\User;
use App\Util\KeyUtil;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
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
                'asistencias.justificacion.excusa',
            ])
                ->where('idMatricula', $idMatricula)
                ->get();

            if ($matriculas->isEmpty()) {
                return response()->json([
                    'message' => 'No hay registros'
                ], 404);
            }

            $aprendiz = $matriculas->first()->matricula->person ?? null;

            $countAsistencia = 0;
            $countFaltas = 0;
            $countJustificadas = 0;
            $materiaStats = [];
            $justificacionesDetalle = [];

            foreach ($matriculas as $matricula) {
                $asistidas = $matricula->asistencias->filter(function ($asistencia) {
                    return $asistencia->asistio === true
                        || $asistencia->asistio === 1
                        || $asistencia->asistio === '1';
                });

                $faltasCollection = $matricula->asistencias->filter(function ($asistencia) {
                    return $asistencia->asistio === false
                        || $asistencia->asistio === 0
                        || $asistencia->asistio === '0';
                });

                $justificadasCollection = $faltasCollection->filter(function ($asistencia) {
                    return $asistencia->justificacion
                        && in_array($asistencia->justificacion->estado, [
                            'APROBADO',
                            'JUSTIFICADO',
                            'ACEPTADO'
                        ]);
                });

                $asistidasCount = $asistidas->count();
                $faltasCount = $faltasCollection->count();
                $justificadasCount = $justificadasCollection->count();

                $faltasNetas = max(0, $faltasCount - $justificadasCount);

                $countAsistencia += $asistidasCount;
                $countFaltas += $faltasNetas;
                $countJustificadas += $justificadasCount;

                foreach ($justificadasCollection as $asistencia) {
                    $justificacion = $asistencia->justificacion;
                    $excusa = $justificacion?->excusa;

                    $archivoPath = $justificacion?->archivoSoporte ?: ($excusa?->urlDocumento ?? null);
                    $archivoUrl = null;

                    if ($archivoPath) {
                        $archivoPath = trim($archivoPath);

                        if (
                            str_starts_with($archivoPath, 'http://') ||
                            str_starts_with($archivoPath, 'https://')
                        ) {
                            $archivoUrl = $archivoPath;
                        } elseif ($justificacion?->id) {
                            $archivoUrl = route('justificaciones.soporte', $justificacion->id);
                        }
                    }

                    $justificacionesDetalle[] = [
                        'idJustificacion' => $justificacion?->id,
                        'idAsistencia' => $asistencia->id,
                        'idMateria' => $matricula->idMateria,
                        'nombreMateria' => optional($matricula->materia)->nombreMateria ?? 'SIN MATERIA',
                        'fecha' => $asistencia->sesionMateria?->fechaSesion,
                        'numeroSesion' => $asistencia->sesionMateria?->numeroSesion,
                        'estado' => $justificacion?->estado,
                        'observacion' => $justificacion?->observacion,
                        'archivoSoporte' => $archivoPath,
                        'archivoSoporteUrl' => $archivoUrl,
                        'excusa' => [
                            'id' => $excusa?->id,
                            'tipoExcusa' => $excusa?->tipoExcusa,
                            'observacion' => $excusa?->observacion,
                            'fechaInicialJustificacion' => $excusa?->fechaInicialJustificacion,
                            'fechaFinalJustificacion' => $excusa?->fechaFinalJustificacion,
                        ],
                    ];
                }

                $materiaStats[] = [
                    'idMateria' => $matricula->idMateria,
                    'nombreMateria' => optional($matricula->materia)->nombreMateria ?? 'SIN MATERIA',
                    'faltas' => $faltasNetas,
                    'faltasTotales' => $faltasCount,
                    'justificadas' => $justificadasCount,
                    'retrasos' => 0,
                ];
            }

            return response()->json([
                'countAsistencia' => $countAsistencia,
                'countFaltas' => $countFaltas,
                'countAsistenciasJustificadas' => $countJustificadas,
                'countTotalAsistencias' => $countAsistencia + $countFaltas + $countJustificadas,
                'aprendiz' => $aprendiz,
                'materiaStats' => $materiaStats,
                'justificaciones' => $justificacionesDetalle,
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

        if (!$data) {
            return response()->json([
                'message' => 'Datos inválidos'
            ], 400);
        }

        $matriculaAcademicaWithAssistance = MatriculaAcademica::with(
            'matricula.person.usuario',
            'asistencias'
        )
            ->where('idAsignacionPeriodoProgramaJornada', $data['idAsignacionPeriodoProgramaJornada'] ?? null)
            ->where('idMateria', $data['idMateria'] ?? null)
            ->where('idMatricula', $data['idMatricula'] ?? null)
            ->first();

        return response()->json($matriculaAcademicaWithAssistance, 200);
    }
    public function updateAssistance(Request $request): JsonResponse
    {
        try {
            $validatedData = $request->validate([
                'idMateria' => 'nullable|integer',
                'idMatricula' => 'required|integer',
                'idHorarioMateria' => 'nullable|integer|exists:horarioMateria,id',
                'idMatriculaAcademica' => 'nullable|integer',

                // Se dejan sin boolean porque desde FormData llegan como texto: "true", "false", "1" o "0"
                'asistio' => 'required',
                'justificada' => 'nullable',

                'tipoExcusa' => 'nullable|string|max:255',
                'observacionExcusa' => 'nullable|string',
                'archivoSoporte' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
            ]);

            $idMatricula = $validatedData['idMatricula'];
            $requestMateriaId = $validatedData['idMateria'] ?? null;
            $idHorarioMateria = $validatedData['idHorarioMateria'] ?? null;
            $idMatriculaAcademica = $validatedData['idMatriculaAcademica'] ?? $request->input('idMatriculaAcademica');

            $asistio = $request->boolean('asistio');
            $esJustificada = $request->boolean('justificada');

            $matriculaAcademica = null;

            // 1. Buscar matrícula académica
            if ($idMatriculaAcademica) {
                $matriculaAcademica = MatriculaAcademica::with('ficha')->find($idMatriculaAcademica);
            }

            if (!$matriculaAcademica) {
                $matriculaAcademica = MatriculaAcademica::with('ficha')
                    ->where('idMatricula', $idMatricula)
                    ->when($requestMateriaId, function ($q) use ($requestMateriaId) {
                        return $q->where('idMateria', $requestMateriaId);
                    })
                    ->first();
            }

            if (!$matriculaAcademica) {
                return response()->json([
                    'message' => 'Matrícula académica no encontrada'
                ], 404);
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
                return response()->json([
                    'message' => 'No se encontró un horario asignado'
                ], 404);
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
                return response()->json([
                    'message' => 'No hay sesiones programadas'
                ], 404);
            }

            // ── Crear o actualizar la asistencia del estudiante ───────────────────
            $asistencia = Asistencia::where('idMatriculaAcademica', $matriculaAcademica->id)
                ->where('idSesionMateria', $sesionMateria->id)
                ->first();

            if (!$asistencia) {
                $asistencia = Asistencia::create([
                    'idMatriculaAcademica' => $matriculaAcademica->id,
                    'idSesionMateria' => $sesionMateria->id,
                    'horaLLegada' => $asistio ? now() : null,
                    'asistio' => $asistio,
                ]);
            } else {
                $asistencia->update([
                    'horaLLegada' => $asistio ? now() : null,
                    'asistio' => $asistio,
                ]);
            }

            // ─── AUTO-REGISTRO DE INASISTENCIAS PARA EL RESTO DEL GRUPO ───────────
            try {
                $todasLasMatriculas = MatriculaAcademica::where('idFicha', $idFicha)
                    ->where('idMateria', $idMateria)
                    ->where('id', '!=', $matriculaAcademica->id)
                    ->get();

                foreach ($todasLasMatriculas as $otraMatricula) {
                    $existe = Asistencia::where('idMatriculaAcademica', $otraMatricula->id)
                        ->where('idSesionMateria', $sesionMateria->id)
                        ->exists();

                    if (!$existe) {
                        $nuevaAsistencia = Asistencia::create([
                            'idMatriculaAcademica' => $otraMatricula->id,
                            'idSesionMateria' => $sesionMateria->id,
                            'horaLLegada' => null,
                            'asistio' => false,
                        ]);

                        // Auto-Justificación por Rango para el resto del grupo
                        $otraPersonaId = $otraMatricula->matricula?->idPersona;
                        if ($otraPersonaId) {
                            $fechaSesionDate = $sesionMateria->fechaSesion ?? today()->toDateString();
                            $rangoDetalleAprobado = \App\Models\JustificacionAsistenciaRangoDetalle::whereHas('rango', function($q) use ($otraPersonaId, $fechaSesionDate) { $q->where('idPersonaAprendiz', $otraPersonaId)->whereDate('fechaInicial', '<=', $fechaSesionDate)->whereDate('fechaFinal', '>=', $fechaSesionDate); })->where('idFicha', $sesionMateria->horarioMateria->idFicha)->where('estado', 'APROBADO')->with('rango')->first(); $rangoAprobado = $rangoDetalleAprobado ? $rangoDetalleAprobado->rango : null;

                            if ($rangoAprobado) {
                                $excusa = \App\Models\Excusa::create([
                                    'tipoExcusa' => $rangoAprobado->tipoExcusa,
                                    'observacion' => $rangoAprobado->observacion,
                                    'urlDocumento' => $rangoAprobado->archivoSoporte,
                                    'fechaInicialJustificacion' => $rangoAprobado->fechaInicial,
                                    'fechaFinalJustificacion' => $rangoAprobado->fechaFinal,
                                ]);
                                
                                JustificacionInasistencia::updateOrCreate(
                                    ['idAsistencia' => $nuevaAsistencia->id],
                                    [
                                        'idExcusa' => $excusa->id,
                                        'idMatriculaAcademica' => $otraMatricula->id,
                                        'idPersona' => $otraPersonaId,
                                        'estado' => 'APROBADO',
                                        'observacion' => $rangoAprobado->observacion,
                                        'archivoSoporte' => $rangoAprobado->archivoSoporte,
                                    ]
                                );
                            }
                        }
                    }
                }
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('Error en auto-registro: ' . $e->getMessage());
            }

            // ── Auto-Justificación por Rango de Fechas Aprobado ───────────────────
            if (!$asistio) {
                $idPersonaAprendiz = $matriculaAcademica->matricula?->idPersona;
                
                if ($idPersonaAprendiz) {
                    $fechaSesionDate = $sesionMateria->fechaSesion ?? today()->toDateString();
                    $rangoDetalleAprobado = \App\Models\JustificacionAsistenciaRangoDetalle::whereHas('rango', function($q) use ($idPersonaAprendiz, $fechaSesionDate) { $q->where('idPersonaAprendiz', $idPersonaAprendiz)->whereDate('fechaInicial', '<=', $fechaSesionDate)->whereDate('fechaFinal', '>=', $fechaSesionDate); })->where('idFicha', $sesionMateria->horarioMateria->idFicha)->where('estado', 'APROBADO')->with('rango')->first(); $rangoAprobado = $rangoDetalleAprobado ? $rangoDetalleAprobado->rango : null;

                    if ($rangoAprobado) {
                        $excusa = \App\Models\Excusa::create([
                            'tipoExcusa' => $rangoAprobado->tipoExcusa,
                            'observacion' => $rangoAprobado->observacion,
                            'urlDocumento' => $rangoAprobado->archivoSoporte,
                            'fechaInicialJustificacion' => $rangoAprobado->fechaInicial,
                            'fechaFinalJustificacion' => $rangoAprobado->fechaFinal,
                        ]);
                        
                        JustificacionInasistencia::updateOrCreate(
                            ['idAsistencia' => $asistencia->id],
                            [
                                'idExcusa' => $excusa->id,
                                'idMatriculaAcademica' => $matriculaAcademica->id,
                                'idPersona' => $idPersonaAprendiz,
                                'estado' => 'APROBADO',
                                'observacion' => $rangoAprobado->observacion,
                                'archivoSoporte' => $rangoAprobado->archivoSoporte,
                            ]
                        );
                        // Prevent overriding it below if manual justification flag was sent
                        $esJustificada = false; 
                    }
                }
            }

            // ── Justificación de inasistencia (Manual) ───────────────────────────
            if ($esJustificada && !$asistio) {
                $archivoSoporte = null;

                if ($request->hasFile('archivoSoporte')) {
                    $archivoSoporte = $request->file('archivoSoporte')
                        ->store('justificaciones_inasistencia', 'public');
                }

                $justificacionExistente = JustificacionInasistencia::where('idAsistencia', $asistencia->id)
                    ->first();

                // Si ya existía una justificación y no subieron nuevo archivo,
                // conservamos el archivo anterior.
                if ($justificacionExistente && !$archivoSoporte) {
                    $archivoSoporte = $justificacionExistente->archivoSoporte;
                }

                // Si subieron un nuevo archivo y ya había uno anterior, eliminamos el anterior.
                if (
                    $justificacionExistente &&
                    $request->hasFile('archivoSoporte') &&
                    $justificacionExistente->archivoSoporte &&
                    Storage::disk('public')->exists($justificacionExistente->archivoSoporte)
                ) {
                    Storage::disk('public')->delete($justificacionExistente->archivoSoporte);
                }

                if ($justificacionExistente && $justificacionExistente->idExcusa) {
                    $excusa = \App\Models\Excusa::find($justificacionExistente->idExcusa);

                    if ($excusa) {
                        $excusa->update([
                            'tipoExcusa' => $request->tipoExcusa ?? 'FUERZA MAYOR',
                            'observacion' => $request->observacionExcusa ?? null,
                            'urlDocumento' => $archivoSoporte,
                            'fechaInicialJustificacion' => $sesionMateria->fechaSesion ?? today(),
                            'fechaFinalJustificacion' => $sesionMateria->fechaSesion ?? today(),
                        ]);
                    } else {
                        $excusa = \App\Models\Excusa::create([
                            'tipoExcusa' => $request->tipoExcusa ?? 'FUERZA MAYOR',
                            'observacion' => $request->observacionExcusa ?? null,
                            'urlDocumento' => $archivoSoporte,
                            'fechaInicialJustificacion' => $sesionMateria->fechaSesion ?? today(),
                            'fechaFinalJustificacion' => $sesionMateria->fechaSesion ?? today(),
                        ]);
                    }
                } else {
                    $excusa = \App\Models\Excusa::create([
                        'tipoExcusa' => $request->tipoExcusa ?? 'FUERZA MAYOR',
                        'observacion' => $request->observacionExcusa ?? null,
                        'urlDocumento' => $archivoSoporte,
                        'fechaInicialJustificacion' => $sesionMateria->fechaSesion ?? today(),
                        'fechaFinalJustificacion' => $sesionMateria->fechaSesion ?? today(),
                    ]);
                }

                JustificacionInasistencia::updateOrCreate(
                    [
                        'idAsistencia' => $asistencia->id,
                    ],
                    [
                        'idExcusa' => $excusa->id,
                        'idMatriculaAcademica' => $matriculaAcademica->id,
                        'idPersona' => auth()->user()->idpersona ?? null,
                        'estado' => 'APROBADO',
                        'observacion' => $request->observacionExcusa ?? 'Justificada desde el registro de asistencia de clase',
                        'archivoSoporte' => $archivoSoporte,
                    ]
                );
            }

            return response()->json([
                'message' => 'Asistencia actualizada correctamente',
                'data' => $asistencia->fresh(),
            ], $request->isMethod('put') ? 200 : 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $e->errors(),
            ], 422);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al actualizar asistencia',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    public function verSoporteJustificacion($id)
    {
        try {
            $justificacion = JustificacionInasistencia::with('excusa')->findOrFail($id);

            $archivo = $justificacion->archivoSoporte;

            if (!$archivo && $justificacion->excusa) {
                $archivo = $justificacion->excusa->urlDocumento;
            }

            if (!$archivo) {
                return response()->json([
                    'message' => 'La justificación no tiene archivo soporte.'
                ], 404);
            }

            $archivo = trim($archivo);
            $archivo = str_replace('/storage/', '', $archivo);
            $archivo = str_replace('storage/', '', $archivo);
            $archivo = ltrim($archivo, '/');

            if (!Storage::disk('public')->exists($archivo)) {
                return response()->json([
                    'message' => 'El archivo no existe en el almacenamiento.',
                    'archivo' => $archivo,
                ], 404);
            }

            return response()->file(storage_path('app/public/' . $archivo));

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'No se pudo abrir el archivo soporte.',
                'error' => $e->getMessage(),
            ], 404);
        }
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
            $user = \App\Util\KeyUtil::user() ?? auth()->user();
            $idPersona = $user?->idpersona ?? $user?->persona?->id;

            if (!$idPersona) {
                return response()->json([
                    'message' => 'Usuario no autenticado o sin persona asociada',
                    'data' => []
                ], 401);
            }

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
                    $materiaMatricula = $matricula->materia;
                    $idMateria = $materiaMatricula ? ($materiaMatricula->id ?? null) : null;
                    $nombreMateria = $materiaMatricula ? ($materiaMatricula->nombreMateria ?? '') : '';
                    $numeroSesion = $sesionMateria ? ($sesionMateria->numeroSesion ?? null) : null;

                    $justificacion = JustificacionInasistencia::where('idAsistencia', $asistencia->id)
                        ->with('excusa')
                        ->first();

                    $estadoJust = strtoupper((string) ($justificacion?->estado ?? ''));
                    $estaJustificadaAprobada = in_array($estadoJust, ['APROBADO', 'ACEPTADO', 'JUSTIFICADO'], true);

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
                        $estadoTexto = 'Presente';
                        if (!$asistencia->asistio) {
                            if ($estaJustificadaAprobada) {
                                $estadoTexto = 'Inasistencia Justificada';
                            } elseif ($estadoJust === 'PENDIENTE') {
                                $estadoTexto = 'Justificación pendiente';
                            } elseif ($estadoJust === 'RECHAZADO') {
                                $estadoTexto = 'Justificación rechazada';
                            } else {
                                $estadoTexto = 'Ausente';
                            }
                        }

                        $registro = [
                            'fecha' => $fechaSesion,
                            'numeroSesion' => $numeroSesion,
                            'idMateria' => $idMateria,
                            'nombreMateria' => $nombreMateria,
                            'idArea' => $idArea,
                            'nombreArea' => $nombreArea,
                            'asistio' => (bool) $asistencia->asistio,
                            'estado' => $estadoTexto,
                            'idAsistencia' => $asistencia->id,
                            'estadoJustificacion' => $justificacion?->estado,
                            'puedeJustificar' => !$asistencia->asistio
                                && (!$justificacion || $estadoJust === 'RECHAZADO'),
                        ];

                        if ($estaJustificadaAprobada && $justificacion) {
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
                $ta = strtotime((string) ($a['fecha'] ?? '')) ?: 0;
                $tb = strtotime((string) ($b['fecha'] ?? '')) ?: 0;

                return $tb <=> $ta;
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
                    if ($justificada) {
                        $agrupado[$clave]['resumen']['justificadas']++;
                    } else {
                        $agrupado[$clave]['resumen']['falto']++;
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
                'asistencias.justificacion',
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
                        // Verificar si tiene justificación aprobada
                        $justificada = $asistencia->justificacion && $asistencia->justificacion->estado === 'APROBADO';
                        
                        if (!$justificada) {
                            $areasMap[$idArea]['inasistencias']++;
                            $totalInasistencias++;
                        }
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
                'asistencias.justificacion',
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
                        // Verificar si tiene justificación aprobada
                        $justificada = $asistencia->justificacion && $asistencia->justificacion->estado === 'APROBADO';
                        
                        if (!$justificada) {
                            $areasMap[$idArea]['inasistencias']++;
                            $totalInasistencias++;
                        }
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

    /**
     * IDs de horarioMateria asignados al instructor autenticado (por contrato activo).
     */
    private function horarioMateriaIdsInstructor(?int $idFicha = null, ?int $idHorarioMateria = null): array
    {
        try {
            $contrato = KeyUtil::lastContractActive();
            $idContrato = $contrato?->id;
        } catch (\Throwable $e) {
            $idContrato = null;
        }

        if (!$idContrato) {
            return [];
        }

        $query = HorarioMateria::query()->where('idContrato', $idContrato);

        if ($idFicha) {
            $query->where('idFicha', $idFicha);
        }
        if ($idHorarioMateria) {
            $query->where('id', $idHorarioMateria);
        }

        return $query->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    private function usuarioIdPorPersona(?int $idPersona): ?int
    {
        if (!$idPersona) {
            return null;
        }

        return User::where('idpersona', $idPersona)->value('id');
    }

    private function enviarNotificacion(
        int $idUsuarioReceptor,
        ?int $idUsuarioRemitente,
        string $asunto,
        string $mensaje,
        string $route
    ): void {
        if ($idUsuarioReceptor <= 0) {
            return;
        }

        try {
            NotificacionSistema::create([
                'fecha' => now()->toDateString(),
                'hora' => now()->toTimeString(),
                'asunto' => $asunto,
                'mensaje' => $mensaje,
                'estado_id' => 1,
                'idUsuarioReceptor' => $idUsuarioReceptor,
                'idUsuarioRemitente' => $idUsuarioRemitente ?: $idUsuarioReceptor,
                'idTipoNotificacion' => TipoNotificacion::ID_ACTIVO,
                'idEmpresa' => KeyUtil::idCompany(),
                'route' => $route,
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('No se pudo crear notificación: ' . $e->getMessage());
        }
    }

    private function nombrePersona($persona): string
    {
        if (!$persona) {
            return 'Sin nombre';
        }

        return trim(implode(' ', array_filter([
            $persona->nombre1 ?? '',
            $persona->nombre2 ?? '',
            $persona->apellido1 ?? '',
            $persona->apellido2 ?? '',
        ])));
    }

    /**
     * Aprendiz solicita justificación de una inasistencia (queda en PENDIENTE).
     */
    public function solicitarJustificacionAsistencia(Request $request): JsonResponse
{
    try {
        $request->validate([
            'idAsistencia' => 'required|integer|exists:asistencia,id',
            'tipoExcusa' => 'required|string|max:255',
            'observacionExcusa' => 'required|string',
            'archivoSoporte' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        $user = KeyUtil::user() ?? auth()->user();
        $idPersonaEstudiante = $user?->idpersona ?? $user?->persona?->id;

        if (!$idPersonaEstudiante) {
            return response()->json([
                'message' => 'Usuario no autenticado'
            ], 401);
        }

        $asistencia = Asistencia::with([
            'justificacion',
            'sesionMateria.horarioMateria.ficha',
            'sesionMateria.horarioMateria.gradoMateria.materia.areaConocimiento',
            'matriculaAcademica.matricula.person',
        ])->findOrFail($request->idAsistencia);

        if ($asistencia->asistio === true || $asistencia->asistio === 1 || $asistencia->asistio === '1') {
            return response()->json([
                'message' => 'No puede justificar un registro marcado como presente.',
            ], 422);
        }

        $idPersonaMatricula = $asistencia->matriculaAcademica?->matricula?->idPersona;

        if ((int) $idPersonaMatricula !== (int) $idPersonaEstudiante) {
            return response()->json([
                'message' => 'No autorizado para esta asistencia'
            ], 403);
        }

        $justificacionExistente = $asistencia->justificacion;

        if ($justificacionExistente) {
            $estado = strtoupper((string) $justificacionExistente->estado);

            if (in_array($estado, ['PENDIENTE', 'APROBADO', 'ACEPTADO', 'JUSTIFICADO'], true)) {
                return response()->json([
                    'message' => 'Ya existe una justificación ' . strtolower($estado) . ' para esta inasistencia.',
                ], 422);
            }
        }

        $archivoSoporte = null;

        if ($request->hasFile('archivoSoporte')) {
            $archivoSoporte = $request->file('archivoSoporte')
                ->store('justificaciones_inasistencia', 'public');
        }

        $fechaSesion = $asistencia->sesionMateria?->fechaSesion ?? today();

        $excusa = \App\Models\Excusa::create([
            'tipoExcusa' => $request->tipoExcusa,
            'observacion' => $request->observacionExcusa,
            'urlDocumento' => $archivoSoporte,
            'fechaInicialJustificacion' => $fechaSesion,
            'fechaFinalJustificacion' => $fechaSesion,
        ]);

        $justificacion = JustificacionInasistencia::updateOrCreate(
            [
                'idAsistencia' => $asistencia->id,
            ],
            [
                'idExcusa' => $excusa->id,
                'idMatriculaAcademica' => $asistencia->idMatriculaAcademica,
                'idPersona' => $idPersonaEstudiante,
                'estado' => 'PENDIENTE',
                'observacion' => $request->observacionExcusa,
                'archivoSoporte' => $archivoSoporte,
            ]
        );

        $horario = $asistencia->sesionMateria?->horarioMateria;
        $idHorarioMateria = $horario?->id;

        $nombreEstudiante = $this->nombrePersona(
            $asistencia->matriculaAcademica?->matricula?->person
        );

        $materiaNombre = $horario?->gradoMateria?->materia?->nombreMateria ?? 'clase';

        if ($horario?->idContrato) {
            $contratoInstructor = \App\Models\Contract::with('persona.usuario')
                ->find($horario->idContrato);

            $idUsuarioInstructor = $this->usuarioIdPorPersona(
                $contratoInstructor?->persona?->id ?? $contratoInstructor?->idpersona
            );

            if ($idUsuarioInstructor) {
                $ruta = $idHorarioMateria
                    ? '/ambiente-virtual/clase/' . $idHorarioMateria . '?menu=justificaciones-pendientes'
                    : '/ambiente-virtual/historial-raps';

                $this->enviarNotificacion(
                    (int) $idUsuarioInstructor,
                    (int) ($user->id ?? 0),
                    'Solicitud de justificación de falta',
                    "{$nombreEstudiante} solicitó justificar su inasistencia en {$materiaNombre}.",
                    $ruta
                );
            }
        }

        return response()->json([
            'message' => 'Justificación enviada. El instructor la revisará pronto.',
            'data' => $justificacion->fresh(['excusa']),
        ], 201);

    } catch (\Illuminate\Validation\ValidationException $e) {
        return response()->json([
            'message' => 'Error de validación',
            'errors' => $e->errors(),
        ], 422);

    } catch (\Exception $e) {
        return response()->json([
            'message' => 'Error al solicitar justificación',
            'error' => $e->getMessage(),
        ], 500);
    }
}

    public function solicitarJustificacionAsistenciaRango(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'fechaInicial' => 'required|date',
                'fechaFinal' => 'required|date|after_or_equal:fechaInicial',
                'tipoExcusa' => 'required|string|max:255',
                'observacion' => 'required|string',
                'archivoSoporte' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
            ]);

            $user = KeyUtil::user() ?? auth()->user();
            $idPersonaEstudiante = $user?->idpersona ?? $user?->persona?->id;

            if (!$idPersonaEstudiante) {
                return response()->json([
                    'message' => 'Usuario no autenticado'
                ], 401);
            }

            $archivoSoporte = null;
            if ($request->hasFile('archivoSoporte')) {
                $archivoSoporte = $request->file('archivoSoporte')
                    ->store('justificaciones_inasistencia', 'public');
            }

            $rango = \App\Models\JustificacionAsistenciaRango::create(['idPersonaAprendiz' => $idPersonaEstudiante,'fechaInicial' => $request->fechaInicial,'fechaFinal' => $request->fechaFinal,'tipoExcusa' => $request->tipoExcusa,'observacion' => $request->observacion,'archivoSoporte' => $archivoSoporte,'estado' => 'PENDIENTE']);

            $matriculas = \App\Models\MatriculaAcademica::whereHas('matricula', function($q) use ($idPersonaEstudiante) { $q->where('idPersona', $idPersonaEstudiante); })->get();
            $fichaIds = $matriculas->pluck('idFicha')->filter()->unique()->toArray();
            $fichas = \App\Models\Ficha::whereIn('id', $fichaIds)->get(); foreach ($fichas as $ficha) { \App\Models\JustificacionAsistenciaRangoDetalle::create([ 'idJustificacionAsistenciaRango' => $rango->id, 'idHorarioMateria' => null, 'idFicha' => $ficha->id, 'idContratoInstructor' => $ficha->idInstructorLider, 'estado' => 'PENDIENTE' ]); if ($ficha->idInstructorLider) { $idPersonaLider = \App\Models\Contract::where('id', $ficha->idInstructorLider)->value('idpersona'); if ($idPersonaLider) { $idUsuarioLider = \App\Models\User::where('idpersona', $idPersonaLider)->value('id'); if ($idUsuarioLider) { $nombreEstudiante = $this->nombrePersona($user?->persona ?? $user?->person); $this->enviarNotificacion((int) $idUsuarioLider, (int) ($user->id ?? 0), 'Solicitud de justificación por rango', $nombreEstudiante . ' solicitó justificar su inasistencia por rango de fechas.', '/ambiente-virtual/historial-raps'); } } } }

            return response()->json([
                'message' => 'Solicitud de justificación por rango enviada. El instructor la revisará pronto.',
                'data' => $rango
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al solicitar justificación por rango',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Justificaciones pendientes para el instructor autenticado.
     */
   public function justificacionesPendientesInstructor(Request $request): JsonResponse
{
    try {
        $idFicha = $request->integer('id_ficha') ?: null;
        $idHorarioMateria = $request->integer('id_horario_materia') ?: null;

        $horarioIds = $this->horarioMateriaIdsInstructor($idFicha, $idHorarioMateria);

        if (empty($horarioIds)) {
            return response()->json([
                'message' => 'Sin clases asignadas',
                'data' => []
            ], 200);
        }

        $resolverUrlDocumento = function ($valor): ?string {
            if ($valor === null || $valor === '') {
                return null;
            }

            if (!is_string($valor) && !is_numeric($valor)) {
                return null;
            }

            $path = trim((string) $valor);

            if ($path === '') {
                return null;
            }

            if (
                str_starts_with($path, 'http://') ||
                str_starts_with($path, 'https://')
            ) {
                return $path;
            }

            $path = str_replace('/storage/', '', $path);
            $path = str_replace('storage/', '', $path);
            $path = ltrim($path, '/');

            return Storage::disk('public')->url($path);
        };

        $items = JustificacionInasistencia::with([
            'excusa',
            'asistencia.sesionMateria.horarioMateria.ficha',
            'asistencia.sesionMateria.horarioMateria.gradoMateria.materia.areaConocimiento',
            'asistencia.matriculaAcademica.matricula.person',
        ])
            ->where('estado', 'PENDIENTE')
            ->whereHas('asistencia.sesionMateria', function ($q) use ($horarioIds) {
                $q->whereIn('idHorarioMateria', $horarioIds);
            })
            ->orderByDesc('created_at')
            ->get();

        $horarios = HorarioMateria::whereIn('id', $horarioIds)->get();
        $fichaIds = $horarios->pluck('idFicha')->filter()->unique()->values()->all();

        $personasPermitidas = MatriculaAcademica::with('matricula')
            ->whereIn('idFicha', $fichaIds)
            ->get()
            ->pluck('matricula.idPersona')
            ->filter()
            ->unique()
            ->values()
            ->all();

        $userAuth = KeyUtil::user() ?? auth()->user(); $idPersonaInstr = $userAuth?->idpersona ?? $userAuth?->persona?->id; $contratosIdsAuth = \App\Models\Contract::where('idpersona', $idPersonaInstr)->pluck('id')->toArray(); $fichasLider = \App\Models\Ficha::whereIn('idInstructorLider', $contratosIdsAuth)->pluck('id')->toArray();

        $detallesRango = \App\Models\JustificacionAsistenciaRangoDetalle::with(['rango.personaAprendiz', 'ficha'])->where('estado', 'PENDIENTE')->whereIn('idFicha', $fichasLider)->orderByDesc('created_at')->get();

        $dataIndividuales = $items->map(function (\App\Models\JustificacionInasistencia $j) use ($resolverUrlDocumento) { $asistencia = $j->asistencia; $sesion = $asistencia?->sesionMateria; $horario = $sesion?->horarioMateria; $persona = $asistencia?->matriculaAcademica?->matricula?->person; $materia = $horario?->gradoMateria?->materia; $archivoJustificacion = $j->archivoSoporte; $archivoExcusa = null; if ($j->excusa) { $archivoExcusa = $j->excusa->getRawOriginal('urlDocumento'); if (!$archivoExcusa) { $archivoExcusa = $j->excusa->getAttribute('urlDocumento'); } } $urlDocumento = $resolverUrlDocumento($archivoJustificacion); if (!$urlDocumento) { $urlDocumento = $resolverUrlDocumento($archivoExcusa); } return ['id' => $j->id, 'tipo' => 'individual', 'idJustificacion' => $j->id, 'idAsistencia' => $j->idAsistencia, 'estado' => $j->estado, 'observacion' => $j->observacion, 'fechaClase' => $sesion?->fechaSesion, 'numeroSesion' => $sesion?->numeroSesion, 'nombreEstudiante' => $this->nombrePersona($persona), 'identificacionEstudiante' => $persona?->identificacion, 'codigoFicha' => $horario?->ficha?->codigo, 'idFicha' => $horario?->idFicha, 'idHorarioMateria' => $horario?->id, 'nombreMateria' => $materia?->nombreMateria, 'nombreArea' => $materia?->areaConocimiento?->nombreAreaConocimiento, 'excusa' => ['id' => $j->excusa?->id, 'tipoExcusa' => $j->excusa?->tipoExcusa, 'observacion' => $j->excusa?->observacion ?? $j->observacion, 'fechaInicialJustificacion' => $j->excusa?->fechaInicialJustificacion, 'fechaFinalJustificacion' => $j->excusa?->fechaFinalJustificacion, 'urlDocumento' => $urlDocumento, ], ]; })->values();

        $dataRangos = $detallesRango->map(function ($d) use ($resolverUrlDocumento) { $r = $d->rango; $urlDocumento = $resolverUrlDocumento($r->archivoSoporte); return ['id' => $r->id, 'tipo' => 'rango', 'idJustificacion' => $d->id, 'estado' => $d->estado, 'observacion' => $r->observacion, 'fechaInicial' => $r->fechaInicial, 'fechaFinal' => $r->fechaFinal, 'fechaClase' => null, 'nombreEstudiante' => $this->nombrePersona($r->personaAprendiz), 'identificacionEstudiante' => $r->personaAprendiz?->identificacion, 'codigoFicha' => $d->ficha?->codigo, 'idFicha' => $d->idFicha, 'idHorarioMateria' => null, 'nombreMateria' => 'Varias Materias', 'excusa' => ['tipoExcusa' => $r->tipoExcusa, 'observacion' => $r->observacion, 'fechaInicialJustificacion' => $r->fechaInicial, 'fechaFinalJustificacion' => $r->fechaFinal, 'urlDocumento' => $urlDocumento, ], ]; })->values();

        $data = collect($dataIndividuales)
            ->merge($dataRangos)
            ->sortByDesc(function ($item) {
                return $item['fechaClase'] ?? $item['fechaInicial'] ?? null;
            })
            ->values();

        return response()->json([
            'message' => 'Justificaciones pendientes',
            'data' => $data,
        ], 200);

    } catch (\Exception $e) {
        return response()->json([
            'message' => 'Error al obtener justificaciones pendientes',
            'error' => $e->getMessage(),
        ], 500);
    }
}

    /**
     * Instructor aprueba o deniega una justificación.
     */
    public function responderJustificacionAsistencia(Request $request): JsonResponse
{
    try {
        $request->validate([
            'idJustificacion' => 'required|integer',
            'tipoJustificacion' => 'nullable|in:individual,rango',
            'accion' => 'required|in:aprobar,denegar',
            'observacionInstructor' => 'nullable|string',
        ]);

        $user = KeyUtil::user() ?? auth()->user();
        $idPersonaInstructor = $user?->idpersona ?? $user?->persona?->id;
        $tipo = $request->tipoJustificacion ?? 'individual';

        if ($tipo === 'rango') { $detalle = \App\Models\JustificacionAsistenciaRangoDetalle::with('rango')->findOrFail($request->idJustificacion); $user = KeyUtil::user() ?? auth()->user(); $idPersonaInstructor = $user?->idpersona ?? $user?->persona?->id; $contratosIds = \App\Models\Contract::where('idpersona', $idPersonaInstructor)->pluck('id')->toArray(); $ficha = \App\Models\Ficha::find($detalle->idFicha); if (!in_array($ficha->idInstructorLider, $contratosIds)) { return response()->json(['message' => 'No est�s autorizado para aprobar este permiso de rango. Solo el instructor l�der puede hacerlo.'], 403); } $justificacion = $detalle->rango; $nuevoEstado = $request->accion === 'aprobar' ? 'APROBADO' : 'RECHAZADO'; $detalle->estado = $nuevoEstado; $detalle->idPersonaAutoriza = $idPersonaInstructor; $detalle->fechaRespuesta = today(); $detalle->observacionInstructor = $request->observacionInstructor; $detalle->save(); $todosDetalles = \App\Models\JustificacionAsistenciaRangoDetalle::where('idJustificacionAsistenciaRango', $justificacion->id)->get(); $pendientes = $todosDetalles->where('estado', 'PENDIENTE')->count(); $aprobados = $todosDetalles->where('estado', 'APROBADO')->count(); $rechazados = $todosDetalles->where('estado', 'RECHAZADO')->count(); $total = $todosDetalles->count(); if ($pendientes === $total) { $justificacion->estado = 'PENDIENTE'; } elseif ($aprobados === $total) { $justificacion->estado = 'APROBADO'; } elseif ($rechazados === $total) { $justificacion->estado = 'RECHAZADO'; } else { $justificacion->estado = 'PARCIAL'; } $justificacion->save(); if ($nuevoEstado === 'APROBADO') { $matriculasIds = \App\Models\MatriculaAcademica::whereHas('matricula', function($q) use ($justificacion) { $q->where('idPersona', $justificacion->idPersonaAprendiz); })->where('idFicha', $detalle->idFicha)->pluck('id')->toArray(); if (!empty($matriculasIds)) { $asistencias = \App\Models\Asistencia::whereIn('idMatriculaAcademica', $matriculasIds)->where('asistio', false)->whereHas('sesionMateria', function($q) use ($justificacion) { $q->whereDate('fechaSesion', '>=', $justificacion->fechaInicial)->whereDate('fechaSesion', '<=', $justificacion->fechaFinal); })->get(); foreach ($asistencias as $asist) { $excusa = \App\Models\Excusa::create(['tipoExcusa' => $justificacion->tipoExcusa, 'observacion' => $justificacion->observacion, 'urlDocumento' => $justificacion->archivoSoporte, 'fechaInicialJustificacion' => $justificacion->fechaInicial, 'fechaFinalJustificacion' => $justificacion->fechaFinal, ]); \App\Models\JustificacionInasistencia::updateOrCreate(['idAsistencia' => $asist->id], ['idExcusa' => $excusa->id, 'idMatriculaAcademica' => $asist->idMatriculaAcademica, 'idPersona' => $justificacion->idPersonaAprendiz, 'estado' => 'APROBADO', 'observacion' => $justificacion->observacion, 'archivoSoporte' => $justificacion->archivoSoporte, ]); } } } return response()->json(['message' => $nuevoEstado === 'APROBADO' ? 'Justificaci�n por rango aprobada' : 'Justificaci�n rechazada', 'data' => $detalle ], 200); }

        $justificacion = JustificacionInasistencia::with([
            'excusa',
            'asistencia.sesionMateria.horarioMateria.gradoMateria.materia',
            'asistencia.matriculaAcademica.matricula.person.usuario',
        ])->findOrFail($request->idJustificacion);

        $idHorarioMateria = $justificacion->asistencia?->sesionMateria?->idHorarioMateria;

        $horarioIds = $this->horarioMateriaIdsInstructor(
            null,
            $idHorarioMateria ? (int) $idHorarioMateria : null
        );

        if ($idHorarioMateria && !in_array((int) $idHorarioMateria, $horarioIds, true)) {
            return response()->json([
                'message' => 'No autorizado para responder esta justificación'
            ], 403);
        }

        $nuevoEstado = $request->accion === 'aprobar' ? 'APROBADO' : 'RECHAZADO';

        $observacion = trim((string) ($request->observacionInstructor ?? ''));

        if ($observacion !== '') {
            $justificacion->observacion = $observacion;
        }

        $justificacion->estado = $nuevoEstado;

        if ($idPersonaInstructor) {
            $justificacion->idPersona = $idPersonaInstructor;
        }

        $justificacion->save();

        $estudianteUsuario = $justificacion
            ->asistencia?->matriculaAcademica?->matricula?->person?->usuario;

        $idUsuarioEstudiante = $estudianteUsuario?->id;

        $materiaNombre = $justificacion
            ->asistencia?->sesionMateria?->horarioMateria?->gradoMateria?->materia?->nombreMateria
            ?? 'tu clase';

        if ($idUsuarioEstudiante) {
            $asunto = $nuevoEstado === 'APROBADO'
                ? 'Justificación aprobada'
                : 'Justificación rechazada';

            $mensaje = $nuevoEstado === 'APROBADO'
                ? "Tu justificación de inasistencia en {$materiaNombre} fue aprobada."
                : "Tu justificación de inasistencia en {$materiaNombre} fue rechazada."
                    . ($observacion !== '' ? " Motivo: {$observacion}" : '');

            $this->enviarNotificacion(
                (int) $idUsuarioEstudiante,
                (int) ($user->id ?? 0),
                $asunto,
                $mensaje,
                '/ambiente-virtual/mis-clases'
            );
        }

        return response()->json([
            'message' => $nuevoEstado === 'APROBADO'
                ? 'Justificación aprobada correctamente'
                : 'Justificación rechazada',
            'data' => $justificacion->fresh(['excusa']),
        ], 200);

    } catch (\Illuminate\Validation\ValidationException $e) {
        return response()->json([
            'message' => 'Error de validación',
            'errors' => $e->errors(),
        ], 422);

    } catch (\Exception $e) {
        return response()->json([
            'message' => 'Error al responder justificación',
            'error' => $e->getMessage(),
        ], 500);
    }
}

    /**
     * Listado global de asistencias del instructor (todas sus fichas).
     */
    public function asistenciasInstructorGlobal(Request $request): JsonResponse
{
    try {
        $idFicha = $request->integer('id_ficha') ?: null;
        $idHorarioMateria = $request->integer('id_horario_materia') ?: null;
        $busqueda = trim((string) $request->input('busqueda', ''));
        $fechaDesde = $request->input('fecha_desde');
        $fechaHasta = $request->input('fecha_hasta');

        $horarioIds = $this->horarioMateriaIdsInstructor($idFicha, $idHorarioMateria);

        if (empty($horarioIds)) {
            return response()->json([
                'message' => 'Sin clases asignadas',
                'data' => []
            ], 200);
        }

        $query = Asistencia::with([
            'justificacion.excusa',
            'sesionMateria.horarioMateria.ficha',
            'sesionMateria.horarioMateria.gradoMateria.materia.areaConocimiento',
            'matriculaAcademica.matricula.person',
        ])
            ->whereNotNull('asistio')
            ->whereHas('sesionMateria', function ($q) use ($horarioIds, $fechaDesde, $fechaHasta) {
                $q->whereIn('idHorarioMateria', $horarioIds);

                if ($fechaDesde) {
                    $q->whereDate('fechaSesion', '>=', $fechaDesde);
                }

                if ($fechaHasta) {
                    $q->whereDate('fechaSesion', '<=', $fechaHasta);
                }
            });

        if ($busqueda !== '') {
            $term = '%' . $busqueda . '%';

            $query->whereHas('matriculaAcademica.matricula.person', function ($q) use ($term) {
                $q->where('identificacion', 'like', $term)
                    ->orWhere(DB::raw("CONCAT(COALESCE(nombre1,''),' ',COALESCE(apellido1,''))"), 'like', $term)
                    ->orWhere(DB::raw("CONCAT(COALESCE(nombre1,''),' ',COALESCE(nombre2,''),' ',COALESCE(apellido1,''),' ',COALESCE(apellido2,''))"), 'like', $term);
            });
        }

        $asistencias = $query
            ->get()
            ->sortByDesc(fn ($a) => $a->sesionMateria?->fechaSesion)
            ->values();

        $registros = $asistencias->map(function (Asistencia $a) {
            $persona = $a->matriculaAcademica?->matricula?->person;
            $sesion = $a->sesionMateria;
            $horario = $sesion?->horarioMateria;
            $materia = $horario?->gradoMateria?->materia;

            $justificacion = $a->justificacion;
            $excusa = $justificacion?->excusa;
            $estadoJust = $justificacion?->estado;

            $permisoRango = null;

            if (!$a->asistio && $persona && $sesion?->fechaSesion) {
                $permisoRango = \App\Models\JustificacionAsistenciaRango::with('personaAutoriza')
                    ->where('idPersonaAprendiz', $persona->id)
                    ->whereIn('estado', ['APROBADO', 'ACEPTADO', 'JUSTIFICADO', 'PENDIENTE', 'RECHAZADO'])
                    ->whereDate('fechaInicial', '<=', $sesion->fechaSesion)
                    ->whereDate('fechaFinal', '>=', $sesion->fechaSesion)
                    ->orderByDesc('updated_at')
                    ->first();
            }

            $estadoPermiso = $permisoRango?->estado;

            $estado = $a->asistio
                ? 'Presente'
                : (
                    in_array($estadoJust, ['APROBADO', 'ACEPTADO', 'JUSTIFICADO'], true)
                        ? 'Inasistencia justificada'
                        : (
                            $estadoJust === 'PENDIENTE'
                                ? 'Justificación pendiente'
                                : (
                                    $estadoPermiso === 'APROBADO'
                                        ? 'Permiso aprobado'
                                        : (
                                            $estadoPermiso === 'PENDIENTE'
                                                ? 'Permiso pendiente'
                                                : (
                                                    $estadoJust === 'RECHAZADO' || $estadoPermiso === 'RECHAZADO'
                                                        ? 'Ausente (justificación rechazada)'
                                                        : 'Ausente'
                                                )
                                        )
                                )
                        )
                );

            $autorizadoPor = null;

            if ($permisoRango?->personaAutoriza) {
                $autorizadoPor = $this->nombrePersona($permisoRango->personaAutoriza);
            }

            return [
                'id' => $a->id,
                'idAsistencia' => $a->id,
                'fecha' => $sesion?->fechaSesion,

                'nombreEstudiante' => $this->nombrePersona($persona),
                'identificacion' => $persona?->identificacion,

                'codigoFicha' => $horario?->ficha?->codigo,
                'nombreArea' => $materia?->areaConocimiento?->nombreAreaConocimiento,
                'nombreMateria' => $materia?->nombreMateria,

                'asistio' => (bool) $a->asistio,
                'estado' => $estado,
                'estadoJustificacion' => $estadoJust,
                'estadoPermiso' => $estadoPermiso,

                'tipoJustificacion' => $permisoRango ? 'rango' : ($justificacion ? 'individual' : null),

                'excusa' => [
                    'tipoExcusa' => $permisoRango?->tipoExcusa ?? $excusa?->tipoExcusa,
                    'observacion' => $permisoRango?->observacion ?? $excusa?->observacion ?? $justificacion?->observacion,
                    'fechaInicialJustificacion' => $permisoRango?->fechaInicial ?? $excusa?->fechaInicialJustificacion,
                    'fechaFinalJustificacion' => $permisoRango?->fechaFinal ?? $excusa?->fechaFinalJustificacion,
                    'archivoSoporte' => $permisoRango?->archivoSoporte ?? $justificacion?->archivoSoporte,
                ],

                'permiso' => $permisoRango ? [
                    'id' => $permisoRango->id,
                    'estado' => $permisoRango->estado,
                    'fechaInicial' => $permisoRango->fechaInicial,
                    'fechaFinal' => $permisoRango->fechaFinal,
                    'tipoExcusa' => $permisoRango->tipoExcusa,
                    'observacion' => $permisoRango->observacion,
                    'archivoSoporte' => $permisoRango->archivoSoporte,
                    'autorizadoPor' => $autorizadoPor,
                    'fechaRespuesta' => $permisoRango->fechaRespuesta,
                    'observacionInstructor' => $permisoRango->observacionInstructor,
                ] : null,
            ];
        });

        return response()->json([
            'message' => 'Asistencias obtenidas',
            'data' => $registros,
        ], 200);

    } catch (\Exception $e) {
        return response()->json([
            'message' => 'Error al obtener asistencias',
            'error' => $e->getMessage(),
        ], 500);
    }
}
}



















