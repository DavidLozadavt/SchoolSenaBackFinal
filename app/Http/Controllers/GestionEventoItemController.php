<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Item;

class GestionEventoItemController extends Controller
{
    // 🔹 Listar
    public function index(Request $request)
    {
        $query = Item::orderBy('hora_inicio');
        
        if ($request->has('idEvento')) {
            $query->where('idEvento', $request->query('idEvento'));
        }

        return response()->json($query->get());
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
            'idEvento' => 'nullable|integer|exists:evento,idEvento',
        ]);

        $item = Item::create([
            'nombreItem' => $request->nombreItem,
            'descripcion' => $request->descripcion,
            'hora_inicio' => $request->hora_inicio,
            'hora_fin' => $request->hora_fin,
            'idEvento' => $request->idEvento,
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
            'idEvento' => 'nullable|integer|exists:evento,idEvento',
            'seleccionar' => 'nullable|boolean'
        ]);

        $item->update($request->only([
            'nombreItem',
            'descripcion',
            'hora_inicio',
            'hora_fin',
            'idEvento',
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
