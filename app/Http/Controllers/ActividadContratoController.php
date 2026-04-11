<?php

namespace App\Http\Controllers;

use App\Models\ActividadContrato;
use App\Models\Contract;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

class ActividadContratoController extends Controller
{
    /**
     * Obtener todas las actividades de un contrato específico
     */
    public function index(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'idContrato' => 'required|exists:contrato,id'
            ], [
                'idContrato.required' => 'El ID del contrato es requerido.',
                'idContrato.exists' => 'El contrato no existe.'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Error de validación.',
                    'errors' => $validator->errors()
                ], 422);
            }

            $contrato = Contract::where('id', $request->idContrato)
                ->with(['actividades' => function ($query) {
                    $query->orderBy('created_at', 'asc');
                }])
                ->first();

            if (!$contrato) {
                return response()->json([
                    'message' => 'No tienes permiso para acceder a este contrato o no existe.',
                    'actividades' => []
                ], 403);
            }

            return response()->json([
                'actividades' => $contrato->actividades,
                'contratoId' => $contrato->id
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener las actividades.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Crear una nueva actividad para un contrato específico
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'obligaciones' => 'required|string',
                'accionesRealizadas' => 'required|string',
                'evidencias' => 'required|string',
                'idContrato' => 'required|exists:contrato,id'
            ], [
                'obligaciones.required' => 'Las obligaciones son requeridas.',
                'accionesRealizadas.required' => 'Las acciones realizadas son requeridas.',
                'evidencias.required' => 'Las evidencias son requeridas.',
                'idContrato.required' => 'El contrato es requerido.',
                'idContrato.exists' => 'El contrato no existe.'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Error de validación.',
                    'errors' => $validator->errors()
                ], 422);
            }


            $actividad = ActividadContrato::create([
                'obligaciones' => strtoupper($request->obligaciones),
                'accionesRealizadas' => strtoupper($request->accionesRealizadas),
                'evidencias' => strtoupper($request->evidencias),
                'idContrato' => $request->idContrato
            ]);

            return response()->json([
                'message' => 'Actividad creada exitosamente.',
                'actividad' => $actividad
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al crear la actividad.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar una actividad existente
     */
    public function update(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'obligaciones' => 'required|string',
                'accionesRealizadas' => 'required|string',
                'evidencias' => 'required|string'
            ], [
                'obligaciones.required' => 'Las obligaciones son requeridas.',
                'accionesRealizadas.required' => 'Las acciones realizadas son requeridas.',
                'evidencias.required' => 'Las evidencias son requeridas.'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Error de validación.',
                    'errors' => $validator->errors()
                ], 422);
            }

            $actividad = ActividadContrato::findOrFail($id);


            $actividad->update([
                'obligaciones' => strtoupper($request->obligaciones),
                'accionesRealizadas' => strtoupper($request->accionesRealizadas),
                'evidencias' => strtoupper($request->evidencias)
            ]);

            return response()->json([
                'message' => 'Actividad actualizada exitosamente.',
                'actividad' => $actividad
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al actualizar la actividad.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar una actividad
     */
    public function destroy($id)
    {
        try {
            $actividad = ActividadContrato::findOrFail($id);

            $actividad->delete();

            return response()->json([
                'message' => 'Actividad eliminada exitosamente.'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al eliminar la actividad.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}