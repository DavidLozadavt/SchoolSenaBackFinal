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
            if ($asignacion->idContrato) {
                $clon = HorarioMateria::duplicarParaAsignacion($asignacion);
                if ($clon) {
                    $asignacion->update(['idHorarioMateria' => $clon->id]);
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

            foreach ($asignaciones as $asignacion) {
                $idHorarioAsig = $asignacion->idHorarioMateria;
                $idContratoAsig = $asignacion->idContrato;
                
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
                    $hasActiveRmi = $horario->detallesRmi()->where(function($q) {
                        $q->where('estado', '!=', 'PENDIENTE')
                          ->orWhereNotNull('archivoPago')
                          ->orWhereNotNull('urlInforme');
                    })->exists();

                    if ($hasActiveRmi) {
                        return response()->json([
                            'message' => 'No es posible desasignar este profesor porque tiene reportes de RMI activos.'
                        ], 422);
                    }

                    // Limpiar asignaciones
                    $asignaciones = AsignacionSesion::where('idHorarioMateria', $idHorarioAsig)->get();
                    foreach ($asignaciones as $asig) {
                        $asig->delete();
                    }

                    // Si es un clon (hay más de un registro para el mismo slot de RAP/Ficha)
                    $totalEnSlot = HorarioMateria::where('idFicha', $horario->idFicha)
                        ->where('idGradoMateria', $horario->idGradoMateria)
                        ->where('idDia', $horario->idDia)
                        ->where('horaInicial', $horario->horaInicial)
                        ->where('horaFinal', $horario->horaFinal)
                        ->where('fechaInicial', $horario->fechaInicial)
                        ->count();

                    if ($totalEnSlot > 1) {
                        // Limpiar y borrar el clon
                        $horario->sesionMaterias()->each(function($sesion) {
                            $sesion->asistencia()->delete();
                            $sesion->delete();
                        });
                        $horario->detallesRmi()->delete();
                        $horario->delete();
                    } else {
                        // Si es el único, solo quitamos el contrato (vuelve a ser placeholder)
                        $horario->update([
                            'idContrato' => null,
                            'estado'     => 'PENDIENTE'
                        ]);
                        // Limpiar sesiones sin asistencia
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
}