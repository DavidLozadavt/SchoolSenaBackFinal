<?php

namespace App\Http\Controllers\gestion_afiliacion;
use App\Http\Controllers\Controller;
use App\Models\Marca;
use Illuminate\Http\Request;

class MarcasController extends Controller
{
    /**
     * Muestra todas las marcas.
     */
    public function index()
    {
        $marcas = Marca::all();
        return response()->json($marcas);
    }

    /**
     * Guarda una nueva marca.
     */
    public function store(Request $request)
    {
        $request->validate([
            'marca' => 'required|string|unique:marca,marca|max:255',
            'descripcion' => 'nullable|string',
        ]);

        $marca = Marca::create($request->all());

        return response()->json([
            'message' => 'Marca creada con éxito',
            'marca' => $marca
        ], 201);
    }

    /**
     * Muestra una marca específica.
     */
    public function show($id)
    {
        $marca = Marca::find($id);

        if (!$marca) {
            return response()->json(['message' => 'Marca no encontrada'], 404);
        }

        return response()->json($marca);
    }

    /**
     * Actualiza una marca existente.
     */
    public function update(Request $request, $id)
    {
        $marca = Marca::find($id);

        if (!$marca) {
            return response()->json(['message' => 'Marca no encontrada'], 404);
        }

        $request->validate([
            'nombre' => 'required|string|unique:marcas,nombre,' . $id . '|max:255',
            'descripcion' => 'nullable|string',
        ]);

        $marca->update($request->all());

        return response()->json([
            'message' => 'Marca actualizada con éxito',
            'marca' => $marca
        ]);
    }

    /**
     * Elimina una marca.
     */
    public function destroy($id)
    {
        $marca = Marca::find($id);

        if (!$marca) {
            return response()->json(['message' => 'Marca no encontrada'], 404);
        }

        $marca->delete();

        return response()->json(['message' => 'Marca eliminada con éxito']);
    }
}
