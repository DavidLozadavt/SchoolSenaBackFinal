<?php

namespace App\Http\Controllers\ambiente_virtual;

use App\Http\Controllers\Controller;
use App\Util\KeyUtil;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * E3: Calificación de actividades individual o por grupo con réplica.
 */
class CalificacionActividadController extends Controller
{
    /**
     * E3-HU1: Calificar actividad de forma individual por aprendiz.
     */
    public function calificarIndividual(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'idCalificacionActividad' => 'required|integer',
                'calificacionNumerica' => 'required|numeric|min:0',
                'ComentarioDocente' => 'nullable|string|max:2000',
            ]);

            if (!Schema::hasTable('calificacionActividad')) {
                return response()->json(['error' => 'Tabla no disponible'], 500);
            }

            $user = KeyUtil::user();
            $idPersona = $user?->idpersona ?? 1;

            $actualizado = DB::table('calificacionActividad')
                ->where('id', $validated['idCalificacionActividad'])
                ->update([
                    'calificacionNumerica' => (string) $validated['calificacionNumerica'],
                    'ComentarioDocente' => $validated['ComentarioDocente'] ?? null,
                    'fechaCalificacion' => now(),
                    'updated_at' => now(),
                ]);

            if (!$actualizado) {
                return response()->json(['error' => 'Registro no encontrado'], 404);
            }

            return response()->json(['message' => 'Calificación registrada']);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * E3-HU2: Calificar actividad por grupo y replicar a todos los integrantes.
     */
    public function calificarPorGrupo(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'idActividad' => 'required|exists:actividades,id',
                'idGrupo' => 'required|exists:grupos,id',
                'calificacionNumerica' => 'required|numeric|min:0',
                'ComentarioDocente' => 'nullable|string|max:2000',
            ]);

            if (!Schema::hasTable('calificacionActividad')) {
                return response()->json(['error' => 'Tabla no disponible'], 500);
            }

            $user = KeyUtil::user();
            $idPersona = $user?->idpersona ?? 1;

            $registros = DB::table('calificacionActividad')
                ->where('idActividad', $validated['idActividad'])
                ->where('idGrupo', $validated['idGrupo'])
                ->get();

            $count = 0;
            foreach ($registros as $r) {
                DB::table('calificacionActividad')
                    ->where('id', $r->id)
                    ->update([
                        'calificacionNumerica' => (string) $validated['calificacionNumerica'],
                        'ComentarioDocente' => $validated['ComentarioDocente'] ?? null,
                        'fechaCalificacion' => now(),
                        'updated_at' => now(),
                    ]);
                $count++;
            }

            return response()->json([
                'message' => "Calificación aplicada a {$count} integrante(s)",
                'integrantesAfectados' => $count,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Listar calificaciones de una actividad para una ficha.
     */
    public function listarPorActividad(int $idActividad, int $idFicha): JsonResponse
    {
        try {
            if (!Schema::hasTable('calificacionActividad')) {
                return response()->json([]);
            }

            $tableMa = Schema::hasTable('matriculaAcademica') ? 'matriculaAcademica' : 'matriculaacademica';
            $calificaciones = DB::table('calificacionActividad as ca')
                ->join($tableMa . ' as ma', 'ca.idAMartriculaAcademica', '=', 'ma.id')
                ->join('matricula as m', 'ma.idMatricula', '=', 'm.id')
                ->leftJoin('persona as p', 'm.idPersona', '=', 'p.id')
                ->where('ca.idActividad', $idActividad)
                ->where('ma.idFicha', $idFicha)
                ->select([
                    'ca.id',
                    'ca.idAMartriculaAcademica',
                    'ca.idGrupo',
                    'ca.calificacionNumerica',
                    'ca.ComentarioDocente',
                    'ca.fechaCalificacion',
                    'm.id as idMatricula',
                    DB::raw("CONCAT(COALESCE(p.nombre1,''), ' ', COALESCE(p.apellido1,'')) as nombreAprendiz"),
                ])
                ->get();

            return response()->json($calificaciones);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
