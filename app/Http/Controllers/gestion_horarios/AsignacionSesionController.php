<?php

namespace App\Http\Controllers\gestion_horarios;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\AsignacionSesion;
use App\Models\DetalleRmi;
use App\Models\HorarioMateria;
use App\Models\SesionMateria;
use App\Models\Rmi;
use App\Models\Contract;
use App\Jobs\SendBasicEmail;

class AsignacionSesionController extends Controller
{
    /**
     * Asignar un reemplazo u horario compartido
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'idHorarioMateria' => 'required|integer',
            'tipoAsignacion'   => 'required|in:REEMPLAZO,HORARIO COMPARTIDO',
            'fechaInicio'      => 'required|date',
            'fechaFin'         => 'required|date|after_or_equal:fechaInicio',
            'idContrato'       => 'nullable|integer',
        ]);

        DB::beginTransaction();

        try {
            $asignacion = AsignacionSesion::create([
                'tipoAsignacion'   => $request->tipoAsignacion,
                'fechaInicio'      => $request->fechaInicio,
                'fechaFin'         => $request->fechaFin,
                'idContrato'       => $request->idContrato,
                'idHorarioMateria' => $request->idHorarioMateria,
                'observacion'      => $request->observacion,
            ]);

            // Si se asignó un contrato de una vez, duplicamos el horario para que tenga su propio RMI
            // Esto solo se hace para HORARIO COMPARTIDO, los REEMPLAZOS no generan RMI independiente
            if ($asignacion->idContrato && $asignacion->tipoAsignacion === 'HORARIO COMPARTIDO') {
                $clon = HorarioMateria::duplicarParaAsignacion($asignacion);
                if ($clon) {
                    $asignacion->update(['idHorarioMateria' => $clon->id]);
                }
            }

            // Si es un reemplazo, enviamos un email al instructor que va a realizar el reemplazo
            if ($asignacion->idContrato && $asignacion->tipoAsignacion === 'REEMPLAZO') {
                try {
                    $this->enviarEmailReemplazo($asignacion);
                } catch (\Exception $e) {
                    // No lanzamos error porque puede que falle el envío de correo, pero se creó la asignación
                }
            }

            DB::commit();

            return response()->json([
                'message' => 'Asignación creada correctamente',
                'data'    => $asignacion
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Error al asignar sesión',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar asignación
     */
    public function desasignarSesiones(Request $request): JsonResponse
    {
        try {
            $horarioIds = $request->input('horarios');
            $idContrato = $request->input('idContrato');

            DB::beginTransaction();

            $query = AsignacionSesion::whereIn('idHorarioMateria', $horarioIds);
            if ($idContrato) {
                $query->where('idContrato', $idContrato);
            }

            $asignaciones = $query->get();

            if ($asignaciones->isEmpty()) {
                return response()->json([
                    'message' => 'No se encontraron asignaciones para desasignar'
                ], 404);
            }

            foreach ($asignaciones as $asignacion) {
                // CASO 1: REEMPLAZO
                if ($asignacion->tipoAsignacion === 'REEMPLAZO') {
                    // Verificar si hay asistencias registradas en el periodo del reemplazo para ese contrato
                    $hasAsistencia = SesionMateria::where('idHorarioMateria', $asignacion->idHorarioMateria)
                        ->where('idContrato', $asignacion->idContrato)
                        ->whereBetween('fechaSesion', [$asignacion->fechaInicio, $asignacion->fechaFin])
                        ->whereHas('asistencia', fn($q) => $q->where('asistio', true))
                        ->exists();

                    if ($hasAsistencia) {
                        return response()->json([
                            'message' => 'No es posible desasignar este reemplazo porque ya tiene asistencias registradas.'
                        ], 422);
                    }

                    // Limpiar sesiones vinculadas al reemplazo (sin asistencias reales) en ese periodo
                    SesionMateria::where('idHorarioMateria', $asignacion->idHorarioMateria)
                        ->where('idContrato', $asignacion->idContrato)
                        ->whereBetween('fechaSesion', [$asignacion->fechaInicio, $asignacion->fechaFin])
                        ->each(function($sesion) {
                            $sesion->asistencia()->delete();
                            $sesion->delete();
                        });

                    $asignacion->delete();
                    continue;
                }

                // CASO 2: HORARIO COMPARTIDO
                $idHorarioAsig = $asignacion->idHorarioMateria;

                $horario = HorarioMateria::find($idHorarioAsig);
                if ($horario) {
                    // Verificar si hay asistencias antes de desasignar
                    $hasAsistencia = $horario->sesionMaterias()
                        ->whereHas('asistencia', fn($q) => $q->where('asistio', true))
                        ->exists();

                    if ($hasAsistencia) {
                        return response()->json([
                            'message' => 'No es posible desasignar este profesor porque ya tiene asistencias registradas en este horario.'
                        ], 422);
                    }

                    // Verificar si el RMI tiene reportes activos
                    $hasActiveRmi = $horario->detallesRmi()->where(function ($q) {
                        $q->where('estado', '!=', 'PENDIENTE')
                          ->orWhereNotNull('archivoPago')
                          ->orWhereNotNull('urlInforme');
                    })->exists();

                    if ($hasActiveRmi) {
                        return response()->json([
                            'message' => 'No es posible desasignar este profesor porque tiene reportes de RMI activos.'
                        ], 422);
                    }

                    // Limpiar asignaciones relacionadas
                    $asigsRelacionadas = AsignacionSesion::where('idHorarioMateria', $idHorarioAsig)->get();
                    foreach ($asigsRelacionadas as $asig) {
                        $asig->delete();
                    }

                    // Si es un clon (hay más de un registro para el mismo slot de RAP/Ficha)
                    $totalEnSlot = HorarioMateria::where('idFicha', $horario->idFicha)
                        ->where('idGradoMateria', $horario->idGradoMateria)
                        ->where('idDia', $horario->idDia)
                        ->where('horaInicial', $horario->horaInicial)
                        ->where('horaFinal', $horario->horaFinal)
                        ->count();

                    if ($totalEnSlot > 1) {
                        // Limpiar y borrar el clon
                        $horario->sesionMaterias()->each(function ($sesion) {
                            $sesion->asistencia()->delete();
                            $sesion->delete();
                        });
                        $horario->detallesRmi()->delete();
                        $horario->delete();
                    } else {
                        // Es el horario base: solo volverlo a PENDIENTE
                        $horario->update([
                            'idContrato' => null,
                            'estado'     => 'PENDIENTE'
                        ]);
                        // Limpiar sesiones y RMIs
                        $horario->sesionMaterias()->whereDoesntHave('asistencia')->delete();
                        // Limpiar RMIs pendientes
                        $horario->detallesRmi()->where('estado', 'PENDIENTE')->delete();
                    }
                }
                
                $asignacion->delete();
            }

            DB::commit();

            return response()->json([
                'message' => 'Asignación eliminada correctamente'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al eliminar asignación',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Envía un email al instructor que realizará el reemplazo
     */
    private function enviarEmailReemplazo(AsignacionSesion $asignacion): void
    {
        $contrato = Contract::with('persona')->find($asignacion->idContrato);
        $horario = HorarioMateria::with(['ficha', 'gradoMateria.materia', 'dia'])->find($asignacion->idHorarioMateria);

        if ($contrato && $contrato->persona && $horario) {
            $instructor = $contrato->persona;
            $nombreInstructor = $instructor->nombre1 . ' ' . $instructor->apellido1;
            $nombreMateria = $horario->gradoMateria->materia->nombreMateria ?? 'Materia';
            $codigoFicha = $horario->ficha->codigo ?? 'N/A';
            $diaSemana = $horario->dia->dia ?? 'N/A';

            $correo = $instructor->email;
            $asunto = "Asignación de Reemplazo: " . $nombreMateria;

            $mensaje = "Hola $nombreInstructor,\n\n"
                . "Se te ha asignado un reemplazo para la materia $nombreMateria.\n\n"
                . "Detalles del horario:\n"
                . "- Ficha: $codigoFicha\n"
                . "- Día: $diaSemana\n"
                . "- Hora: " . $horario->horaInicial . " - " . $horario->horaFinal . "\n"
                . "- Periodo: " . $asignacion->fechaInicio . " hasta " . $asignacion->fechaFin . "\n\n"
                . "Por favor revisa tu horario en la plataforma.\n\n"
                . "Gracias.";

            SendBasicEmail::dispatch($correo, $asunto, $mensaje);
        }
    }
}
