<?php

namespace App\Http\Controllers;

use App\Models\DescuentoPlanilla;
use App\Util\KeyUtil;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class DescuentoPlanillaController extends Controller
{
    /**
     * Listar todos los descuentos
     */
    public function index()
    {
        $idCompany = KeyUtil::idCompany();
        
        $descuentos = DescuentoPlanilla::where('idCompany', $idCompany)->get();

        return response()->json($descuentos, 200);
    }

    /**
     * Crear nuevo descuento
     */
    public function store(Request $request)
    {
        $idCompany = KeyUtil::idCompany();

        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string',
            'valor' => 'nullable|numeric|min:0',
            'porcentaje' => 'nullable|numeric|min:0|max:100',
            'obligatorio' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $request->all();
        $data['idCompany'] = $idCompany;
        $data['obligatorio'] = $request->input('obligatorio', true); 
        $descuento = DescuentoPlanilla::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Descuento creado exitosamente',
            'data' => $descuento
        ], 201);
    }

    /**
     * Mostrar un descuento específico
     */
    public function show($id)
    {
        $idCompany = KeyUtil::idCompany();

        $descuento = DescuentoPlanilla::where('id', $id)
            ->where('idCompany', $idCompany)
            ->with('empresa')
            ->first();

        if (!$descuento) {
            return response()->json([
                'success' => false,
                'message' => 'Descuento no encontrado'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $descuento
        ], 200);
    }

    /**
     * Actualizar descuento
     */
    public function update(Request $request, $id)
    {
        $idCompany = KeyUtil::idCompany();

        $descuento = DescuentoPlanilla::where('id', $id)
            ->where('idCompany', $idCompany)
            ->first();

        if (!$descuento) {
            return response()->json([
                'success' => false,
                'message' => 'Descuento no encontrado'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'nombre' => 'sometimes|required|string',
            'valor' => 'nullable|numeric|min:0',
            'porcentaje' => 'nullable|numeric|min:0|max:100',
            'obligatorio' => 'boolean', 
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $request->all();
        if (!$request->has('obligatorio')) {
            $data['obligatorio'] = $descuento->obligatorio; 
        }

        $descuento->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Descuento actualizado exitosamente',
            'data' => $descuento
        ], 200);
    }

    /**
     * Eliminar descuento
     */
   public function destroy($id)
{
    $idCompany = KeyUtil::idCompany();

    $descuento = DescuentoPlanilla::where('id', $id)
        ->where('idCompany', $idCompany)
        ->first();

    if (!$descuento) {
        return response()->json([
            'success' => false,
            'message' => 'Descuento no encontrado.'
        ], 404);
    }

    try {
        $descuento->delete();

        return response()->json([
            'success' => true,
            'message' => 'Descuento eliminado exitosamente.'
        ], 200);

    } catch (\Illuminate\Database\QueryException $e) {
        if ($e->getCode() == 23000) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede eliminar este descuento porque está asignado a una planilla.'
            ], 409);
        }

        return response()->json([
            'success' => false,
            'message' => 'Ocurrió un error al intentar eliminar el descuento.'
        ], 500);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Error inesperado: ' . $e->getMessage()
        ], 500);
    }
}

}
