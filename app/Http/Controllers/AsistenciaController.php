<?php
namespace App\Http\Controllers;

use App\Models\Asistencia;
use App\Models\MatriculaAcademica;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
class AsistenciaController extends Controller
{
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
            'asistencias'
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

            $asistencias = $matricula->asistencias;

            $asistidas = $asistencias->where('asistio', true)->count();
            $faltas = $asistencias->where('asistio', false)->count();

            $justificadas = $asistencias->where('asistio', false)->filter(function($asistencia) {
                return \App\Models\JustificacionInasistencia::where('idAsistencia', $asistencia->id)->where('estado', 'APROBADO')->exists();
            })->count();

            $countAsistencia += $asistidas;
            $faltas = $faltas - $justificadas;
            $countFaltas += $faltas;
            $countJustificadas += $justificadas;

            $materiaStats[] = [
                'nombreMateria' => $matricula->materia->nombreMateria ?? '',
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
            'asistencias'
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

            $asistidas = $matricula->asistencias
                            ->where('asistio', true)
                            ->count();

            $faltas = $matricula->asistencias
                            ->where('asistio', false)
                            ->count();

            $justificadas = $matricula->asistencias
                            ->where('asistio', false)
                            ->filter(function($asistencia) {
                                return \App\Models\JustificacionInasistencia::where('idAsistencia', $asistencia->id)->where('estado', 'APROBADO')->exists();
                            })
                            ->count();

            $countAsistencia += $asistidas;
            // Subtract justified absences from pure 'faltas'
            $faltas = $faltas - $justificadas;
            $countFaltas += $faltas;
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
      // 'idAsignacionPeriodoProgramaJornada' => 'nullable|integer',
      'idMateria' => 'nullable|integer',
      'idMatricula' => 'required|integer',
    ]);

    $idMatricula = $validatedData['idMatricula'];
    $idMateria = $validatedData['idMateria'];

    $matriculaAcademica = MatriculaAcademica::with('ficha')->where('idMatricula', $idMatricula)
      ->where('idMateria', $idMateria)
      ->first();

    if (!$matriculaAcademica) {
        return response()->json(['message' => 'Matrícula académica no encontrada'], 404);
    }

    $idFicha = $matriculaAcademica->idFicha;

    $hoy = now();
    $dbIdDia = ($hoy->dayOfWeek == 0) ? 7 : $hoy->dayOfWeek;

    // Find HorarioMateria for the given ficha and materia on TODAY
    $horarioMateria = \App\Models\HorarioMateria::where('idFicha', $idFicha)
        ->whereHas('gradoMateria', function ($query) use ($idMateria) {
            $query->where('idMateria', $idMateria);
        })
        ->where('idDia', $dbIdDia)
        ->first();

    // Fallback to any schedule if updating past attendance on off-days
    if (!$horarioMateria) {
        $horarioMateria = \App\Models\HorarioMateria::where('idFicha', $idFicha)
            ->whereHas('gradoMateria', function ($query) use ($idMateria) {
                $query->where('idMateria', $idMateria);
            })
            ->first();
    }

    if (!$horarioMateria) {
        return response()->json(['message' => 'No se encontró un horario asignado para esta materia y ficha'], 404);
    }

    // Try finding a session for today first
    $sesionMateria = \App\Models\SesionMateria::where('idHorarioMateria', $horarioMateria->id)
        ->whereDate('fechaSesion', today())
        ->first();

    // If no session today, check if WE SHOULD HAVE ONE (it's a scheduled day)
    if (!$sesionMateria && $horarioMateria->idDia == $dbIdDia) {
        // Create the session for today
        $lastSession = \App\Models\SesionMateria::where('idHorarioMateria', $horarioMateria->id)
            ->max('numeroSesion') ?? 0;
        
        $sesionMateria = \App\Models\SesionMateria::create([
            'numeroSesion' => $lastSession + 1,
            'idHorarioMateria' => $horarioMateria->id,
            'fechaSesion' => today()->toDateString(),
        ]);

        // Note: Individual attendance will be created below since $asistencia will be null
    }

    // Fallback: If still no session (e.g. it's not a scheduled day), find the most recent session for manual updates
    if (!$sesionMateria) {
        $sesionMateria = \App\Models\SesionMateria::where('idHorarioMateria', $horarioMateria->id)
            ->orderBy('fechaSesion', 'desc')
            ->first();
    }

    if (!$sesionMateria) {
        return response()->json(['message' => 'No hay sesiones programadas para el horario de esta materia.'], 404);
    }

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

    // ─── AUTO-REGISTRO DE INASISTENCIAS ───────────────────────────────────
    // Cuando se registra la asistencia de un estudiante, automáticamente
    // se crean registros asistio=false para todos los demás estudiantes de
    // la misma ficha+materia que aún no tengan registro en esta sesión.
    try {
        $todasLasMatriculas = MatriculaAcademica::where('idFicha', $idFicha)
            ->where('idMateria', $idMateria)
            ->where('id', '!=', $matriculaAcademica->id) // excluir al que ya se registró
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
        // No interrumpir el flujo principal si falla el auto-registro
        \Illuminate\Support\Facades\Log::warning('Auto-registro inasistencias falló: ' . $e->getMessage());
    }
    // ──────────────────────────────────────────────────────────────────────

    // Process Justification
    if ($request->has('justificada') && $request->justificada && !$request->asistio) {
        // Create Excusa
        $excusa = \App\Models\Excusa::create([
            'tipoExcusa' => $request->tipoExcusa ?? 'FUERZA MAYOR',
            'observacion' => $request->observacionExcusa ?? null,
            'urlDocumento' => $request->urlDocumento ?? null,
            'fechaInicialJustificacion' => today(),
            'fechaFinalJustificacion' => today(),
        ]);

        // Link with JustificacionInasistencia
        \App\Models\JustificacionInasistencia::create([
            'idAsistencia' => $asistencia->id,
            'idExcusa' => $excusa->id,
            'idMatriculaAcademica' => $matriculaAcademica->id,
            'idPersona' => auth()->user()->idpersona ?? null,
            'estado' => 'APROBADO', // By default since it's entered directly
            'observacion' => 'Justificada desde el registro de asistencia de clase'
        ]);
    }

    return response()->json($asistencia, $request->isMethod('put') ? 200 : 201);
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

          // Buscar la sesión existente para hoy
          $sesionMateria = \App\Models\SesionMateria::where('idHorarioMateria', $idHorarioMateria)
              ->whereDate('fechaSesion', today())
              ->first();

          if (!$sesionMateria) {
              return response()->json([
                  'message' => 'No existe una sesión programada para hoy en este horario.',
              ], 404);
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

          $creados = 0;

          foreach ($matriculas as $matricula) {
              // Solo crear si no existe ya un registro para esta sesión
              $existe = Asistencia::where('idMatriculaAcademica', $matricula->id)
                  ->where('idSesionMateria', $sesionMateria->id)
                  ->exists();

              if (!$existe) {
                  Asistencia::create([
                      'idMatriculaAcademica' => $matricula->id,
                      'idSesionMateria' => $sesionMateria->id,
                      'horaLLegada' => null,
                      'asistio' => false,
                  ]);
                  $creados++;
              }
          }

          return response()->json([
              'message' => "Clase iniciada. Se registraron {$creados} inasistencias por defecto.",
              'sesion' => $sesionMateria,
              'totalEstudiantes' => $matriculas->count(),
              'inasistenciasCreadas' => $creados,
          ], 200);

      } catch (\Exception $e) {
          return response()->json([
              'message' => 'Error al iniciar la clase',
              'error' => $e->getMessage()
          ], 500);
      }
  }
}