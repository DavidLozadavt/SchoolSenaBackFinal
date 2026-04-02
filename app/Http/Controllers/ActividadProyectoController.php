<?php

namespace App\Http\Controllers;

use App\Models\ActividadProyecto;
use Illuminate\Http\Request;

class ActividadProyectoController extends Controller
{
    public function index(Request $request)
    {
        $query = ActividadProyecto::with('faseProyecto.proyectoFormativo');

        if ($request->has('idFaseProyecto')) {
            $query->where('idFaseProyecto', $request->idFaseProyecto);
        }

        return response()->json($query->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'descripcionActividad' => 'required|string',
            'idFaseProyecto'       => 'required|exists:faseProyecto,id',
        ]);

        $actividad = ActividadProyecto::create($validated);
        return response()->json($actividad->load('faseProyecto.proyectoFormativo'), 201);
    }

    public function update(Request $request, $id)
    {
        $actividad = ActividadProyecto::findOrFail($id);

        $validated = $request->validate([
            'descripcionActividad' => 'sometimes|string',
        ]);

        $actividad->update($validated);
        return response()->json($actividad->load('faseProyecto.proyectoFormativo'));
    }

    public function destroy($id)
    {
        ActividadProyecto::findOrFail($id)->delete();
        return response()->json(['message' => 'Actividad eliminada correctamente.']);
    }
}
