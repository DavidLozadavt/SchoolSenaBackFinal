<?php

namespace App\Http\Controllers\ambiente_virtual;

use App\Http\Controllers\Controller;
use App\Models\GrupoFicha;
use App\Models\Ficha;
use App\Models\HorarioMateria;
use App\Models\TipoGrupo;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class GruposFichaController extends Controller
{
    /**
     * Listar grupos de una ficha (RAPS).
     * idAsignacionPeriodoProgramaJornada = ficha.id
     */
    public function index(int $idFicha): JsonResponse
    {
        try {
            $ficha = Ficha::with('asignacion')->findOrFail($idFicha);
            $fechaFinRaps = $ficha->asignacion?->fechaFinalClases ?? null;

            $grupos = GrupoFicha::where('idAsignacionPeriodoProgramaJornada', $idFicha)
                ->with('tipoGrupo')
                ->orderBy('id', 'desc')
                ->get();

            return response()->json([
                'data' => $grupos,
                'fechaFinalClases' => $fechaFinRaps,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Obtener tipos de grupo e idGradoMateria para crear grupo.
     */
    public function datosCrear(int $idFicha): JsonResponse
    {
        try {
            Ficha::findOrFail($idFicha);

            $tiposGrupo = TipoGrupo::all(['id', 'nombreTipoGrupo']);
            $primerHorario = HorarioMateria::where('idFicha', $idFicha)->first();
            $idGradoMateria = $primerHorario?->idGradoMateria;

            return response()->json([
                'tiposGrupo' => $tiposGrupo,
                'idGradoMateria' => $idGradoMateria,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Crear un grupo para la ficha.
     */
    public function store(Request $request, int $idFicha): JsonResponse
    {
        try {
            Ficha::findOrFail($idFicha);

            $primerHorario = HorarioMateria::where('idFicha', $idFicha)->first();
            if (!$primerHorario) {
                return response()->json(['error' => 'La ficha no tiene horarios/materias asignados'], 422);
            }

            $validated = $request->validate([
                'nombreGrupo' => 'required|string|max:255',
                'cantidadParticipantes' => 'required|integer|min:1',
                'descripcion' => 'nullable|string',
                'idTipoGrupo' => 'required|exists:tipoGrupo,id',
            ]);

            $grupo = GrupoFicha::create([
                'nombreGrupo' => $validated['nombreGrupo'],
                'cantidadParticipantes' => $validated['cantidadParticipantes'],
                'descripcion' => $validated['descripcion'] ?? '',
                'estado' => 'ACTIVO',
                'idTipoGrupo' => $validated['idTipoGrupo'],
                'idAsignacionPeriodoProgramaJornada' => $idFicha,
                'idGradoMateria' => $primerHorario->idGradoMateria,
            ]);

            return response()->json($grupo, 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Mostrar un grupo.
     */
    public function show(int $idFicha, int $id): JsonResponse
    {
        try {
            $grupo = GrupoFicha::where('idAsignacionPeriodoProgramaJornada', $idFicha)
                ->with('tipoGrupo')
                ->findOrFail($id);
            return response()->json($grupo);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 404);
        }
    }

    /**
     * Actualizar un grupo.
     */
    public function update(Request $request, int $idFicha, int $id): JsonResponse
    {
        try {
            $grupo = GrupoFicha::where('idAsignacionPeriodoProgramaJornada', $idFicha)->findOrFail($id);

            $validated = $request->validate([
                'nombreGrupo' => 'sometimes|required|string|max:255',
                'cantidadParticipantes' => 'sometimes|required|integer|min:1',
                'descripcion' => 'nullable|string',
                'idTipoGrupo' => 'sometimes|required|exists:tipoGrupo,id',
            ]);

            $grupo->update($validated);
            return response()->json($grupo);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Eliminar un grupo.
     */
    public function destroy(int $idFicha, int $id): JsonResponse
    {
        try {
            $grupo = GrupoFicha::where('idAsignacionPeriodoProgramaJornada', $idFicha)->findOrFail($id);
            $grupo->delete();
            return response()->json(['message' => 'Grupo eliminado']);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
