<?php

namespace App\Http\Controllers;

use App\Models\FaseProyectoRap;
use Illuminate\Http\Request;

class FaseProyectoRapController extends Controller
{
    public function index(Request $request)
    {
        $query = FaseProyectoRap::with('materia', 'actividadProyecto');

        if ($request->has('idFaseProyecto')) {
            $query->where('idFaseProyecto', $request->idFaseProyecto);
        }

        return response()->json($query->get());
    }

    public function store(Request $request)
    {
        $request->validate([
            'idFaseProyecto' => 'required|exists:faseProyecto,id',
            'idMaterias' => 'required|array|min:1',
            'idMaterias.*' => 'required|integer|exists:materia,id',
            'idActividadProyecto' => 'required|exists:actividadesProyecto,id',
        ]);

        $creados = [];
        $duplicados = [];

        foreach ($request->idMaterias as $idMateria) {
            $existe = FaseProyectoRap::where('idFaseProyecto', $request->idFaseProyecto)
                ->where('idMateria', $idMateria)
                ->where('idActividadProyecto', $request->idActividadProyecto)
                ->exists();

            if ($existe) {
                $duplicados[] = $idMateria;
                continue;
            }

            $rap = FaseProyectoRap::create([
                'idFaseProyecto' => $request->idFaseProyecto,
                'idMateria' => $idMateria,
                'idActividadProyecto' => $request->idActividadProyecto,
            ]);

            $creados[] = $rap->load('materia', 'actividadProyecto');
        }

        return response()->json([
            'creados' => $creados,
            'duplicados' => $duplicados,
        ], 201);
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
