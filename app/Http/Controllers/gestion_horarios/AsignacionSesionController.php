<?php

namespace App\Http\Controllers\gestion_horarios;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\AsignacionSesion;
use App\Models\HorarioMateria;
use App\Models\SesionMateria;

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
            ]);

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
                $asignacion->idContrato = null;
                $asignacion->save();
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