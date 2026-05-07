<?php

namespace App\Http\Controllers\ambiente_virtual;

use App\Http\Controllers\Controller;
use App\Util\KeyUtil;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * E3: Calificación de actividades individual o por grupo con réplica.
 */
class CalificacionActividadController extends Controller
{
    /**
     * Descargar el archivo de entrega (forzar attachment).
     * Autorización mínima: el instructor que asignó (ca.idPersona).
     */
    public function descargarArchivo(int $idCalificacionActividad)
    {
        try {
            if (!Schema::hasTable('calificacionActividad')) {
                return response()->json(['error' => 'Tabla no disponible'], 500);
            }

            $user = KeyUtil::user();
            $idPersona = $user?->idpersona ?? null;
            if (!$idPersona) {
                return response()->json(['error' => 'Usuario autenticado sin persona asociada'], 401);
            }

            $row = DB::table('calificacionActividad')
                ->where('id', $idCalificacionActividad)
                ->select('id', 'archivo', 'idPersona')
                ->first();

            if (!$row) {
                return response()->json(['error' => 'Entrega no encontrada'], 404);
            }

            if ((int) $row->idPersona !== (int) $idPersona) {
                return response()->json(['error' => 'No tienes permiso para descargar este archivo'], 403);
            }

            $archivo = trim((string) ($row->archivo ?? ''));
            if ($archivo === '') {
                return response()->json(['error' => 'La entrega no tiene archivo adjunto'], 404);
            }

            if (!Storage::disk('public')->exists($archivo)) {
                return response()->json(['error' => 'Archivo no encontrado'], 404);
            }

            $fileName = basename($archivo);
            $absolutePath = Storage::disk('public')->path($archivo);
            $mime = Storage::disk('public')->mimeType($archivo) ?: 'application/octet-stream';

            return response()->download($absolutePath, $fileName, [
                'Content-Type' => $mime,
                'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
                'Access-Control-Expose-Headers' => 'Content-Disposition',
            ]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
    /**
     * E3-HU1: Calificar actividad de forma individual por aprendiz.
     * Actividades con evidencia: solo se permite calificar si el aprendiz adjuntó evidencia (archivo o comentario).
     * Actividades sin evidencia: se puede calificar sin evidencia.
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

            $ca = DB::table('calificacionActividad')
                ->where('id', $validated['idCalificacionActividad'])
                ->first();
            if (!$ca) {
                return response()->json(['error' => 'Registro no encontrado'], 404);
            }

            $actividad = DB::table('actividades')->where('id', $ca->idActividad)->first();
            if ($actividad && ($actividad->tipoActividad ?? '') === 'con evidencia') {
                $tieneEvidencia = !empty(trim($ca->archivo ?? '')) || !empty(trim($ca->ComentarioEstudiante ?? ''));
                if (!$tieneEvidencia) {
                    return response()->json([
                        'error' => 'Las actividades con evidencia requieren que el aprendiz adjunte una evidencia (archivo o enlace) antes de poder calificar.',
                    ], 422);
                }
            }

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
     * Actividades con evidencia: solo se califican los que tienen evidencia; si alguno no tiene, se rechaza todo.
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

            $actividad = DB::table('actividades')->where('id', $validated['idActividad'])->first();
            $registros = DB::table('calificacionActividad')
                ->where('idActividad', $validated['idActividad'])
                ->where('idGrupo', $validated['idGrupo'])
                ->get();

            if ($actividad && ($actividad->tipoActividad ?? '') === 'con evidencia') {
                $alMenosUnoConEvidencia = false;
                foreach ($registros as $r) {
                    $tieneEvidencia = !empty(trim($r->archivo ?? '')) || !empty(trim($r->ComentarioEstudiante ?? ''));
                    if ($tieneEvidencia) {
                        $alMenosUnoConEvidencia = true;
                        break;
                    }
                }
                if (!$alMenosUnoConEvidencia) {
                    return response()->json([
                        'error' => 'Las actividades con evidencia requieren que al menos un integrante del grupo haya realizado la entrega antes de calificar.',
                    ], 422);
                }
            }

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
                ->leftJoin('grupos as g', 'ca.idGrupo', '=', 'g.id')
                ->where('ca.idActividad', $idActividad)
                ->where('ma.' . $colFicha, $idFicha)
                ->select([
                    'ca.id as idCalificacionActividad',
                    'ca.idAMartriculaAcademica',
                    'ca.idGrupo',
                    'g.nombreGrupo',
                    'ca.calificacionNumerica',
                    'ca.calificacionEstandart',
                    'ca.ComentarioDocente',
                    'ca.ComentarioEstudiante',
                    'ca.archivo',
                    'ca.fechaCalificacion',
                    'ca.updated_at',
                    'm.id as idMatricula',
                    'p.identificacion',
                    'p.rutaFoto',
                    DB::raw("CONCAT(COALESCE(p.nombre1,''), ' ', COALESCE(p.nombre2,''), ' ', COALESCE(p.apellido1,''), ' ', COALESCE(p.apellido2,'')) as nombreAprendiz"),
                ])
                ->orderBy('ca.id', 'desc')
                ->get();

            $vistos = [];
            $result = [];
            foreach ($calificaciones as $c) {
                $idMat = $c->idMatricula ?? null;
                if ($idMat !== null && isset($vistos[$idMat])) {
                    continue;
                }
                $vistos[$idMat] = true;

                $entregado = !empty(trim($c->ComentarioEstudiante ?? '')) || !empty(trim($c->archivo ?? ''));
                $calificado = $c->calificacionNumerica !== null && $c->calificacionNumerica !== '';
                $estado = $calificado ? 'CALIFICADO' : ($entregado ? 'ENVIADO' : 'PENDIENTE');

                $result[] = [
                    'idCalificacionActividad' => $c->idCalificacionActividad,
                    'idAMartriculaAcademica' => $c->idAMartriculaAcademica,
                    'idMatricula' => $c->idMatricula,
                    'idGrupo' => $c->idGrupo,
                    'nombreGrupo' => $c->nombreGrupo ?? null,
                    'nombreAprendiz' => trim($c->nombreAprendiz ?? '') ?: 'Sin nombre',
                    'identificacion' => $c->identificacion ?? '',
                    'rutaFoto' => $c->rutaFoto ?? null,
                    'calificacionNumerica' => $c->calificacionNumerica,
                    'calificacionEstandart' => $c->calificacionEstandart,
                    'ComentarioDocente' => $c->ComentarioDocente,
                    'ComentarioEstudiante' => $c->ComentarioEstudiante,
                    'archivo' => $c->archivo,
                    'fechaCalificacion' => $c->fechaCalificacion,
                    /** Útil como referencia de última modificación del registro (p. ej. tras entrega). */
                    'fechaActualizacionRegistro' => $c->updated_at ?? null,
                    'estado' => $estado,
                ];
            }

            return response()->json(['data' => $result]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage(), 'data' => []], 500);
        }
    }

    /**
     * Ampliar actividad: actualizar SOLO fechaFinal en las calificaciones de esta actividad para esta ficha.
     * No afecta otras actividades. El estado se calcula por fecha inicio, fecha límite y hora actual.
     */
    public function ampliar(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'idActividad' => 'required|integer|exists:actividades,id',
                'idFicha' => 'required|integer',
                'fechaFinal' => 'required|date',
                'descripcionExtension' => 'nullable|string|max:2000',
            ]);

            if (!Schema::hasTable('calificacionActividad')) {
                return response()->json(['error' => 'Tabla no disponible'], 500);
            }

            $tableMa = Schema::hasTable('matriculaAcademica') ? 'matriculaAcademica' : 'matriculaacademica';
            $colFicha = Schema::hasColumn($tableMa, 'idFicha') ? 'idFicha' : (Schema::hasColumn($tableMa, 'idAsignacionPeriodoProgramaJornada') ? 'idAsignacionPeriodoProgramaJornada' : 'idFicha');

            $idsCa = DB::table('calificacionActividad as ca')
                ->join($tableMa . ' as ma', 'ca.idAMartriculaAcademica', '=', 'ma.id')
                ->where('ca.idActividad', $validated['idActividad'])
                ->where('ma.' . $colFicha, $validated['idFicha'])
                ->pluck('ca.id');

            if ($idsCa->isEmpty()) {
                return response()->json(['error' => 'No hay asignaciones para esta actividad en esta ficha'], 404);
            }

            $fechaFin = \Carbon\Carbon::parse($validated['fechaFinal'])->endOfDay();
            $observacion = \Illuminate\Support\Str::limit($validated['descripcionExtension'] ?? '', 250);

            DB::transaction(function () use ($idsCa, $fechaFin, $observacion) {
                // Solo actualizar las calificaciones de ESTA actividad en ESTA ficha
                DB::table('calificacionActividad')
                    ->whereIn('id', $idsCa->all())
                    ->update([
                        'fechaFinal' => $fechaFin->format('Y-m-d H:i:s'),
                        'updated_at' => now(),
                    ]);

                if (Schema::hasTable('ampliacionActividad')) {
                    foreach ($idsCa as $idCa) {
                        DB::table('ampliacionActividad')->insert([
                            'idCalificacionActividad' => $idCa,
                            'observacion' => $observacion ?: null,
                            'fechaExtendida' => $fechaFin->format('Y-m-d H:i:s'),
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }
            });

            return response()->json([
                'message' => 'Actividad ampliada correctamente',
                'registrosActualizados' => $idsCa->count(),
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
