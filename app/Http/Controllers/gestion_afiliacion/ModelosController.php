<?php

namespace App\Http\Controllers\gestion_afiliacion;

use App\Http\Controllers\Controller;
use App\Models\Modelo;
use Illuminate\Http\Request;

class ModelosController extends Controller
{
    /**
     * Muestra todos los modelos.
     */
    public function index()
    {
        $modelos = Modelo::all();
        return response()->json($modelos);
    }

    /**
     * Guarda un nuevo modelo.
     */
    public function store(Request $request)
    {
        $request->validate([
            'modelo' => 'required|unique:modelo,modelo|max:255',
            'descripcion' => 'nullable|string',
        ]);

        $modelo = Modelo::create($request->all());

        return response()->json([
            'message' => 'Modelo creado con éxito',
            'modelo' => $modelo
        ], 201);
    }


    /**
     * Muestra un modelo específico.
     */
    public function show($id)
    {
        $modelo = Modelo::find($id);

        if (!$modelo) {
            return response()->json(['message' => 'Modelo no encontrado'], 404);
        }

        return response()->json($modelo);
    }

    /**
     * Actualiza un modelo existente.
     */
    public function update(Request $request, $id)
    {
        $modelo = Modelo::find($id);

        if (!$modelo) {
            return response()->json(['message' => 'Modelo no encontrado'], 404);
        }

        $request->validate([
            'modelo' => 'required|unique:modelos,modelo,' . $id . '|max:255',
            'descripcion' => 'nullable|string',
        ]);

        $modelo->update($request->all());

        return response()->json([
            'message' => 'Modelo actualizado con éxito',
            'modelo' => $modelo
        ]);
    }


    /**
     * Elimina un modelo.
     */
    public function destroy($id)
    {
        $modelo = Modelo::find($id);

        if (!$modelo) {
            return response()->json(['message' => 'Modelo no encontrado'], 404);
        }

        $modelo->delete();

        return response()->json(['message' => 'Modelo eliminado con éxito']);
    }
}
