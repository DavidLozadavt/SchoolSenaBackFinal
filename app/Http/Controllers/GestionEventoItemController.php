<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Item;

class GestionEventoItemController extends Controller
{
    // 🔹 Listar
    public function index()
    {
        return response()->json(
            Item::orderBy('hora_inicio')->get()
        );
    }

    // 🔹 Ver uno
    public function show($id)
    {
        $item = Item::findOrFail($id);

        return response()->json($item);
    }

    // 🔹 Crear
    public function store(Request $request)
    {
        $request->validate([
            'nombreItem' => 'required|string|max:100',
            'descripcion' => 'nullable|string',
            'hora_inicio' => 'nullable|date',
            'hora_fin' => 'nullable|date|after_or_equal:hora_inicio',
        ]);

        $item = Item::create([
            'nombreItem' => $request->nombreItem,
            'descripcion' => $request->descripcion,
            'hora_inicio' => $request->hora_inicio,
            'hora_fin' => $request->hora_fin,
            'seleccionar' => false
        ]);

        return response()->json([
            'message' => 'Item creado correctamente',
            'data' => $item
        ], 201);
    }

    // 🔹 Actualizar
    public function update(Request $request, $id)
    {
        $item = Item::findOrFail($id);

        $request->validate([
            'nombreItem' => 'sometimes|string|max:100',
            'descripcion' => 'nullable|string',
            'hora_inicio' => 'nullable|date',
            'hora_fin' => 'nullable|date|after_or_equal:hora_inicio',
            'seleccionar' => 'nullable|boolean'
        ]);

        $item->update($request->only([
            'nombreItem',
            'descripcion',
            'hora_inicio',
            'hora_fin',
            'seleccionar'
        ]));

        return response()->json([
            'message' => 'Item actualizado correctamente',
            'data' => $item
        ]);
    }

    // 🔹 Eliminar
    public function destroy($id)
    {
        $item = Item::findOrFail($id);

        // 🔥 eliminar ejecuciones relacionadas
        $item->ejecuciones()->delete();

        // eliminar item
        $item->delete();

        return response()->json([
            'message' => 'Item eliminado correctamente'
        ]);
    }
}
