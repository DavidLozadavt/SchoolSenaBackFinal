<?php
namespace App\Http\Controllers;

use App\Enums\Estado;
use App\Models\MatriculaAcademica;
use App\Models\Status;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class MatriculaAcademicaController extends Controller
{
    private array $relations;
    private array $columns;

    /** Estados de matrícula.estado que permiten ver al aprendiz en Ambiente Virtual. */
    private const ESTADOS_MATRICULA_VIGENTES = [
        Estado::EN_FORMACION,
        Estado::ACTIVO,
        Estado::ENCURSO,
        Estado::CURSANDO,
        Estado::MATRICULADO,
    ];

    function __construct(){
        $this-> relations=[];
        $this-> columns=["*"];
    }
    

 
    public function getStudentByIdMateria(Request $request): JsonResponse
    {
        try {
            $dataEncoded = $request->input('data_encoded');
            $data = $dataEncoded ? json_decode($dataEncoded, true) : null;
 
            if (!$data || !isset($data['idMateria'])) {
                return response()->json([
                    'message' => 'El idMateria es requerido en data_encoded'
                ], 400);
            }
 
            $idMateria = $data['idMateria'];
            $idFicha   = $data['idFicha'] ?? null;
            // Si el frontend envía el horario exacto, usarlo directamente
            $idHorarioMateriaFijo = isset($data['idHorarioMateria']) ? (int)$data['idHorarioMateria'] : null;

            // Fuente de verdad: el RAP del horario (gm.idMateria), no el id enviado por navegación
            // (puede ser idMateriaPadre/competencia y dejar la lista vacía en técnicos/tecnólogos).
            if ($idHorarioMateriaFijo) {
                $horarioParaMateria = \App\Models\HorarioMateria::with('gradoMateria')->find($idHorarioMateriaFijo);
                if ($horarioParaMateria?->gradoMateria?->idMateria) {
                    $idMateria = (int) $horarioParaMateria->gradoMateria->idMateria;
                }
                if (!$idFicha && $horarioParaMateria?->idFicha) {
                    $idFicha = (int) $horarioParaMateria->idFicha;
                }
            }

            $placeholders = implode(',', array_fill(0, count(self::ESTADOS_MATRICULA_VIGENTES), '?'));
 
            $matriculasAcademicas = MatriculaAcademica::with([
                'matricula.person',
                'matricula.acudiente',
                'ficha',
                'materia'
            ])
            ->where('idMateria', $idMateria)
            // 1) Matrícula vigente en ficha (matricula.estado)
            // 2) Usuario de plataforma ACTIVO: activation_company_users.state_id = 1 (tabla estado)
            ->whereHas('matricula', function ($query) use ($placeholders) {
                $query->whereRaw('UPPER(TRIM(estado)) IN ('.$placeholders.')', self::ESTADOS_MATRICULA_VIGENTES)
                    ->whereHas('person.usuario.activationCompanyUsers', function ($q) {
                        $q->where('state_id', Status::ID_ACTIVE);
                    });
            });
 
            if ($idFicha) {
                $matriculasAcademicas->where('idFicha', $idFicha);
            }
 
            $result = $matriculasAcademicas->get();

            // Crear SesionMateria y Asistencia automáticamente para HOY si corresponde
            if ($idFicha && $idMateria && $result->isNotEmpty()) {
                $hoy     = now();
                $dbIdDia = ($hoy->dayOfWeek == 0) ? 7 : $hoy->dayOfWeek;

                // Prioridad: horario exacto del frontend (soporta misma materia 2 veces el mismo día)
                if ($idHorarioMateriaFijo) {
                    $horarioMateria = \App\Models\HorarioMateria::find($idHorarioMateriaFijo);
                } else {
                    // Fallback: buscar por ficha + materia + día actual
                    $horarioMateria = \App\Models\HorarioMateria::where('idFicha', $idFicha)
                        ->whereHas('gradoMateria', function ($query) use ($idMateria) {
                            $query->where('idMateria', $idMateria);
                        })
                        ->where('idDia', (int)$dbIdDia)
                        ->first();
                }

                if ($horarioMateria) {
                    // Solo verificar si existe, sin crearla. La creación se delega a updateAssistance.
                    $existsSesion = \App\Models\SesionMateria::where('idHorarioMateria', $horarioMateria->id)
                        ->whereDate('fechaSesion', $hoy->toDateString())
                        ->exists();
                }
            }

            $result->load('asistencias.sesionMateria');

            $hoyDate = today()->toDateString();
            foreach ($result as $item) {
                $permiso = null;
                $personId = $item->matricula?->person?->id;
                if ($personId && $idFicha) {
                    try {
                        $detalle = \App\Models\JustificacionAsistenciaRangoDetalle::whereHas('rango', function($q) use ($personId, $hoyDate) {
                            $q->where('idPersonaAprendiz', $personId)->where('fechaInicial', '<=', $hoyDate)->where('fechaFinal', '>=', $hoyDate);
                        })->where('idFicha', $idFicha)->whereIn('estado', ['PENDIENTE', 'APROBADO'])->with(['rango', 'personaAutoriza'])->first();
                        if ($detalle) {
                            $permiso = [
                                'tienePermiso' => true,
                                'estado' => $detalle->estado,
                                'fechaInicial' => $detalle->rango->fechaInicial,
                                'fechaFinal' => $detalle->rango->fechaFinal,
                                'tipoExcusa' => $detalle->rango->tipoExcusa,
                                'observacion' => $detalle->rango->observacion,
                                'archivoSoporteUrl' => $detalle->rango->archivoSoporte ? url('storage/' . $detalle->rango->archivoSoporte) : null,
                                'autorizadoPor' => $detalle->personaAutoriza ? trim($detalle->personaAutoriza->nombre1 . ' ' . $detalle->personaAutoriza->apellido1) : null,
                                'fechaRespuesta' => $detalle->fechaRespuesta,
                                'observacionInstructor' => $detalle->observacionInstructor
                            ];
                        }
                    } catch (\Throwable $e) {}
                }
                $item->permisoAsistencia = $permiso;
            }

            // Calcular nota parcial y porcentaje de avance para cada matrícula
            foreach ($result as $matricula) {
                // Debido a posibles cruces al momento de asignar actividades, buscamos por todas las matrículas académicas del estudiante
                $idsMaEstudiante = \DB::table('matriculaAcademica')
                    ->where('idMatricula', $matricula->idMatricula)
                    ->pluck('id');

                $actividadesEstudiante = \DB::table('calificacionActividad as ca')
                    ->join('actividades as a', 'ca.idActividad', '=', 'a.id')
                    ->whereIn('ca.idAMartriculaAcademica', $idsMaEstudiante)
                    ->where('a.idMateria', $matricula->idMateria)
                    ->get(['ca.*']);
                
                $totalAsignadas = $actividadesEstudiante->count();
                
                // Actividades ya calificadas (no nulos ni vacíos)
                $conNota = $actividadesEstudiante->filter(function($a) {
                    return !is_null($a->calificacionNumerica) && $a->calificacionNumerica !== '';
                })->count();
                
                if ($totalAsignadas > 0) {
                    $matricula->porcentaje_avance = round(($conNota / $totalAsignadas) * 100, 2);
                    
                    if ($conNota > 0) {
                        $sumaNotas = $actividadesEstudiante->sum(function($a) {
                            return is_numeric($a->calificacionNumerica) ? (float)$a->calificacionNumerica : 0.0;
                        });
                        $matricula->notaParcial = round($sumaNotas / $totalAsignadas, 2);
                    } else {
                        $matricula->notaParcial = null;
                    }
                } else {
                    $matricula->porcentaje_avance = null;
                    $matricula->notaParcial = null;
                }
            }

            return response()->json($result);
 
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error general',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Listar calificaciones de una ficha y materia filtradas por instructor (evaluador).
     * GET calificaciones_ficha_by_instructor/{idInstructor}?idFicha=...&idMateria=...&page=1&per_page=10&search=
     */
    public function calificacionesFichaByInstructor(Request $request, int $idInstructor): JsonResponse
    {
        try {
            $idFicha = (int) $request->input('idFicha');
            $idMateria = (int) $request->input('idMateria');
            $page = max(1, (int) $request->input('page', 1));
            $perPage = max(1, (int) $request->input('per_page', 50));
            $search = trim((string) $request->input('search', ''));

            $query = MatriculaAcademica::with([
                'matricula.person',
                'ficha',
                'materia',
                'evaluador',
            ]);

            // Filtrar por ficha (OBLIGATORIO para no traer estudiantes de otras fichas)
            if ($idFicha > 0) {
                $query->where('idFicha', $idFicha);
            }

            if ($idMateria > 0) {
                $query->where('idMateria', $idMateria);
            }

            // Filtrar solo por el evaluador asignado (sin orWhereNull para no traer estudiantes de otras fichas)
            if ($idInstructor > 0) {
                $query->where(function ($q) use ($idInstructor) {
                    $q->where('idEvaluador', $idInstructor)
                        ->orWhereNull('idEvaluador');
                });
            }

            if ($search !== '') {
                $query->whereHas('matricula.person', function ($q) use ($search) {
                    $q->where('nombre1', 'like', "%{$search}%")
                        ->orWhere('apellido1', 'like', "%{$search}%")
                        ->orWhere('identificacion', 'like', "%{$search}%");
                });
            }

            $result = $query
                ->orderBy('id', 'asc')
                ->paginate($perPage, ['*'], 'page', $page);

            // Calcular nota parcial y porcentaje de avance
            $result->getCollection()->transform(function ($matricula) use ($hoy) { $permiso = null; $idPersona = $matricula->matricula?->idPersona; $idFicha = $matricula->idFicha; if ($idPersona && $idFicha) { try { $detalle = \App\Models\JustificacionAsistenciaRangoDetalle::whereHas('rango', function($q) use ($idPersona, $hoy) { $q->where('idPersonaAprendiz', $idPersona)->whereDate('fechaInicial', '<=', $hoy)->whereDate('fechaFinal', '>=', $hoy); })->where('idFicha', $idFicha)->with(['rango', 'personaAutoriza'])->first(); if ($detalle) { $permiso = [ 'tienePermiso' => true, 'estado' => $detalle->estado, 'fechaInicial' => $detalle->rango->fechaInicial, 'fechaFinal' => $detalle->rango->fechaFinal, 'tipoExcusa' => $detalle->rango->tipoExcusa, 'observacion' => $detalle->rango->observacion, 'archivoSoporteUrl' => $detalle->rango->archivoSoporte ? url('storage/' . $detalle->rango->archivoSoporte) : null, 'autorizadoPor' => $detalle->personaAutoriza ? trim($detalle->personaAutoriza->nombre1 . ' ' . $detalle->personaAutoriza->apellido1) : null, 'fechaRespuesta' => $detalle->fechaRespuesta, 'observacionInstructor' => $detalle->observacionInstructor ]; } } catch (\Throwable $e) {} } $matricula->permisoAsistencia = $permiso; // Debido a posibles cruces al momento de asignar actividades, buscamos por todas las matrículas académicas del estudiante
                $idsMaEstudiante = \DB::table('matriculaAcademica')
                    ->where('idMatricula', $matricula->idMatricula)
                    ->pluck('id');

                $actividadesEstudiante = \DB::table('calificacionActividad as ca')
                    ->join('actividades as a', 'ca.idActividad', '=', 'a.id')
                    ->whereIn('ca.idAMartriculaAcademica', $idsMaEstudiante)
                    ->where('a.idMateria', $matricula->idMateria)
                    ->get(['ca.*']);

                $totalAsignadas = $actividadesEstudiante->count();

                // Actividades ya calificadas (no nulos ni vacíos)
                $conNota = $actividadesEstudiante->filter(function($a) {
                    return !is_null($a->calificacionNumerica) && $a->calificacionNumerica !== '';
                })->count();

                if ($totalAsignadas > 0) {
                    $matricula->porcentaje_avance = round(($conNota / $totalAsignadas) * 100, 2);
                    
                    if ($conNota > 0) {
                        $sumaNotas = $actividadesEstudiante->sum(function($a) {
                            return is_numeric($a->calificacionNumerica) ? (float)$a->calificacionNumerica : 0.0;
                        });
                        $matricula->notaParcial = round($sumaNotas / $totalAsignadas, 2);
                    } else {
                        $matricula->notaParcial = null;
                    }
                } else {
                    $matricula->porcentaje_avance = null;
                    $matricula->notaParcial = null;
                }

                
                return $matricula;
            });

            return response()->json($result);
        } catch (QueryException $th) {
            return response()->json([
                'message' => 'Error en la consulta',
                'error' => $th->getMessage()
            ], 500);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error general',
                'error' => $e->getMessage()
            ], 500);
        }
    }
 
    public function getUsuariosByFichaMateria($idFicha, $idMateria): JsonResponse
    {
        try {
            $matriculasAcademicas = MatriculaAcademica::with([
                'matricula.person.usuario',
                'matricula.acudiente',
                'ficha',
                'materia'
            ])
            ->where('idFicha', $idFicha)
            ->where('idMateria', $idMateria)
            ->get();
 
            $usuarios = [];
            foreach ($matriculasAcademicas as $matriculaAcademica) {
                $persona = $matriculaAcademica->matricula->persona;
                $usuario = $persona->usuario;
                
                if ($usuario) {
                    $usuarios[] = [
                        'id' => $usuario->id,
                        'name' => $usuario->name,
                        'email' => $usuario->email,
                        'persona' => [
                            'id' => $persona->id,
                            'identificacion' => $persona->identificacion,
                            'nombre1' => $persona->nombre1,
                            'apellido1' => $persona->apellido1,
                        ]
                    ];
                }
            }
 
            return response()->json([
                'usuarios' => $usuarios,
                'total' => count($usuarios)
            ]);
 
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error general',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getStudentByIdMateriaHorario(Request $request): JsonResponse
{
    try {
        $dataEncoded = $request->input('data_encoded');
        $data = $dataEncoded ? json_decode($dataEncoded, true) : null;

        // Validar que existan los datos requeridos
        if (!$data || !isset($data['idMateria'])) {
            return response()->json([
                'message' => 'El idMateria es requerido'
            ], 400);
        }

        $idMateria   = $data['idMateria'];
        $idPrograma  = $data['idPrograma'] ?? null;
        $idJornada   = $data['idJornada'] ?? null;
        $idFicha     = $data['idFicha'] ?? null;
        $horaInicial = $data['horaInicial'] ?? null;
        $horaFinal   = $data['horaFinal'] ?? null;

        $hoy = now()->format('Y-m-d');
        $diaActual = now();
        $diaDeLaSemana = $diaActual->dayOfWeek;
        $fechaActual = \Carbon\Carbon::createFromFormat('Y-m-d', $hoy);

        // Construir la consulta base
        $query = MatriculaAcademica::with([
            'matricula.person',
            'matricula.person.usuario',
            'matricula.acudiente',
            'ficha',
            'materia',
            'evaluador'
        ]);

        // Aplicar filtros
        $query->where('idMateria', $idMateria);

        $placeholders = implode(',', array_fill(0, count(self::ESTADOS_MATRICULA_VIGENTES), '?'));
        $query->whereHas('matricula', function ($q) use ($placeholders) {
            $q->whereRaw('UPPER(TRIM(estado)) IN ('.$placeholders.')', self::ESTADOS_MATRICULA_VIGENTES)
                ->whereHas('person.usuario.activationCompanyUsers', function ($uq) {
                    $uq->where('state_id', Status::ID_ACTIVE);
                });
        });

        if ($idFicha) {
            $query->where('idFicha', $idFicha);
        }

        // Si hay programa, filtrar por asignación
        if ($idPrograma) {
            $query->whereHas('matricula.asignacionPeriodoProgramaJornada.asignacionPeriodoPrograma', function ($q) use ($idPrograma) {
                $q->where('idPrograma', $idPrograma);
            });
        }

        // Si hay jornada, filtrar por jornada
        if ($idJornada) {
            $query->whereHas('matricula.asignacionPeriodoProgramaJornada', function ($q) use ($idJornada) {
                $q->where('idJornada', $idJornada);
            });
        }

        $matriculasAcademicas = $query->get();

        $matriculas = [];

        foreach ($matriculasAcademicas as $matriculaAcademica) {
            // Cargar relaciones adicionales según los filtros
            $relations = [
                'matricula.person.usuario.person',
                'matricula.acudiente',
                'asistencias.sesionMateria'
            ];

            // Agregar relaciones de jornada si aplica
            if ($idJornada) {
                $relations['matricula.asignacionPeriodoProgramaJornada'] = function ($query) use ($idJornada) {
                    $query->where('idJornada', $idJornada);
                };
                $relations['asignacionPeriodoProgramaJornada'] = function ($query) use ($idJornada) {
                    $query->where('idJornada', $idJornada);
                };
            }

            // Agregar relaciones de programa si aplica
            if ($idPrograma) {
                $relations['asignacionPeriodoProgramaJornada.asignacionPeriodoPrograma'] = function ($query) use ($idPrograma) {
                    $query->where('idPrograma', $idPrograma);
                };
                $relations['matricula.asignacionPeriodoProgramaJornada.asignacionPeriodoPrograma'] = function ($query) use ($idPrograma) {
                    $query->where('idPrograma', $idPrograma);
                };
            }

            // Agregar programaciones de mensajería si hay jornada y fecha
            if ($idJornada && $horaInicial && $horaFinal) {
                $relations['matricula.person.usuario.programacionesEstadoMensajeria'] = function ($query) use ($fechaActual, $diaDeLaSemana) {
                    return $query->whereDate('fechaInicial', $fechaActual)
                        ->whereDate('fechaFinal', '>=', $fechaActual)
                        ->whereHas('jornada.dias', function ($query) use ($diaDeLaSemana) {
                            $query->where('idDia', $diaDeLaSemana);
                        });
                };
            }
                    $matriculaAcademica->load($relations);
            $matriculas[] = $matriculaAcademica;
        }

        // Lógica para crear SesionMateria y Asistencia automáticamente para HOY si corresponde
        if ($idFicha && $idMateria && count($matriculas) > 0) {
            $hoy = now();
            $dbIdDia = ($hoy->dayOfWeek == 0) ? 7 : $hoy->dayOfWeek;

            // Buscar un horario de esa materia en la ficha para el día de hoy
            $horarioMateria = \App\Models\HorarioMateria::where('idFicha', $idFicha)
                ->whereHas('gradoMateria', function ($query) use ($idMateria) {
                    $query->where('idMateria', $idMateria);
                })
                ->where('idDia', (int)$dbIdDia)
                ->first();

            if ($horarioMateria) {
                // Solo verificar si existe, sin crearla
                $existsSesion = \App\Models\SesionMateria::where('idHorarioMateria', $horarioMateria->id)
                    ->whereDate('fechaSesion', $hoy->toDateString())
                    ->exists();
            }
        }

        \Illuminate\Database\Eloquent\Collection::make($matriculas)->load('asistencias.sesionMateria');

        return response()->json($matriculas);

    } catch (QueryException $th) {
        return response()->json([
            'message' => 'Error en la consulta',
            'error' => $th->getMessage()
        ], 500);
    } catch (\Exception $e) {
        return response()->json([
            'message' => 'Error general',
            'error' => $e->getMessage()
        ], 500);
    }
}

 public function getMateriasByContrato(Request $request): JsonResponse
{
    try {
        $idContrato = $request->input('idContrato');
        
        if (!$idContrato) {
            return response()->json([
                'message' => 'El idContrato es requerido'
            ], 400);
        }

        $materias = HorarioMateria::with([
            'gradoMateria.materia',
            'gradoMateria.gradoPrograma',
            'contrato.person',
            'asignacionPeriodoProgramaJornada.jornada'
        ])
        ->where('idContrato', $idContrato)
        ->where('estado', EstadoHorarioMateria::ASIGNADO)
        ->get();

        return response()->json($materias);

    } catch (QueryException $e) {
        return response()->json([
            'message' => 'Error en la consulta',
            'error' => $e->getMessage()
        ], 500);
    } catch (\Exception $e) {
        return response()->json([
            'message' => 'Error general',
            'error' => $e->getMessage()
        ], 500);
    }
}


}

