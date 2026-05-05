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
                $idHorarioABorrar = $asignacion->idHorarioMateria;
                $idContratoAsig = $asignacion->idContrato;
                
                // Verificar si hay asistencias antes de desasignar
                $horarioCheck = HorarioMateria::find($idHorarioABorrar);
                if ($horarioCheck) {
                    $hasAsistencia = $horarioCheck->sesionMaterias()
                        ->whereHas('asistencia', fn($q) => $q->where('asistio', true))
                        ->exists();
                    if ($hasAsistencia) {
                        return response()->json([
                            'message' => 'No es posible desasignar este profesor porque ya tiene asistencias registradas en este horario.'
                        ], 422);
                    }
                }
                
                $asignacion->delete();

                // Si la asignación tenía un contrato, el horario asociado era un duplicado, lo borramos
                if ($idContratoAsig && $idHorarioABorrar) {
                    $horarioClon = HorarioMateria::find($idHorarioABorrar);
                    if ($horarioClon) {
                        // NO eliminamos el registro, solo quitamos el contrato
                        $horarioClon->idContrato = null;
                        $horarioClon->save();
                        
                        // Las sesiones se eliminan solo si no tienen asistencias
                        $horarioClon->sesionMaterias()
                            ->whereDoesntHave('asistencia')
                            ->delete();
                    }
                }
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