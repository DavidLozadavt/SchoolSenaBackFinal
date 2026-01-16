<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\ClaseVehiculo;
use Illuminate\Http\Request;

class ClaseVehiculoController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return response()->json(ClaseVehiculo::all());
    }

    /**
     * Guarda un nuevo tipo de vehículo.
     */
    public function store(Request $request)
    {
        $request->validate([
            'nombre' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
        ]);

        $claseVehiculo = ClaseVehiculo::create($request->all());

        return response()->json([
            'message' => 'Tipo de vehículo creado con éxito',
            'claseVehiculo' => $claseVehiculo
        ], 201);
    }

    /**
     * Muestra un tipo de vehículo específico.
     */
    public function show($id)
    {
        $claseVehiculo = ClaseVehiculo::find($id);

        if (!$claseVehiculo) {
            return response()->json(['message' => 'Clase de vehículo no encontrado'], 404);
        }

        return response()->json($claseVehiculo);
    }

    /**
     * Actualiza un tipo de vehículo existente.
     */
    public function update(Request $request, $id)
    {
        $claseVehiculo = ClaseVehiculo::find($id);

        if (!$claseVehiculo) {
            return response()->json(['message' => 'Tipo de vehículo no encontrado'], 404);
        }

        $request->validate([
            'nombre' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
        ]);

        $claseVehiculo->update($request->all());

        return response()->json([
            'message' => 'Clase de vehículo actualizado con éxito',
            'claseVehiculo' => $claseVehiculo
        ]);
    }

    /**
     * Elimina un tipo de vehículo.
     */
    public function destroy($id)
    {
        $claseVehiculo = ClaseVehiculo::find($id);

        if (!$claseVehiculo) {
            return response()->json(['message' => 'Clase de vehículo no encontrado'], 404);
        }

        $claseVehiculo->delete();

        return response()->json(['message' => 'Clase de vehículo eliminado con éxito']);
    }
}
