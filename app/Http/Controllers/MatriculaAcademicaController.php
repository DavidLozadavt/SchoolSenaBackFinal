<?php
namespace App\Http\Controllers;

use App\Models\MatriculaAcademica;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Database\QueryException;

class MatriculaAcademicaController extends Controller
{
    private array $relations;
    private array $columns;

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
 
            $matriculasAcademicas = MatriculaAcademica::with([
                'matricula.person',
                'matricula.acudiente',
                'ficha',
                'materia'
            ])
            ->where('idMateria', $idMateria);
 
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
                    // Verificar si ya existe la sesión de hoy para este horario exacto
                    $existsSesion = \App\Models\SesionMateria::where('idHorarioMateria', $horarioMateria->id)
                        ->whereDate('fechaSesion', $hoy->toDateString())
                        ->exists();

                    if (!$existsSesion) {
                        $lastSession = \App\Models\SesionMateria::where('idHorarioMateria', $horarioMateria->id)
                            ->max('numeroSesion') ?? 0;

                        $nuevaSesion = \App\Models\SesionMateria::create([
                            'numeroSesion'     => $lastSession + 1,
                            'idHorarioMateria' => $horarioMateria->id,
                            'fechaSesion'      => $hoy->toDateString(),
                        ]);

                        $asistenciaData = [];
                        foreach ($result as $matricula) {
                            $asistenciaData[] = [
                                'idSesionMateria'      => $nuevaSesion->id,
                                'idMatriculaAcademica' => $matricula->id,
                                'asistio'              => false,
                                'horaLLegada'          => null,
                                'created_at'           => $hoy,
                                'updated_at'           => $hoy,
                            ];
                        }

                        if (!empty($asistenciaData)) {
                            \App\Models\Asistencia::insert($asistenciaData);
                        }
                    }
                }
            }

            $result->load('asistencias.sesionMateria');

            return response()->json($result);
 
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
                // Verificar si ya existe la sesión de hoy
                $existsSesion = \App\Models\SesionMateria::where('idHorarioMateria', $horarioMateria->id)
                    ->whereDate('fechaSesion', $hoy->toDateString())
                    ->exists();

                if (!$existsSesion) {
                    $lastSession = \App\Models\SesionMateria::where('idHorarioMateria', $horarioMateria->id)
                        ->max('numeroSesion') ?? 0;
                    
                    $nuevaSesion = \App\Models\SesionMateria::create([
                        'numeroSesion' => $lastSession + 1,
                        'idHorarioMateria' => $horarioMateria->id,
                        'fechaSesion' => $hoy->toDateString(),
                    ]);

                    $asistenciaData = [];
                    foreach ($matriculas as $matricula) {
                        $asistenciaData[] = [
                            'idSesionMateria' => $nuevaSesion->id,
                            'idMatriculaAcademica' => $matricula->id,
                            'asistio' => false,
                            'horaLLegada' => null,
                            'created_at' => $hoy,
                            'updated_at' => $hoy,
                        ];
                    }

                    if (!empty($asistenciaData)) {
                        \App\Models\Asistencia::insert($asistenciaData);
                    }
                }
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