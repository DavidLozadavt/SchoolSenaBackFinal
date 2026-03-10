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
     * Listar grupos del estudiante para todas sus fichas (vista aprendiz).
     * Retorna fichas con sus grupos y si el estudiante ya está en cada grupo.
     */
    public function gruposEstudiante(): JsonResponse
    {
        try {
            $user = auth()->user();
            if (!$user || !$user->idpersona) {
                return response()->json(['data' => []], 200);
            }

            $matriculas = \Illuminate\Support\Facades\DB::table('matricula as m')
                ->where('m.idPersona', $user->idpersona)
                ->whereIn('m.estado', ['ACTIVO', 'EN CURSO', 'CURSANDO', 'MATRICULADO', 'EN FORMACION'])
                ->whereNotNull('m.idFicha')
                ->select('m.id as idMatricula', 'm.idFicha')
                ->distinct()
                ->get();

            if ($matriculas->isEmpty()) {
                return response()->json(['data' => []], 200);
            }

            $fichas = [];
            foreach ($matriculas->unique('idFicha') as $m) {
                $grupos = GrupoFicha::where('idAsignacionPeriodoProgramaJornada', $m->idFicha)
                    ->with('tipoGrupo')
                    ->orderBy('id', 'desc')
                    ->get();

                $tblPart = $this->tablaParticipantes();
                $integrantesPorGrupo = [];
                if ($tblPart) {
                    $counts = \Illuminate\Support\Facades\DB::table($tblPart)
                        ->whereIn('idGrupo', $grupos->pluck('id'))
                        ->selectRaw('idGrupo, COUNT(*) as total')
                        ->groupBy('idGrupo')
                        ->pluck('total', 'idGrupo');
                    foreach ($grupos as $g) {
                        $integrantesPorGrupo[$g->id] = (int) ($counts[$g->id] ?? 0);
                    }
                }

                $gruposConEstado = [];
                foreach ($grupos as $g) {
                    $integrantes = $integrantesPorGrupo[$g->id] ?? 0;
                    $yaUnido = false;
                    if ($tblPart) {
                        $yaUnido = \Illuminate\Support\Facades\DB::table($tblPart)
                            ->where('idGrupo', $g->id)
                            ->where('idMatricula', $m->idMatricula)
                            ->exists();
                    }
                    $gruposConEstado[] = [
                        'id' => $g->id,
                        'nombreGrupo' => $g->nombreGrupo,
                        'descripcion' => $g->descripcion,
                        'cantidadParticipantes' => $g->cantidadParticipantes,
                        'estado' => $g->estado,
                        'integrantesActuales' => $integrantes,
                        'yaUnido' => $yaUnido,
                        'tipoGrupo' => $g->tipoGrupo,
                    ];
                }

                $ficha = Ficha::find($m->idFicha);
                $fichas[] = [
                    'idFicha' => $m->idFicha,
                    'idMatricula' => $m->idMatricula,
                    'codigoFicha' => $ficha?->codigo ?? null,
                    'grupos' => $gruposConEstado,
                ];
            }

            return response()->json(['data' => $fichas]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

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

            $integrantesPorGrupo = [];
            $tblPart = $this->tablaParticipantes();
            if ($tblPart) {
                $counts = \Illuminate\Support\Facades\DB::table($tblPart)
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
                'data' => $grupos,
                'fechaFinalClases' => $fechaFinRaps,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Listar tipos de grupo (fallback cuando datos-crear falla).
     */
    public function tiposGrupo(): JsonResponse
    {
        try {
            $tipos = TipoGrupo::all(['id', 'nombreTipoGrupo']);
            return response()->json($tipos);
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
                'idTipoGrupo' => 'nullable|exists:tipoGrupo,id',
            ]);

            $idTipoGrupo = $validated['idTipoGrupo'] ?? TipoGrupo::first()?->id;
            if (!$idTipoGrupo) {
                // Crear tipo "General" si la tabla está vacía
                $tipo = TipoGrupo::create(['nombreTipoGrupo' => 'General']);
                $idTipoGrupo = $tipo->id;
            }

            $grupo = GrupoFicha::create([
                'nombreGrupo' => $validated['nombreGrupo'],
                'cantidadParticipantes' => $validated['cantidadParticipantes'],
                'descripcion' => $validated['descripcion'] ?? '',
                'estado' => 'ACTIVO',
                'idTipoGrupo' => $idTipoGrupo,
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
     * E1-HU2: No permite reducir cupo por debajo de integrantes actuales.
     * Valida nombre duplicado en el mismo RAP.
     */
    public function update(Request $request, int $idFicha, int $id): JsonResponse
    {
        try {
            $grupo = GrupoFicha::where('idAsignacionPeriodoProgramaJornada', $idFicha)->findOrFail($id);

            $validated = $request->validate([
                'nombreGrupo' => 'sometimes|required|string|max:255',
                'cantidadParticipantes' => 'sometimes|required|integer|min:1',
                'descripcion' => 'nullable|string',
                'idTipoGrupo' => 'nullable|exists:tipoGrupo,id',
                'estado' => 'sometimes|in:ACTIVO,INACTIVO',
            ]);

            if (isset($validated['cantidadParticipantes'])) {
                $integrantesActuales = $this->contarIntegrantes($grupo->id);
                if ($validated['cantidadParticipantes'] < $integrantesActuales) {
                    return response()->json([
                        'errors' => ['cantidadParticipantes' => ["No puede reducir el cupo por debajo de los integrantes actuales ({$integrantesActuales})."]]
                    ], 422);
                }
            }

            if (isset($validated['nombreGrupo']) && $validated['nombreGrupo'] !== $grupo->nombreGrupo) {
                $existe = GrupoFicha::where('idAsignacionPeriodoProgramaJornada', $idFicha)
                    ->where('nombreGrupo', $validated['nombreGrupo'])
                    ->where('id', '!=', $id)
                    ->exists();
                if ($existe) {
                    return response()->json([
                        'errors' => ['nombreGrupo' => ['Ya existe un grupo con ese nombre en este RAP.']]
                    ], 422);
                }
            }

            $grupo->update($validated);
            return response()->json($grupo);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Nombre de la tabla de participantes (camelCase o lowercase).
     */
    private function tablaParticipantes(): ?string
    {
        if (\Illuminate\Support\Facades\Schema::hasTable('asignacionParticipantes')) {
            return 'asignacionParticipantes';
        }
        if (\Illuminate\Support\Facades\Schema::hasTable('asignacionparticipantes')) {
            return 'asignacionparticipantes';
        }
        return null;
    }

    /**
     * Cuenta integrantes del grupo (asignacionParticipantes/asignacionparticipantes con idGrupo -> grupos).
     */
    private function contarIntegrantes(int $idGrupo): int
    {
        $tbl = $this->tablaParticipantes();
        if (!$tbl) {
            return 0;
        }
        return (int) \Illuminate\Support\Facades\DB::table($tbl)
            ->where('idGrupo', $idGrupo)
            ->count();
    }

    /**
     * E2-HU2: Aprendiz se une a un grupo activo.
     * E2-HU3: No puede unirse si ya está en otro grupo del mismo RAP.
     * E2-HU4: Permite múltiples grupos si son de RAP diferentes.
     */
    public function unirse(Request $request, int $idFicha, int $id): JsonResponse
    {
        try {
            $grupo = GrupoFicha::where('idAsignacionPeriodoProgramaJornada', $idFicha)
                ->where('id', $id)
                ->where('estado', 'ACTIVO')
                ->firstOrFail();

            $validated = $request->validate([
                'idMatricula' => 'required|exists:matricula,id',
            ]);
            $idMatricula = $validated['idMatricula'];

            $tbl = $this->tablaParticipantes();
            if (!$tbl) {
                return response()->json(['error' => 'Tabla asignacionParticipantes no disponible'], 500);
            }

            $yaEnEsteGrupo = \Illuminate\Support\Facades\DB::table($tbl)
                ->where('idGrupo', $id)
                ->where('idMatricula', $idMatricula)
                ->exists();
            if ($yaEnEsteGrupo) {
                return response()->json(['error' => 'Ya perteneces a este grupo'], 422);
            }

            $integrantes = $this->contarIntegrantes($id);
            if ($integrantes >= $grupo->cantidadParticipantes) {
                return response()->json(['error' => 'El grupo está lleno'], 422);
            }

            $yaEnOtroGrupoMismoRap = \Illuminate\Support\Facades\DB::table($tbl . ' as ap')
                ->join('grupos as g', 'ap.idGrupo', '=', 'g.id')
                ->where('ap.idMatricula', $idMatricula)
                ->where('g.idAsignacionPeriodoProgramaJornada', $idFicha)
                ->where('g.id', '!=', $id)
                ->exists();
            if ($yaEnOtroGrupoMismoRap) {
                return response()->json(['error' => 'Ya perteneces a un grupo de este RAP'], 422);
            }

            \Illuminate\Support\Facades\DB::table($tbl)->insert([
                'idGrupo' => $id,
                'idMatricula' => $idMatricula,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return response()->json(['message' => 'Te has unido al grupo correctamente'], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Eliminar un grupo.
     * No se puede eliminar si tiene integrantes asignados.
     */
    public function destroy(int $idFicha, int $id): JsonResponse
    {
        try {
            $grupo = GrupoFicha::where('idAsignacionPeriodoProgramaJornada', $idFicha)->findOrFail($id);
            $integrantes = $this->contarIntegrantes($grupo->id);
            if ($integrantes > 0) {
                return response()->json([
                    'error' => "No se puede eliminar el grupo porque tiene {$integrantes} integrante(s). Retírelos primero."
                ], 422);
            }
            $grupo->delete();
            return response()->json(['message' => 'Grupo eliminado']);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
