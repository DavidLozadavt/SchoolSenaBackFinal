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
     * Listar aprendices asignados a una actividad para una ficha.
     * Incluye: foto, identificación, entrega (archivo, comentario), estado, nota.
     */
    public function listarPorActividad(int $idActividad, int $idFicha): JsonResponse
    {
        try {
            if (!Schema::hasTable('calificacionActividad')) {
                return response()->json(['data' => []]);
            }

            $tableMa = Schema::hasTable('matriculaAcademica') ? 'matriculaAcademica' : 'matriculaacademica';
            $colFicha = Schema::hasColumn($tableMa, 'idFicha') ? 'idFicha' : (Schema::hasColumn($tableMa, 'idAsignacionPeriodoProgramaJornada') ? 'idAsignacionPeriodoProgramaJornada' : 'idFicha');

            $calificaciones = DB::table('calificacionActividad as ca')
                ->join($tableMa . ' as ma', 'ca.idAMartriculaAcademica', '=', 'ma.id')
                ->join('matricula as m', 'ma.idMatricula', '=', 'm.id')
                ->leftJoin('persona as p', 'm.idPersona', '=', 'p.id')
                ->where('ca.idActividad', $idActividad)
                ->where('ma.' . $colFicha, $idFicha)
                ->select([
                    'ca.id as idCalificacionActividad',
                    'ca.idAMartriculaAcademica',
                    'ca.idGrupo',
                    'ca.calificacionNumerica',
                    'ca.calificacionEstandart',
                    'ca.ComentarioDocente',
                    'ca.ComentarioEstudiante',
                    'ca.archivo',
                    'ca.fechaCalificacion',
                    'm.id as idMatricula',
                    'p.identificacion',
                    'p.rutaFoto',
                    DB::raw("CONCAT(COALESCE(p.nombre1,''), ' ', COALESCE(p.nombre2,''), ' ', COALESCE(p.apellido1,''), ' ', COALESCE(p.apellido2,'')) as nombreAprendiz"),
                ])
                ->get();

            $result = [];
            foreach ($calificaciones as $c) {
                $entregado = !empty(trim($c->ComentarioEstudiante ?? '')) || !empty(trim($c->archivo ?? ''));
                $calificado = $c->calificacionNumerica !== null && $c->calificacionNumerica !== '';
                $estado = $calificado ? 'CALIFICADO' : ($entregado ? 'ENVIADO' : 'PENDIENTE');

                $result[] = [
                    'idCalificacionActividad' => $c->idCalificacionActividad,
                    'idAMartriculaAcademica' => $c->idAMartriculaAcademica,
                    'idMatricula' => $c->idMatricula,
                    'idGrupo' => $c->idGrupo,
                    'nombreAprendiz' => trim($c->nombreAprendiz ?? '') ?: 'Sin nombre',
                    'identificacion' => $c->identificacion ?? '',
                    'rutaFoto' => $c->rutaFoto ?? null,
                    'calificacionNumerica' => $c->calificacionNumerica,
                    'calificacionEstandart' => $c->calificacionEstandart,
                    'ComentarioDocente' => $c->ComentarioDocente,
                    'ComentarioEstudiante' => $c->ComentarioEstudiante,
                    'archivo' => $c->archivo,
                    'fechaCalificacion' => $c->fechaCalificacion,
                    'estado' => $estado,
                ];
            }

            return response()->json(['data' => $result]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage(), 'data' => []], 500);
        }
    }
}
