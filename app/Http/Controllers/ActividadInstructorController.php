<?php

namespace App\Http\Controllers;

use App\Models\ActividadInstructor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ActividadInstructorController extends Controller
{
    public function index(Request $request)
    {
        $query = ActividadInstructor::with(['rmi']);

        if ($request->has('idRmi')) {
            $query->where('idRmi', $request->idRmi);
        }

        // Filtro adicional por contrato para aislar al instructor
        if ($request->has('idContrato')) {
            $query->where('idContrato', $request->idContrato);
        }

        return $query->get();
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'descripcion'  => 'required|string',
            'fechaInicial' => 'required|date',
            'fechaFinal'   => 'required|date|after_or_equal:fechaInicial',
            'numeroHoras'  => 'required|integer|min:0',
            'idRmi'        => 'required|exists:rmi,id',
            'idContrato'   => 'required|exists:contrato,id',
            'documento'    => 'nullable|file|mimes:pdf,doc,docx|max:5120',
        ]);

        if ($request->hasFile('documento')) {
            $validated['documento'] = $request->file('documento')
                ->store(ActividadInstructor::RUTA_DOCUMENTO, 'public');
        }

        return response()->json(ActividadInstructor::create($validated), 201);
    }

    public function show($id)
    {
        return ActividadInstructor::with(['rmi'])->findOrFail($id);
    }

    public function update(Request $request, $id)
    {
        $actividad = ActividadInstructor::findOrFail($id);
        if ($request->isMethod('POST')) {
            $request->merge(['_method' => 'PUT']);
        }

        $validated = $request->validate([
            'descripcion' => 'required|string',
            'fechaInicial' => 'required|date',
            'fechaFinal' => 'required|date|after_or_equal:fechaInicial',
            'numeroHoras' => 'required|integer|min:0',
            'idRmi' => 'required|exists:rmi,id',
            'idContrato'   => 'required|exists:contrato,id',
            'documento' => 'nullable|file|max:5120',
        ]);

        if ($request->hasFile('documento')) {
            // Elimina el archivo anterior si existe
            if ($actividad->documento) {
                Storage::disk('public')->delete($actividad->documento);
            }
            $validated['documento'] = $request->file('documento')
                ->store(ActividadInstructor::RUTA_DOCUMENTO, 'public');
        }

        $actividad->update($validated);

        return response()->json($actividad);
    }

    public function destroy($id)
    {
        $actividad = ActividadInstructor::findOrFail($id);

        if ($actividad->documento) {
            Storage::disk('public')->delete($actividad->documento);
        }

        $actividad->delete();

        return response()->json(['message' => 'Eliminado correctamente']);
    }

    // Endpoint separado para eliminar solo el documento sin borrar la actividad
    public function destroyDocumento($id)
    {
        $actividad = ActividadInstructor::findOrFail($id);

        if ($actividad->documento) {
            Storage::disk('public')->delete($actividad->documento);
            $actividad->update(['documento' => null]);
        }

        return response()->json(['message' => 'Documento eliminado correctamente']);
    }
}
