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
     * Estudiantes (matrículas) en ficha y, por actividad, cuántos ya tienen registro en calificacionActividad.
     * Usado en la lista de actividades: el estado "Asignado" es informativo; no bloquea nuevas asignaciones
     * a estudiantes aún no cubiertos (los duplicados reales se omiten en asignar()).
     */
    public function cobertura(int $idFicha): JsonResponse
    {
        try {
            $tableMa = Schema::hasTable('matriculaAcademica') ? 'matriculaAcademica' : 'matriculaacademica';
            if (!Schema::hasTable('calificacionActividad')) {
                $ap = $this->aprendicesPorFicha($idFicha);
                $total = count($ap);

                return response()->json([
                    'totalEnFicha' => $total,
                    'porActividad' => (object) [],
                ]);
            }

            $colFicha = Schema::hasColumn($tableMa, 'idFicha')
                ? 'idFicha'
                : (Schema::hasColumn($tableMa, 'idAsignacionPeriodoProgramaJornada')
                    ? 'idAsignacionPeriodoProgramaJornada'
                    : 'idFicha');

            $idsMatFicha = DB::table($tableMa)
                ->where($colFicha, $idFicha)
                ->whereNotNull('idMatricula')
                ->distinct()
                ->pluck('idMatricula');
            $totalEnFicha = $idsMatFicha->unique()->count();
            if ($totalEnFicha === 0) {
                $ap = $this->aprendicesPorFicha($idFicha);
                $totalEnFicha = count($ap);
            }

            $asignadosPorActividad = collect();
            $entregaronPorActividad = collect();
            if (Schema::hasTable('calificacionActividad') && $totalEnFicha > 0) {
                $asignadosPorActividad = DB::table('calificacionActividad as ca')
                    ->join($tableMa . ' as ma', 'ca.idAMartriculaAcademica', '=', 'ma.id')
                    ->where('ma.' . $colFicha, $idFicha)
                    ->select('ca.idActividad', DB::raw('COUNT(DISTINCT ma.idMatricula) as asignados'))
                    ->groupBy('ca.idActividad')
                    ->get()
                    ->keyBy('idActividad');

                // Aprendices con entrega (archivo o comentario del estudiante), mismo criterio que listarPorActividad.
                $entregaCond = "(TRIM(COALESCE(ca.archivo, '')) <> '' OR TRIM(COALESCE(ca.ComentarioEstudiante, '')) <> '')";
                $entregaronPorActividad = DB::table('calificacionActividad as ca')
                    ->join($tableMa . ' as ma', 'ca.idAMartriculaAcademica', '=', 'ma.id')
                    ->where('ma.' . $colFicha, $idFicha)
                    ->whereRaw($entregaCond)
                    ->select('ca.idActividad', DB::raw('COUNT(DISTINCT ma.idMatricula) as entregaron'))
                    ->groupBy('ca.idActividad')
                    ->get()
                    ->keyBy('idActividad');
            }

            $porActividad = [];
            foreach ($asignadosPorActividad as $idAct => $row) {
                $asig = (int) ($row->asignados ?? 0);
                $faltan = max(0, $totalEnFicha - $asig);
                $ent = (int) ($entregaronPorActividad[(int) $idAct]->entregaron ?? 0);
                $pendEntrega = max(0, $asig - $ent);
                $porActividad[(int) $idAct] = [
                    'asignados' => $asig,
                    'faltan' => $faltan,
                    'total' => (int) $totalEnFicha,
                    'entregaron' => $ent,
                    'pendientesEntrega' => $pendEntrega,
                ];
            }

            return response()->json([
                'totalEnFicha' => (int) $totalEnFicha,
                'porActividad' => (object) $porActividad,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Listado de aprendices asignados vs pendientes por asignar para una actividad en la ficha.
     * Alineado con el conteo de cobertura (matrículas distintas con registro en calificacionActividad).
     */
    public function coberturaDetalleActividad(int $idFicha, int $idActividad): JsonResponse
    {
        try {
            [$tableMa, $colFicha] = $this->resolverMatriculaAcademicaFicha();

            $idsTodos = $this->idsMatriculaDistintasEnFicha($idFicha, $tableMa, $colFicha);
            $totalEnFicha = $idsTodos->count();

            if (!Schema::hasTable('calificacionActividad')) {
                return response()->json([
                    'totalEnFicha' => $totalEnFicha,
                    'asignados' => 0,
                    'faltan' => $totalEnFicha,
                    'detalleAsignados' => [],
                    'detallePendientes' => $this->detalleAprendicesPorMatriculas($idsTodos->all()),
                ]);
            }

            $idsAsignados = collect();
            if ($totalEnFicha > 0) {
                $idsAsignados = DB::table('calificacionActividad as ca')
                    ->join($tableMa . ' as ma', 'ca.idAMartriculaAcademica', '=', 'ma.id')
                    ->where('ca.idActividad', $idActividad)
                    ->where('ma.' . $colFicha, $idFicha)
                    ->distinct()
                    ->pluck('ma.idMatricula')
                    ->filter()
                    ->unique()
                    ->values();
            }

            $asignadosCount = $idsAsignados->count();
            $faltan = max(0, $totalEnFicha - $asignadosCount);

            $idsPendientes = $idsTodos->diff($idsAsignados)->values();

            return response()->json([
                'totalEnFicha' => $totalEnFicha,
                'asignados' => $asignadosCount,
                'faltan' => $faltan,
                'detalleAsignados' => $this->detalleAprendicesPorMatriculas($idsAsignados->all()),
                'detallePendientes' => $this->detalleAprendicesPorMatriculas($idsPendientes->all()),
            ]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

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

            // Producción puede no tener migrada la tabla `grupos`; sin esto el 500 impide devolver aprendices al modal.
            $grupos = collect();
            if (Schema::hasTable('grupos')) {
                $grupos = GrupoFicha::where('idAsignacionPeriodoProgramaJornada', $idFicha)
                    ->where('estado', 'ACTIVO')
                    ->with('tipoGrupo')
                    ->orderBy('id', 'desc')
                    ->get();

                $integrantesPorGrupo = [];
                $tblPart = $this->tablaParticipantes();
                if ($tblPart) {
                    $counts = DB::table($tblPart)
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
            }

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
     * Actividades asignadas a un estudiante específico en una ficha.
     * Usado desde la vista del instructor (ModalMisActividades).
     */
    public function actividadesEstudiante(Request $request, int $idFicha): JsonResponse
    {
        try {
            $idPersona = (int) $request->query('idPersona', 0);
            $idMateriaFiltro = $request->query('idMateria') ? (int) $request->query('idMateria') : null;

            if (!$idPersona) {
                return response()->json(['data' => [], 'message' => 'idPersona requerido']);
            }

            if (!Schema::hasTable('calificacionActividad')) {
                return response()->json(['data' => []]);
            }

            $tableMa = Schema::hasTable('matriculaAcademica') ? 'matriculaAcademica' : 'matriculaacademica';
            $colFicha = Schema::hasColumn($tableMa, 'idFicha') ? 'idFicha' : (Schema::hasColumn($tableMa, 'idAsignacionPeriodoProgramaJornada') ? 'idAsignacionPeriodoProgramaJornada' : null);

            // Obtener matrículas académicas del estudiante en esta ficha
            $queryMa = DB::table($tableMa . ' as ma')
                ->join('matricula as m', 'ma.idMatricula', '=', 'm.id')
                ->where('m.idPersona', $idPersona);

            if ($colFicha) {
                $queryMa->where('ma.' . $colFicha, $idFicha);
            }

            $idsMatriculaAcademica = $queryMa->pluck('ma.id');

            if ($idsMatriculaAcademica->isEmpty()) {
                return response()->json(['data' => []]);
            }

            $query = DB::table('calificacionActividad as ca')
                ->join($tableMa . ' as ma', 'ca.idAMartriculaAcademica', '=', 'ma.id')
                ->join('actividades as a', 'ca.idActividad', '=', 'a.id')
                ->leftJoin('persona as p_asigno', 'ca.idPersona', '=', 'p_asigno.id')
                ->leftJoin('materia as mat', 'a.idMateria', '=', 'mat.id')
                ->leftJoin('estado as e', 'a.idEstado', '=', 'e.id')
                ->whereIn('ca.idAMartriculaAcademica', $idsMatriculaAcademica);

            if ($idMateriaFiltro) {
                $query->where('a.idMateria', $idMateriaFiltro);
            }

            $calificaciones = $query->select([
                    'ca.id as idCalificacionActividad',
                    'ca.idActividad',
                    'ca.fechaInicial',
                    'ca.fechaFinal',
                    'ca.calificacionNumerica',
                    'ca.calificacionEstandart',
                    'ca.ComentarioDocente',
                    'ca.ComentarioEstudiante',
                    'ca.archivo',
                    'ca.idGrupo',
                    'p_asigno.id as idPersonaInstructor',
                    DB::raw("CONCAT(COALESCE(p_asigno.nombre1,''), ' ', COALESCE(p_asigno.nombre2,''), ' ', COALESCE(p_asigno.apellido1,''), ' ', COALESCE(p_asigno.apellido2,'')) as asignadoPor"),
                    'p_asigno.rutaFoto as instructorRutaFoto',
                    'a.tituloActividad',
                    'a.descripcionActividad',
                    'a.pathDocumentoActividad',
                    'a.tipoActividad',
                    'a.entregables',
                    'a.idMateria',
                    'mat.nombreMateria',
                    'mat.codigo as materiaCodigo',
                    'mat.descripcion as materiaDescripcion',
                    'e.estado as estadoActividad',
                ])
                ->orderBy('ca.fechaFinal', 'desc')
                ->get();

            $result = [];
            foreach ($calificaciones as $c) {
                $result[] = [
                    'id' => $c->idCalificacionActividad,
                    'idActividad' => $c->idActividad,
                    'codigo' => (string) $c->idActividad,
                    'instructorRutaFoto' => $c->instructorRutaFoto ?? null,
                    'actividad' => [
                        'id' => $c->idActividad,
                        'tituloActividad' => $c->tituloActividad,
                        'descripcionActividad' => $c->descripcionActividad,
                        'pathDocumentoActividad' => $c->pathDocumentoActividad,
                        'tipoActividad' => $c->tipoActividad,
                        'entregables' => $c->entregables,
                        'materia' => $c->nombreMateria ? [
                            'nombreMateria' => $c->nombreMateria,
                            'codigo' => $c->materiaCodigo,
                            'descripcion' => $c->materiaDescripcion,
                        ] : null,
                    ],
                    'tituloActividad' => $c->tituloActividad,
                    'descripcionActividad' => $c->descripcionActividad,
                    'tipoActividad' => $c->tipoActividad,
                    'entregables' => $c->entregables,
                    'idMateria' => $c->idMateria,
                    'materia' => $c->nombreMateria ? ['nombreMateria' => $c->nombreMateria, 'codigo' => $c->materiaCodigo] : null,
                    'estado' => $c->estadoActividad,
                    'fechaInicial' => $c->fechaInicial,
                    'fechaFinal' => $c->fechaFinal,
                    'calificacionNumerica' => $c->calificacionNumerica,
                    'calificacionEstandart' => $c->calificacionEstandart,
                    'ComentarioDocente' => $c->ComentarioDocente,
                    'ComentarioEstudiante' => $c->ComentarioEstudiante,
                    'archivo' => $c->archivo,
                    'asignadoPor' => trim($c->asignadoPor ?? '') ?: null,
                    'entregablesRealizados' => $c->archivo ? 'SÍ' : '-',
                ];
            }

            return response()->json(['data' => $result]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage(), 'data' => []], 500);
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
                    // Usar todas las matrículas académicas (una por materia) para que coincidan con cada actividad
                    $aprendices = $this->aprendicesPorFichaParaAsignacion($idFicha);
                    foreach ($aprendices as $a) {
                        $destinatariosAprendices->push([
                            'idMatriculaAcademica' => $a['idMatriculaAcademica'],
                            'idMatricula' => $a['id'],
                            'idMateria' => $a['idMateria'] ?? null,
                            'idGrupo' => null,
                        ]);
                    }
                } elseif (is_array($validated['aprendices'])) {
                    // Obtener TODAS las matrículas académicas por estudiante (una por materia)
                    // para que coincidan con la materia de cada actividad
                    $aprendices = $this->aprendicesPorFichaParaAsignacion($idFicha);
                    $idsMatSeleccionados = collect($validated['aprendices'])->map(fn ($v) => (int) $v)->filter()->values();
                    foreach ($aprendices as $a) {
                        if ($idsMatSeleccionados->contains($a['id'])) {
                            $destinatariosAprendices->push([
                                'idMatriculaAcademica' => $a['idMatriculaAcademica'],
                                'idMatricula' => $a['id'],
                                'idMateria' => $a['idMateria'] ?? null,
                                'idGrupo' => null,
                            ]);
                        }
                    }
                }
            }

            $destinatariosGrupos = collect();
            $idsGrupo = collect();
            if (!empty($validated['grupos'])) {
                if (Schema::hasTable('grupos')) {
                    $idsGrupo = ($validated['grupos'] === 'todos' || (is_array($validated['grupos']) && in_array('todos', $validated['grupos'])))
                        ? GrupoFicha::where('idAsignacionPeriodoProgramaJornada', $idFicha)->where('estado', 'ACTIVO')->pluck('id')
                        : collect(is_array($validated['grupos']) ? $validated['grupos'] : [])->flatten()->map(fn ($v) => (int) $v)->filter()->values();
                }

                $tblPart = $this->tablaParticipantes();
                $tieneIdMatricula = $tblPart && Schema::hasColumn($tblPart, 'idMatricula');
                if ($tieneIdMatricula && $idsGrupo->isNotEmpty()) {
                    $participantes = DB::table($tblPart)
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
                // Grupos vacíos: NO crear calificacionActividad. Solo se registra en asignacionActividadGrupo.
                // Cuando un aprendiz se una al grupo (unirse), recibirá las actividades automáticamente.
            }

            $destinatarios = $destinatariosAprendices->merge($destinatariosGrupos)
                ->unique(fn ($d) => $d['idMatriculaAcademica'] . '-' . (is_scalar($d['idGrupo'] ?? null) ? $d['idGrupo'] : ''));

            $tieneGruposSinParticipantes = $idsGrupo->isNotEmpty() && $destinatariosGrupos->count() < $idsGrupo->count() * count($actividades);
            if ($destinatarios->isEmpty() && $idsGrupo->isEmpty()) {
                return response()->json(['error' => 'Selecciona al menos un estudiante o un grupo.'], 422);
            }

            $tableMa = Schema::hasTable('matriculaAcademica') ? 'matriculaAcademica' : 'matriculaacademica';

            foreach ($actividades as $idActividad) {
                $actividad = Actividad::find($idActividad);
                if (!$actividad) continue;
                $idMateria = $actividad->idMateria;

                // Un solo destinatario por (idMatricula): preferir matriculaAcademica que coincida con idMateria de la actividad
                $destPorMatricula = [];
                foreach ($destinatarios as $dest) {
                    $idMat = $dest['idMatricula'] ?? null;
                    if ($idMat === null) continue;
                    $materiaCoincide = !$idMateria || !($dest['idMateria'] ?? null) || $dest['idMateria'] == $idMateria;
                    if (!isset($destPorMatricula[$idMat])) {
                        $destPorMatricula[$idMat] = $dest;
                    } else {
                        $actualMateria = $destPorMatricula[$idMat]['idMateria'] ?? null;
                        $actualCoincide = !$idMateria || $actualMateria == $idMateria;
                        if ($materiaCoincide && !$actualCoincide) {
                            $destPorMatricula[$idMat] = $dest;
                        }
                    }
                }
                foreach (array_values($destPorMatricula) as $dest) {
                    $idMa = is_array($dest['idMatriculaAcademica'] ?? null)
                        ? ($dest['idMatriculaAcademica'][0] ?? 0)
                        : ($dest['idMatriculaAcademica'] ?? 0);
                    $idMa = (int) $idMa;
                    if ($idMa <= 0) continue;

                    $idMat = $dest['idMatricula'] ?? null;

                    $yaExiste = DB::table('calificacionActividad as ca')
                        ->join($tableMa . ' as _ma', 'ca.idAMartriculaAcademica', '=', '_ma.id')
                        ->where('ca.idActividad', $idActividad)
                        ->where('_ma.idMatricula', $idMat)
                        ->exists();

                    if ($yaExiste) {
                        $omitidas++;
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
                    if ($insertado) {
                        $exitosas++;
                    }
                }
            }

            if ($idsGrupo->isNotEmpty() && Schema::hasTable('asignacionActividadGrupo')) {
                foreach ($actividades as $idActividad) {
                    foreach ($idsGrupo as $idGrupo) {
                        $yaExiste = DB::table('asignacionActividadGrupo')
                            ->where('idActividad', $idActividad)
                            ->where('idGrupo', $idGrupo)
                            ->exists();
                        if (!$yaExiste) {
                            DB::table('asignacionActividadGrupo')->insert([
                                'idActividad' => $idActividad,
                                'idGrupo' => $idGrupo,
                                'fechaInicial' => $fecha,
                                'fechaFinal' => $fechaFin,
                                'idPersona' => $idPersona,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                            $exitosas++;
                        }
                    }
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

        $tableMa = Schema::hasTable('matriculaAcademica') ? 'matriculaAcademica' : 'matriculaacademica';

        // Prioridad: matriculaAcademica por idFicha (misma fuente que getStudentByIdMateria)
        $ma = collect();
        if (Schema::hasColumn($tableMa, 'idFicha')) {
            $ma = DB::table($tableMa)->where('idFicha', $idFicha)->get();
        }

        // Fallback: matricula por ficha -> matriculaAcademica
        if ($ma->isEmpty()) {
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
            $ma = $matriculas->isNotEmpty()
                ? DB::table($tableMa)->whereIn('idMatricula', $matriculas)->get()
                : collect();
        }

        $idsMatricula = $ma->pluck('idMatricula')->unique()->filter()->values();
        $matriculasMap = DB::table('matricula')->whereIn('id', $idsMatricula)->get(['id', 'idPersona'])->keyBy('id');
        $idsPersona = $matriculasMap->pluck('idPersona')->unique()->filter()->values();
        $personas = DB::table('persona')->whereIn('id', $idsPersona)->get()->keyBy('id');

        $vistos = [];
        $result = [];
        foreach ($ma as $m) {
            $mat = $matriculasMap[$m->idMatricula] ?? null;
            $idPersona = $mat->idPersona ?? $m->idMatricula ?? null;
            if ($idPersona === null || isset($vistos[$idPersona])) continue;
            $vistos[$idPersona] = true;
            $p = $personas[$idPersona] ?? null;
            $nombreCompleto = $p
                ? trim(implode(' ', array_filter([
                    $p->nombre1 ?? null,
                    $p->nombre2 ?? null,
                    $p->apellido1 ?? null,
                    $p->apellido2 ?? null,
                ])))
                : '';
            $result[] = [
                'id' => $m->idMatricula,
                'idMatriculaAcademica' => $m->id,
                'idMateria' => $m->idMateria,
                'nombre' => $p ? trim(($p->nombre1 ?? '') . ' ' . ($p->apellido1 ?? '')) : 'N/A',
                'nombreCompleto' => $nombreCompleto !== '' ? $nombreCompleto : null,
                'identificacion' => $p ? (string) ($p->identificacion ?? '') : null,
                'rutaFoto' => $p->rutaFoto ?? null,
            ];
        }
        return $result;
    }

    /**
     * Todas las matrículas académicas de la ficha (una por materia por estudiante).
     * Usado al asignar "todos" para que cada actividad reciba estudiantes de su materia.
     */
    private function aprendicesPorFichaParaAsignacion(int $idFicha): array
    {
        $ficha = \App\Models\Ficha::find($idFicha);
        if (!$ficha) return [];

        $tableMa = Schema::hasTable('matriculaAcademica') ? 'matriculaAcademica' : 'matriculaacademica';
        $ma = collect();
        if (Schema::hasColumn($tableMa, 'idFicha')) {
            $ma = DB::table($tableMa)->where('idFicha', $idFicha)->get();
        }
        if ($ma->isEmpty()) {
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
                    if (Schema::hasColumn($tbl, 'idJornada')) $q->where('idJornada', $ficha->idJornada);
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
            $ma = $matriculas->isNotEmpty()
                ? DB::table($tableMa)->whereIn('idMatricula', $matriculas)->get()
                : collect();
        }

        $idsMatricula = $ma->pluck('idMatricula')->unique()->filter()->values();
        $matriculasMap = DB::table('matricula')->whereIn('id', $idsMatricula)->get(['id', 'idPersona'])->keyBy('id');
        $personas = DB::table('persona')->whereIn('id', $matriculasMap->pluck('idPersona')->unique()->filter())->get()->keyBy('id');

        $result = [];
        foreach ($ma as $m) {
            $mat = $matriculasMap[$m->idMatricula] ?? null;
            $idPersona = $mat->idPersona ?? null;
            $p = $idPersona ? ($personas[$idPersona] ?? null) : null;
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
            \Illuminate\Support\Facades\Log::warning('AsignacionActividad: fallo al crear calificacionActividad', [
                'error' => $e->getMessage(),
                'idActividad' => $idActividad,
                'idMatriculaAcademica' => $idMatriculaAcademica,
            ]);
            return false;
        }
    }

    private function tablaParticipantes(): ?string
    {
        if (Schema::hasTable('asignacionParticipantes')) {
            return 'asignacionParticipantes';
        }
        if (Schema::hasTable('asignacionparticipantes')) {
            return 'asignacionparticipantes';
        }
        return null;
    }

    /** @return array{0: string, 1: string} [tabla matriculaAcademica, columna ficha] */
    private function resolverMatriculaAcademicaFicha(): array
    {
        $tableMa = Schema::hasTable('matriculaAcademica') ? 'matriculaAcademica' : 'matriculaacademica';
        $colFicha = Schema::hasColumn($tableMa, 'idFicha')
            ? 'idFicha'
            : (Schema::hasColumn($tableMa, 'idAsignacionPeriodoProgramaJornada')
                ? 'idAsignacionPeriodoProgramaJornada'
                : 'idFicha');

        return [$tableMa, $colFicha];
    }

    /**
     * Matrículas distintas en la ficha (misma lógica que cobertura: pluck MA o fallback aprendicesPorFicha).
     *
     * @return \Illuminate\Support\Collection<int, int>
     */
    private function idsMatriculaDistintasEnFicha(int $idFicha, string $tableMa, string $colFicha): \Illuminate\Support\Collection
    {
        $ids = DB::table($tableMa)
            ->where($colFicha, $idFicha)
            ->whereNotNull('idMatricula')
            ->distinct()
            ->pluck('idMatricula')
            ->map(fn ($v) => (int) $v)
            ->filter(fn ($v) => $v > 0)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return collect($this->aprendicesPorFicha($idFicha))
                ->pluck('id')
                ->map(fn ($v) => (int) $v)
                ->filter(fn ($v) => $v > 0)
                ->unique()
                ->values();
        }

        return $ids;
    }

    /**
     * @param  array<int>  $idsMatricula
     * @return array<int, array{idMatricula: int, identificacion: string, nombreCompleto: string, rutaFoto: string|null}>
     */
    private function detalleAprendicesPorMatriculas(array $idsMatricula): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $idsMatricula))));
        if ($ids === []) {
            return [];
        }

        $rows = DB::table('matricula as m')
            ->join('persona as p', 'm.idPersona', '=', 'p.id')
            ->whereIn('m.id', $ids)
            ->select([
                'm.id as idMatricula',
                'p.identificacion',
                DB::raw("TRIM(CONCAT(COALESCE(p.nombre1,''), ' ', COALESCE(p.nombre2,''), ' ', COALESCE(p.apellido1,''), ' ', COALESCE(p.apellido2,''))) as nombreCompleto"),
                'p.rutaFoto',
            ])
            ->get();

        $out = [];
        foreach ($rows as $r) {
            $nombre = trim((string) ($r->nombreCompleto ?? ''));
            $out[] = [
                'idMatricula' => (int) $r->idMatricula,
                'identificacion' => (string) ($r->identificacion ?? ''),
                'nombreCompleto' => $nombre !== '' ? $nombre : 'Sin nombre',
                'rutaFoto' => $r->rutaFoto ?? null,
            ];
        }

        usort($out, fn ($a, $b) => strcasecmp($a['nombreCompleto'], $b['nombreCompleto']));

        return $out;
    }
}
