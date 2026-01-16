<?php

namespace App\Http\Controllers\gestion_afiliacion;

use App\Http\Controllers\Controller;
use App\Models\TipoVehiculo;
use Illuminate\Http\Request;

class TipoVehiculoController extends Controller
{
      /**
     * Muestra todos los tipos de vehículos.
     */
    public function index()
    {
        return response()->json(TipoVehiculo::all());
    }

    /**
     * Guarda un nuevo tipo de vehículo.
     */
    public function store(Request $request)
    {
        $request->validate([
            'tipo' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
        ]);

        $tipoVehiculo = TipoVehiculo::create($request->all());

        return response()->json([
            'message' => 'Tipo de vehículo creado con éxito',
            'tipoVehiculo' => $tipoVehiculo
        ], 201);
    }

    /**
     * Muestra un tipo de vehículo específico.
     */
    public function show($id)
    {
        $tipoVehiculo = TipoVehiculo::find($id);

        if (!$tipoVehiculo) {
            return response()->json(['message' => 'Tipo de vehículo no encontrado'], 404);
        }

        return response()->json($tipoVehiculo);
    }

    /**
     * Actualiza un tipo de vehículo existente.
     */
    public function update(Request $request, $id)
    {
        $tipoVehiculo = TipoVehiculo::find($id);

        if (!$tipoVehiculo) {
            return response()->json(['message' => 'Tipo de vehículo no encontrado'], 404);
        }

        $request->validate([
            'tipo' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
        ]);

        $tipoVehiculo->update($request->all());

        return response()->json([
            'message' => 'Tipo de vehículo actualizado con éxito',
            'tipoVehiculo' => $tipoVehiculo
        ]);
    }

    /**
     * Elimina un tipo de vehículo.
     */
    public function destroy($id)
    {
        $tipoVehiculo = TipoVehiculo::find($id);

        if (!$tipoVehiculo) {
            return response()->json(['message' => 'Tipo de vehículo no encontrado'], 404);
        }

        $tipoVehiculo->delete();

        return response()->json(['message' => 'Tipo de vehículo eliminado con éxito']);
    }
}
