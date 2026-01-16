<?php

namespace App\Http\Controllers;

use App\Models\DetalleRevision;
use App\Util\KeyUtil;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class DetalleRevisionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): JsonResponse
    {
        $detalles = DetalleRevision::where('idCompany', KeyUtil::idCompany())->get();

        return response()->json($detalles);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nombre' => 'required|string',
            'tipoDetalle' => 'required|in:PARTE EXTERNA DE VEHICULO,MOTOR,INTERIOR DEL VEHICULO,DOCUMENTACION DEL VEHICULO'
        ]);

        $detalle = DetalleRevision::create([
            'nombre' => $validated['nombre'],
            'tipoDetalle' => $validated['tipoDetalle'],
            'idCompany' => KeyUtil::idCompany(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Detalle de revisión creado exitosamente',
            'data' => $detalle
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(int $id): JsonResponse
    {
        $detalle = DetalleRevision::with(['empresa', 'asignaciones'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $detalle
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $detalle = DetalleRevision::findOrFail($id);

        if ($detalle->idCompany !== KeyUtil::idCompany()) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permiso para editar este detalle de revisión.'
            ], 403);
        }

        $validated = $request->validate([
            'nombre' => 'nullable|string',
            'tipoDetalle' => 'nullable|in:PARTE EXTERNA DE VEHICULO,MOTOR,INTERIOR DEL VEHICULO,DOCUMENTACION DEL VEHICULO'
        ]);

        $detalle->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Detalle de revisión actualizado exitosamente',
            'data' => $detalle
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $detalle = DetalleRevision::findOrFail($id);
            $detalle->delete();

            return response()->json([
                'success' => true,
                'message' => 'Detalle de revisión eliminado exitosamente'
            ]);
        } catch (QueryException $e) {
            if ($e->getCode() === '23000') {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede eliminar este detalle porque está asignado a un vehículo o revisión.'
                ], 409);
            }

            return response()->json([
                'success' => false,
                'message' => 'Ocurrió un error al intentar eliminar el detalle.',
                'error' => $e->getMessage(),
            ], 500);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error inesperado al eliminar el detalle.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
