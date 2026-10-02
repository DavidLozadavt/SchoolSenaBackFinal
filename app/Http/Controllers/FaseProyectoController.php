<?php

namespace App\Http\Controllers;

use App\Models\FaseProyecto;
use Illuminate\Http\Request;

class FaseProyectoController extends Controller
{
    public function index(Request $request)
    {
        $query = FaseProyecto::query();

        if ($request->has('idProyectoFormativo')) {
            $query->where('idProyectoFormativo', $request->idProyectoFormativo);
        }

        return response()->json($query->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'descripcionFase'     => 'required|string|max:255',
            'idProyectoFormativo' => 'required|exists:proyectoFormativo,id',
        ]);

        $fase = FaseProyecto::create($validated);
        return response()->json($fase, 201);
    }

    public function update(Request $request, $id)
    {
        $fase = FaseProyecto::findOrFail($id);

        $validated = $request->validate([
            'descripcionFase' => 'sometimes|string|max:255',
        ]);

        $fase->update($validated);
        return response()->json($fase);
    }

    public function destroy($id)
    {
        FaseProyecto::findOrFail($id)->delete();
        return response()->json(['message' => 'Fase eliminada correctamente.']);
    }
    public function show($id)
    {
        $fase = FaseProyecto::with('proyectoFormativo')->findOrFail($id);
        return response()->json($fase);
    }
}
