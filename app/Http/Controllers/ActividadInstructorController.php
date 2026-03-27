<?php

namespace App\Http\Controllers;

use App\Models\ActividadInstructor;
use Illuminate\Http\Request;

class ActividadInstructorController extends Controller
{
    public function index(Request $request)
    {
        $query = ActividadInstructor::with(['rmi']);

        if ($request->has('idRmi')) {
            $query->where('idRmi', $request->idRmi);
        }

        return $query->get();
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'descripcion' => 'required|string',
            'fechaInicial' => 'required|date',
            'fechaFinal' => 'required|date|after_or_equal:fechaInicial',
            'numeroHoras' => 'required|integer|min:0',
            'idRmi' => 'required|exists:rmi,id',
        ]);

        $actividad = ActividadInstructor::create($validated);

        return response()->json($actividad, 201);
    }

    public function show($id)
    {
        return ActividadInstructor::with(['rmi'])->findOrFail($id);
    }

    public function update(Request $request, $id)
    {
        $actividad = ActividadInstructor::findOrFail($id);

        $validated = $request->validate([
            'descripcion' => 'required|string',
            'fechaInicial' => 'required|date',
            'fechaFinal' => 'required|date|after_or_equal:fechaInicial',
            'numeroHoras' => 'required|integer|min:0',
            'idRmi' => 'required|exists:rmi,id',
        ]);

        $actividad->update($validated);

        return response()->json($actividad);
    }

    public function destroy($id)
    {
        $actividad = ActividadInstructor::findOrFail($id);
        $actividad->delete();

        return response()->json(['message' => 'Eliminado correctamente']);
    }
}
