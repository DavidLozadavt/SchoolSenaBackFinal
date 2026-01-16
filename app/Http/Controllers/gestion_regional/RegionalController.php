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
}
