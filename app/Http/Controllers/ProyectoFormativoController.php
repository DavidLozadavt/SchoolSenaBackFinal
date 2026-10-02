<?php

namespace App\Http\Controllers;

use App\Models\ProyectoFormativo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProyectoFormativoController extends Controller
{
    public function index(Request $request)
    {
        $query = ProyectoFormativo::with('programa');

        if ($request->has('idPrograma')) {
            $query->where('idPrograma', $request->idPrograma);
        }

        return response()->json($query->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombreProyecto' => 'required|string|max:255',
            'version'        => 'required|string|max:255',
            'estado'         => 'in:ACTIVO,INACTIVO',
            'idPrograma'     => 'required|exists:programa,id',
            'documento'      => 'nullable|file|mimes:pdf,doc,docx|max:10240',
        ]);

        if ($request->hasFile('documento')) {
            $validated['documento'] = $request->file('documento')
                ->store(ProyectoFormativo::RUTA_DOCUMENTO, 'public');
        }

        $proyecto = ProyectoFormativo::create($validated);
        return response()->json($proyecto->load('programa'), 201);
    }

    public function show($id)
    {
        $proyecto = ProyectoFormativo::with('programa')->findOrFail($id);
        return response()->json($proyecto);
    }

    public function update(Request $request, $id)
    {
        $proyecto = ProyectoFormativo::findOrFail($id);

        $validated = $request->validate([
            'nombreProyecto' => 'sometimes|string|max:255',
            'version'        => 'sometimes|string|max:255',
            'estado'         => 'sometimes|in:ACTIVO,INACTIVO',
            'idPrograma'     => 'sometimes|exists:programa,id',
            'documento'      => 'nullable|file|mimes:pdf,doc,docx|max:10240',
        ]);

        if ($request->hasFile('documento')) {
            if ($proyecto->documento) {
                Storage::disk('public')->delete($proyecto->documento);
            }
            $validated['documento'] = $request->file('documento')
                ->store(ProyectoFormativo::RUTA_DOCUMENTO, 'public');
        }

        $proyecto->update($validated);
        return response()->json($proyecto->load('programa'));
    }

    public function destroy($id)
    {
        $proyecto = ProyectoFormativo::findOrFail($id);

        if ($proyecto->documento) {
            Storage::disk('public')->delete($proyecto->documento);
        }

        $proyecto->delete();
        return response()->json(['message' => 'Proyecto formativo eliminado correctamente.']);
    }
}
