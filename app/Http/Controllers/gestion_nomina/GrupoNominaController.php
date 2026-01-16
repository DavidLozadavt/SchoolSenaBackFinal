<?php

namespace App\Http\Controllers\gestion_nomina;

use App\Http\Controllers\Controller;
use App\Models\GrupoNomina;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GrupoNominaController extends Controller
{
    /**
     * Mostrar todos los grupos de nómina.
     */
    public function index()
    {
        try {
            $grupos = GrupoNomina::all();
            return response()->json($grupos, 200);
        } catch (\Exception $e) {

            return response()->json(['error' => 'Error al obtener los grupos de nómina.'], 500);
        }
    }

    /**
     * Crear un nuevo grupo de nómina.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombreGrupo' => 'required|string|max:300',
            'sabados' => 'nullable|boolean',
            'domingos' => 'nullable|boolean',
            'festivos' => 'nullable|boolean',
            'trabajoDiaPorMedio' => 'nullable|boolean',
            'periodoLiquidacion' => 'nullable|in:MENSUAL,QUINCENAL,SEMANAL',
        ]);

        DB::beginTransaction();

        try {
            $grupo = new GrupoNomina();
            $grupo->nombreGrupo = $validated['nombreGrupo'];
            $grupo->sabados = $validated['sabados'] ?? false;
            $grupo->domingos = $validated['domingos'] ?? false;
            $grupo->festivos = $validated['festivos'] ?? false;
            $grupo->trabajoDiaPorMedio = $validated['trabajoDiaPorMedio'] ?? false;
            $grupo->periodoLiquidacion = $validated['periodoLiquidacion'] ?? null;
            $grupo->save();

            DB::commit();
            return response()->json($grupo, 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json(['error' => 'Error al crear el grupo de nómina.'], 500);
        }
    }

    /**
     * Mostrar un grupo específico.
     */
    public function show($id)
    {
        try {
            $grupo = GrupoNomina::findOrFail($id);
            return response()->json($grupo, 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['error' => 'Grupo de nómina no encontrado.'], 404);
        } catch (\Exception $e) {

            return response()->json(['error' => 'Error al obtener el grupo de nómina.'], 500);
        }
    }

    /**
     * Actualizar un grupo existente.
     */
    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'nombreGrupo' => 'required|string|max:300',
            'sabados' => 'nullable|boolean',
            'domingos' => 'nullable|boolean',
            'festivos' => 'nullable|boolean',
            'trabajoDiaPorMedio' => 'nullable|boolean',
            'periodoLiquidacion' => 'nullable|in:MENSUAL,QUINCENAL,SEMANAL',
        ]);

        DB::beginTransaction();

        try {
            $grupo = GrupoNomina::findOrFail($id);
            $grupo->nombreGrupo = $validated['nombreGrupo'];
            $grupo->sabados = $validated['sabados'] ?? false;
            $grupo->domingos = $validated['domingos'] ?? false;
            $grupo->festivos = $validated['festivos'] ?? false;
            $grupo->trabajoDiaPorMedio = $validated['trabajoDiaPorMedio'] ?? false;
            $grupo->periodoLiquidacion = $validated['periodoLiquidacion'] ?? null;
            $grupo->save();

            DB::commit();
            return response()->json($grupo, 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json(['error' => 'Grupo de nómina no encontrado.'], 404);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json(['error' => 'Error al actualizar el grupo de nómina.'], 500);
        }
    }

    /**
     * Eliminar un grupo de nómina.
     */
    public function destroy($id)
    {
        DB::beginTransaction();

        try {
            $grupo = GrupoNomina::findOrFail($id);
            $grupo->delete();

            DB::commit();
            return response()->json(['message' => 'Grupo de nómina eliminado correctamente.'], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json(['error' => 'Grupo de nómina no encontrado.'], 404);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json(['error' => 'Error al eliminar el grupo de nómina.'], 500);
        }
    }
}
