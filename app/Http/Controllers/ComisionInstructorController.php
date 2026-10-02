<?php

namespace App\Http\Controllers;

use App\Models\ComisionInstructor;
use Illuminate\Http\Request;

class ComisionInstructorController extends Controller
{
    public function index(Request $request)
    {
        $query = ComisionInstructor::with(['contrato', 'rmi']);

        if ($request->has('idContrato')) {
            $query->where('idContrato', $request->idContrato);
        }

        if ($request->has('idRmi')) {
            $query->where('idRmi', $request->idRmi);
        }

        return $query->get();
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'numeroViaje' => 'required|integer',
            'lugarDesplazamiento' => 'required|string|max:255',
            'fechaInicialDesplazamiento' => 'required|date',
            'fechaFinalDesplazamiento' => 'required|date|after_or_equal:fechaInicialDesplazamiento',
            'idContrato' => 'required|exists:contrato,id',
            'idRmi' => 'required|exists:rmi,id',
            'item' => 'nullable|string|max:250',
        ]);

        $comision = ComisionInstructor::create($validated);

        return response()->json($comision, 201);
    }

    public function show($id)
    {
        return ComisionInstructor::with(['contrato', 'rmi'])->findOrFail($id);
    }

    public function update(Request $request, $id)
    {
        $comision = ComisionInstructor::findOrFail($id);

        $validated = $request->validate([
            'numeroViaje' => 'required|integer',
            'lugarDesplazamiento' => 'required|string|max:255',
            'fechaInicialDesplazamiento' => 'required|date',
            'fechaFinalDesplazamiento' => 'required|date|after_or_equal:fechaInicialDesplazamiento',
            'idContrato' => 'required|exists:contrato,id',
            'idRmi' => 'required|exists:rmi,id',
            'item' => 'nullable|string|max:250',
        ]);

        $comision->update($validated);

        return response()->json($comision);
    }

    public function destroy($id)
    {
        $comision = ComisionInstructor::findOrFail($id);
        $comision->delete();

        return response()->json(['message' => 'Eliminado correctamente']);
    }
}
