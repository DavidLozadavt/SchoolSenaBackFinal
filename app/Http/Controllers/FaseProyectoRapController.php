<?php

namespace App\Http\Controllers;

use App\Models\FaseProyectoRap;
use Illuminate\Http\Request;

class FaseProyectoRapController extends Controller
{
    public function index(Request $request)
    {
        $query = FaseProyectoRap::with('materia');

        if ($request->has('idFaseProyecto')) {
            $query->where('idFaseProyecto', $request->idFaseProyecto);
        }

        return response()->json($query->get());
    }

    public function store(Request $request)
    {
        $request->validate([
            'idFaseProyecto' => 'required|exists:faseProyecto,id',
            'idMateria'      => 'required|exists:materia,id',
        ]);

        // Evita duplicados
        $existe = FaseProyectoRap::where('idFaseProyecto', $request->idFaseProyecto)
            ->where('idMateria', $request->idMateria)
            ->exists();

        if ($existe) {
            return response()->json(['message' => 'Esta materia ya está asignada a la fase.'], 422);
        }

        $rap = FaseProyectoRap::create($request->only('idFaseProyecto', 'idMateria'));
        return response()->json($rap->load('materia'), 201);
    }

    public function destroy($id)
    {
        FaseProyectoRap::findOrFail($id)->delete();
        return response()->json(['message' => 'Materia desasignada correctamente.']);
    }
    public function getMaterias(Request $request)
    {
        $request->validate([
            'idPrograma' => 'required|integer|exists:programa,id'
        ]);

        $idPrograma = $request->idPrograma;

        $materias = \App\Models\AgregarMateriaPrograma::with('materia')
            ->where('idPrograma', $idPrograma)
            ->get()
            ->pluck('materia')
            ->map(function ($m) {
                return [
                    'id' => $m->id,
                    'nombreMateria' => $m->nombreMateria,
                    'descripcion' => $m->descripcion,
                    'codigo' => $m->codigo,
                ];
            });

        return response()->json($materias);
    }
}
