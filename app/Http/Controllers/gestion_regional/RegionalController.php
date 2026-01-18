<?php

namespace App\Http\Controllers\gestion_regional;

use App\Http\Controllers\Controller;
use App\Models\Regional;
use Illuminate\Http\Request;

class RegionalController extends Controller
{
    //
    public function store(Request $request)
    {
        try {
            $request->validate([
                'nombre' => 'required|string|max:255',
                'telefono' => 'required|string|max:20',
                'direccion' => 'required|string|max:255',
                'idDepartamento' => 'required|exists:departamento,id'
            ]);

            $nuevaRegional = Regional::create([
                'nombre' => $request->nombre,
                'telefono' => $request->telefono,
                'direccion' => $request->direccion,
                'idDepartamento' => $request->idDepartamento
            ]);

            return response()->json([
                'status' => 'success',
                'message' => '¡Regional guardada con éxito!',
                'data' => $nuevaRegional
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al guardar la regional: ' . $e->getMessage()
            ], 500);
        }
    }
    public function index()
    {
        $regionales = Regional::with('departamento')->select('id', 'nombre', 'telefono', 'direccion', 'idDepartamento');
        return response()->json($regionales->get());
    }
    public function update(Request $request, $id)
    {
        try {
            $request->validate([
                'nombre' => 'sometimes|string|max:255',
                'telefono' => 'sometimes|string|max:20',
                'direccion' => 'sometimes|string|max:255',
            ]);

            $regional = Regional::findOrFail($id);

            $regional->update(
                $request->only(['nombre', 'telefono', 'direccion'])
            );

            return response()->json([
                'status' => 'success',
                'message' => '¡Regional actualizada correctamente!',
                'data' => $regional->load('departamento')
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al actualizar la regional: ' . $e->getMessage()
            ], 500);
        }
    }
}
