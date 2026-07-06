<?php

namespace App\Http\Controllers;

use App\Models\FaseProyectoMateria;
use App\Models\Materia;
use Illuminate\Http\Request;

class FaseProyectoMateriaController extends Controller
{
    /**
     * Devuelve las materias hijas de una materia padre dada.
     * GET /fase-proyecto-materia/materias?idMateriaPadre=X
     */
    public function getMaterias(Request $request)
    {
        // 1. Validamos que ingresen ambos IDs y que existan en la base de datos
        $request->validate([
            'idMateriaPadre' => 'required|integer|exists:materia,id',
            'idFaseProyecto' => 'required|integer|exists:faseProyecto,id',
        ]);

        // 2. Consultamos las materias hijas excluyendo las ya registradas en este proyecto
        $materias = Materia::where('idMateriaPadre', $request->idMateriaPadre)
            ->whereDoesntHave('faseProyectoMaterias.faseProyectoRap', function ($query) use ($request) {
                // Filtramos la exclusión mapeando la relación hasta llegar a la fase actual
                $query->where('idFaseProyecto', $request->idFaseProyecto);
            })
            ->get(['id', 'nombreMateria', 'descripcion', 'codigo', 'horas', 'creditos']);

        // 3. Retornamos la lista limpia
        return response()->json($materias);
    }

    /**
     * Asigna una o varias materias hijas a un FaseProyectoRap.
     * POST /fase-proyecto-materia
     * Body: { idFaseProyectoRap, idMaterias: number[] }
     */
    public function store(Request $request)
    {
        $request->validate([
            'idFaseProyectoRap' => 'required|exists:faseProyectoRap,id',
            'idMaterias' => 'required|array|min:1',
            'idMaterias.*' => 'required|integer|exists:materia,id',
        ]);

        $creados = [];
        $duplicados = [];

        foreach ($request->idMaterias as $idMateria) {
            $existe = FaseProyectoMateria::where('idFaseProyectoRap', $request->idFaseProyectoRap)
                ->where('idMateria', $idMateria)
                ->exists();

            if ($existe) {
                $duplicados[] = $idMateria;
                continue;
            }

            $fpm = FaseProyectoMateria::create([
                'idFaseProyectoRap' => $request->idFaseProyectoRap,
                'idMateria' => $idMateria,
            ]);

            $creados[] = $fpm->load('materia');
        }

        return response()->json([
            'creados' => $creados,
            'duplicados' => $duplicados,
        ], 201);
    }

    /**
     * Elimina una asignación de materia hija.
     * DELETE /fase-proyecto-materia/{id}
     */
    public function destroy($id)
    {
        FaseProyectoMateria::findOrFail($id)->delete();
        return response()->json(['message' => 'Materia desasignada correctamente.']);
    }
}
