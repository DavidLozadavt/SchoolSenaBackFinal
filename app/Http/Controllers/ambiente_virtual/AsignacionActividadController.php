<?php

namespace App\Http\Controllers\ambiente_virtual;

use App\Http\Controllers\Controller;
use App\Models\Actividad;
use App\Models\GrupoFicha;
use App\Models\PlaneacionActividad;
use App\Util\KeyUtil;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Asignación masiva de actividades a aprendices y/o grupos (E1).
 * Usa calificacionactividad como registro de asignación.
 */
class AsignacionActividadController extends Controller
{
    /**
     * E1-HU1: Datos para asignar - actividades del banco, aprendices y grupos del RAP.
     */
    public function datos(int $idFicha): JsonResponse
    {
        try {
            $ficha = \App\Models\Ficha::with('asignacion')->findOrFail($idFicha);
            $idCompany = KeyUtil::idCompany();

            $actividades = Actividad::with(['materia', 'estado'])
                ->where('idCompany', $idCompany)
                ->orderBy('id', 'desc')
                ->get(['id', 'tituloActividad', 'tipoActividad', 'idMateria', 'descripcionActividad']);

            $aprendices = $this->aprendicesPorFicha($idFicha);

            $grupos = GrupoFicha::where('idAsignacionPeriodoProgramaJornada', $idFicha)
                ->where('estado', 'ACTIVO')
                ->with('tipoGrupo')
                ->orderBy('id', 'desc')
                ->get();

            $integrantesPorGrupo = [];
            if (Schema::hasTable('asignacionparticipantes')) {
                $counts = DB::table('asignacionparticipantes')
                    ->whereIn('idGrupo', $grupos->pluck('id'))
                    ->selectRaw('idGrupo, COUNT(*) as total')
                    ->groupBy('idGrupo')
                    ->pluck('total', 'idGrupo');
                foreach ($grupos as $g) {
                    $integrantesPorGrupo[$g->id] = (int) ($counts[$g->id] ?? 0);
                }
            }
            $grupos->each(function ($g) use ($integrantesPorGrupo) {
                $g->integrantesActuales = $integrantesPorGrupo[$g->id] ?? 0;
            });

            return response()->json([
                'actividades' => $actividades,
                'aprendices' => $aprendices,
                'grupos' => $grupos,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * E1-HU2, E1-HU3, E1-HU4: Asignar actividades masivamente.
     * aprendices: array de idMatricula o "todos"
     * grupos: array de idGrupo o "todos"
     * actividades: array de idActividad (requerido)
     */
    public function asignar(Request $request, int $idFicha): JsonResponse
    {
        try {
            $validated = $request->validate([
                'actividades' => 'required|array|min:1',
                'actividades.*' => 'exists:actividades,id',
                'aprendices' => 'nullable', // array de idMatricula o "todos"
                'grupos' => 'nullable',    // array de idGrupo o "todos"
                'fechaInicial' => 'nullable|date',
                'fechaFinal' => 'nullable|date|after_or_equal:fechaInicial',
            ]);

            $ficha = \App\Models\Ficha::findOrFail($idFicha);
            $user = KeyUtil::user();
            $idPersona = (int) ($user?->idpersona ?? 1);

            $idCorte = $this->obtenerIdCorte($idFicha);
            $fecha = isset($validated['fechaInicial'])
                ? \Carbon\Carbon::parse($validated['fechaInicial'])
                : now();
            $fechaFin = isset($validated['fechaFinal'])
                ? \Carbon\Carbon::parse($validated['fechaFinal'])
                : ($ficha->asignacion?->fechaFinalClases ?? $fecha->copy()->addMonths(3));

            $actividades = $validated['actividades'];
            $exitosas = 0;
            $omitidas = 0;
            $omitidosDetalle = [];

            $destinatariosAprendices = collect();
            if (!empty($validated['aprendices'])) {
                if ($validated['aprendices'] === 'todos' || (is_array($validated['aprendices']) && in_array('todos', $validated['aprendices']))) {
                    $aprendices = $this->aprendicesPorFicha($idFicha);
                    foreach ($aprendices as $a) {
                        $destinatariosAprendices->push([
                            'idMatriculaAcademica' => $a['idMatriculaAcademica'],
                            'idMatricula' => $a['id'],
                            'idMateria' => $a['idMateria'] ?? null,
                            'idGrupo' => null,
                        ]);
                    }
                } elseif (is_array($validated['aprendices'])) {
                    foreach ($validated['aprendices'] as $idMat) {
                        $ma = $this->matriculaAcademicaPorMatricula($idMat);
                        if ($ma) {
                            $destinatariosAprendices->push([
                                'idMatriculaAcademica' => $ma->id,
                                'idMatricula' => $idMat,
                                'idMateria' => $ma->idMateria ?? null,
                                'idGrupo' => null,
                            ]);
                        }
                    }
                }
            }

            $destinatariosGrupos = collect();
            $tieneIdMatricula = Schema::hasTable('asignacionparticipantes')
                && Schema::hasColumn('asignacionparticipantes', 'idMatricula');
            if (!empty($validated['grupos']) && $tieneIdMatricula) {
                $idsGrupo = ($validated['grupos'] === 'todos' || (is_array($validated['grupos']) && in_array('todos', $validated['grupos'])))
                    ? GrupoFicha::where('idAsignacionPeriodoProgramaJornada', $idFicha)->where('estado', 'ACTIVO')->pluck('id')
                    : collect(is_array($validated['grupos']) ? $validated['grupos'] : [])->flatten()->map(fn ($v) => (int) $v)->filter()->values();

                $participantes = $idsGrupo->isEmpty()
                    ? collect()
                    : DB::table('asignacionparticipantes')
                        ->whereIn('idGrupo', $idsGrupo->all())
                        ->whereNotNull('idMatricula')
                        ->get(['idMatricula', 'idGrupo']);
                foreach ($participantes as $p) {
                    $ma = $this->matriculaAcademicaPorMatricula($p->idMatricula);
                    if ($ma) {
                        $destinatariosGrupos->push([
                            'idMatriculaAcademica' => $ma->id,
                            'idMatricula' => $p->idMatricula,
                            'idMateria' => $ma->idMateria ?? null,
                            'idGrupo' => $p->idGrupo,
                        ]);
                    }
                }
            }

            $destinatarios = $destinatariosAprendices->merge($destinatariosGrupos)
                ->unique(fn ($d) => $d['idMatriculaAcademica'] . '-' . (is_scalar($d['idGrupo'] ?? null) ? $d['idGrupo'] : ''));

            if ($destinatarios->isEmpty()) {
                $msg = 'No se encontraron destinatarios. ';
                if (!empty($validated['grupos']) && empty($validated['aprendices'])) {
                    $msg .= 'Los grupos seleccionados no tienen participantes con matrícula académica. Verifica que los estudiantes se hayan unido a los grupos.';
                } elseif (!empty($validated['aprendices']) && empty($validated['grupos'])) {
                    $msg .= 'No se encontró matrícula académica para los estudiantes seleccionados.';
                } else {
                    $msg .= 'Selecciona estudiantes o grupos con participantes.';
                }
                return response()->json(['error' => $msg], 422);
            }

            foreach ($actividades as $idActividad) {
                $actividad = Actividad::find($idActividad);
                if (!$actividad) continue;
                $idMateria = $actividad->idMateria;

                foreach ($destinatarios as $dest) {
                    $idMa = is_array($dest['idMatriculaAcademica'] ?? null)
                        ? ($dest['idMatriculaAcademica'][0] ?? 0)
                        : ($dest['idMatriculaAcademica'] ?? 0);
                    $idMa = (int) $idMa;
                    if ($idMa <= 0) continue;
                    if ($idMateria && $dest['idMateria'] && $dest['idMateria'] != $idMateria) continue;

                    $yaExiste = DB::table('calificacionActividad')
                        ->where('idActividad', $idActividad)
                        ->where('idAMartriculaAcademica', $idMa)
                        ->exists();

                    if ($yaExiste) {
                        $omitidas++;
                        $idMat = $dest['idMatricula'] ?? '?';
                        $omitidosDetalle[] = "Actividad {$idActividad} - Aprendiz " . (is_scalar($idMat) ? $idMat : json_encode($idMat)) . " (ya asignada)";
                        continue;
                    }

                    $idGrupo = $dest['idGrupo'] ?? null;
                    $idGrupo = is_array($idGrupo) ? ($idGrupo[0] ?? null) : $idGrupo;
                    $idGrupo = $idGrupo !== null ? (int) $idGrupo : null;

                    $insertado = $this->crearCalificacionActividad(
                        (int) $idActividad,
                        $idMa,
                        $idGrupo,
                        $idPersona,
                        $idCorte,
                        $fecha,
                        $fechaFin
                    );
                    if ($insertado) $exitosas++;
                }
            }

            return response()->json([
                'message' => 'Asignación completada',
                'exitosas' => $exitosas,
                'omitidas' => $omitidas,
                'omitidosDetalle' => array_slice($omitidosDetalle, 0, 20),
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    private function aprendicesPorFicha(int $idFicha): array
    {
        $ficha = \App\Models\Ficha::find($idFicha);
        if (!$ficha) return [];

        $matriculas = collect();
        if (Schema::hasColumn('matricula', 'idFicha')) {
            $matriculas = DB::table('matricula')
                ->where('idFicha', $idFicha)
                ->whereIn('estado', ['ACTIVO', 'MATRICULADO', 'CURSANDO', 'EN FORMACION'])
                ->pluck('id');
        } elseif (Schema::hasColumn('matricula', 'idAsignacionPeriodoProgramaJornada')) {
            $idAppj = null;
            foreach (['asignacionPeriodoProgramaJornada', 'asignacionperiodoprogramajornada', 'asignacionJornada'] as $tbl) {
                if (!Schema::hasTable($tbl) || !Schema::hasColumn($tbl, 'idAsignacion')) continue;
                $q = DB::table($tbl)->where('idAsignacion', $ficha->idAsignacion);
                if (Schema::hasColumn($tbl, 'idJornada')) {
                    $q->where('idJornada', $ficha->idJornada);
                }
                $appj = $q->first();
                if ($appj) { $idAppj = $appj->id; break; }
            }
            if ($idAppj) {
                $matriculas = DB::table('matricula')
                    ->where('idAsignacionPeriodoProgramaJornada', $idAppj)
                    ->whereIn('estado', ['ACTIVO', 'MATRICULADO', 'CURSANDO', 'EN FORMACION'])
                    ->pluck('id');
            }
        }

        $tableMa = Schema::hasTable('matriculaAcademica') ? 'matriculaAcademica' : 'matriculaacademica';
        $ma = $matriculas->isNotEmpty()
            ? DB::table($tableMa)->whereIn('idMatricula', $matriculas)->get()
            : (Schema::hasColumn($tableMa, 'idFicha')
                ? DB::table($tableMa)->where('idFicha', $idFicha)->get()
                : collect());

        $personas = DB::table('persona')
            ->whereIn('id', $ma->pluck('idMatricula')->unique()->filter())
            ->get()
            ->keyBy('id');

        $result = [];
        foreach ($ma as $m) {
            $p = $personas[$m->idMatricula] ?? null;
            $result[] = [
                'id' => $m->idMatricula,
                'idMatriculaAcademica' => $m->id,
                'idMateria' => $m->idMateria,
                'nombre' => $p ? trim(($p->nombre1 ?? '') . ' ' . ($p->apellido1 ?? '')) : 'N/A',
            ];
        }
        return $result;
    }

    private function matriculaAcademicaPorMatricula($idMatricula)
    {
        $table = Schema::hasTable('matriculaAcademica') ? 'matriculaAcademica' : 'matriculaacademica';
        return DB::table($table)
            ->where('idMatricula', $idMatricula)
            ->first();
    }

    private function obtenerIdCorte(int $idFicha): int
    {
        if (!Schema::hasTable('configuracionCortes')) return 1;
        $corte = DB::table('configuracionCortes')->first();
        return $corte ? (int) $corte->id : 1;
    }

    private function crearCalificacionActividad(
        int $idActividad,
        int $idMatriculaAcademica,
        ?int $idGrupo,
        int $idPersona,
        int $idCorte,
        $fecha,
        $fechaFin
    ): bool {
        if (!Schema::hasTable('calificacionActividad')) return false;
        try {
            $fechaObj = $fecha instanceof \DateTimeInterface ? $fecha : \Carbon\Carbon::parse(is_scalar($fecha) ? $fecha : now());
            $fechaFinObj = $fechaFin instanceof \DateTimeInterface ? $fechaFin : \Carbon\Carbon::parse(is_scalar($fechaFin) ? $fechaFin : now());
            $idGrupoVal = $idGrupo !== null ? (int) $idGrupo : null;
            $data = [
                'idActividad' => (int) $idActividad,
                'idAMartriculaAcademica' => (int) $idMatriculaAcademica,
                'idGrupo' => $idGrupoVal,
                'idPersona' => (int) $idPersona,
                'idCorte' => (int) $idCorte,
                'fechaCreacion' => $fechaObj->format('Y-m-d'),
                'fechaInicial' => $fechaObj->format('Y-m-d H:i:s'),
                'fechaFinal' => $fechaFinObj->format('Y-m-d H:i:s'),
                'created_at' => now(),
                'updated_at' => now(),
            ];
            DB::table('calificacionActividad')->insert($data);
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }
}
