<?php

namespace App\Http\Controllers\gestion_materias;

use Exception;
use Carbon\Carbon;
use App\Util\KeyUtil;
use App\Models\Materia;
use App\Util\QueryUtil;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
use App\Models\Ficha;
use App\Http\Controllers\Controller;
use App\Models\AgregarMateriaPrograma;
use App\Models\AsignacionContratoAreaConocimiento;
use App\Models\Contract;
use App\Models\GradoMateria;

use function PHPUnit\Framework\isEmpty;

class MateriaController extends Controller
{
    private array $relations;
    private array $columns;

    function __construct()
    {
        $this->relations = [];
        $this->columns = ['*'];
    }
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */

    public function getAllCompetencesByProgram($idPrograma): JsonResponse
    {
        try {
            $materias = AgregarMateriaPrograma::where('idPrograma', $idPrograma)
                ->with('materia', function ($query) {
                    $query->whereNull('idMateriaPadre');
                })
                ->get()
                ->pluck('materia')
                ->values();
            return response()->json($materias);
        } catch (\Throwable $error) {
            return response()->json([
                'message' => 'No se pudieron cargar las competencias',
                'error' => $error->getMessage()
            ]);
        }
    }

    /**
     * Get sub materias by id materia padre
     * @param \Illuminate\Http\Request $request
     * @param string $id (idMateria - idRap)
     * @return \Illuminate\Http\JsonResponse
     */
    public static function getSubMateriasByPadre(Request $request, string $id): JsonResponse
    {
        $idFicha = $request->idFicha;
        $idGradoPrograma                    = $request->idGradoPrograma;

        $idAperturarPrograma   = Ficha::findOrFail($idFicha);

        $subMaterias = Materia::with([
            'grados' => function ($query) use ($idFicha, $idGradoPrograma) {
                $query->where('idGradoPrograma', $idGradoPrograma)
                    ->whereHas('horarios', function ($subQuery) use ($idFicha) {
                        $subQuery->whereIn('id', function ($maxQuery) use ($idFicha) {
                            $maxQuery->select(DB::raw('MAX(id)'))
                                ->from('horarioMateria')
                                ->where('idAsignacionPeriodoJornada', $idFicha)
                                ->groupBy('idAsignacionPeriodoJornada', 'idGradoMateria');
                        });
                    })
                    ->with(['horarios' => function ($subQuery) use ($idFicha) {
                        $subQuery->whereIn('id', function ($maxQuery) use ($idFicha) {
                            $maxQuery->select(DB::raw('MAX(id)'))
                                ->from('horarioMateria')
                                ->where('idAsignacionPeriodoJornada', $idFicha)
                                ->groupBy('idAsignacionPeriodoJornada', 'idGradoMateria');
                        })->with([
                            'infraestructura.sede',
                            'infraestructura.inventario',
                            'dia.jornadas',
                            'idAperturarPrograma.asignacionPeriodoPrograma.programa',
                            'contrato.persona',
                            'sesionMaterias.asistencia.matriculaAcademica',
                            'sesionMaterias.calificacionSesiones',
                        ]);
                    }]);
            },
            'seguimientoMaterias' => function ($query) use ($idFicha) {
                $query->where('idFicha', $idFicha);
            },
        ])
            ->whereHas('grados', function ($query) use ($idFicha, $idGradoPrograma) {
                $query->where('idGradoPrograma', $idGradoPrograma)
                    ->whereHas('horarios', function ($subQuery) use ($idFicha) {
                        $subQuery->whereIn('id', function ($maxQuery) use ($idFicha) {
                            $maxQuery->select(DB::raw('MAX(id)'))
                                ->from('horarioMateria')
                                ->where('idAsignacionPeriodoJornada', $idFicha)
                                ->groupBy('idAsignacionPeriodoJornada', 'idGradoMateria');
                        });
                    });
            })
            ->whereHas('grados.horarios.idAperturarPrograma', function ($subQJornada) use ($idAperturarPrograma) {
                $subQJornada->where('id', $idAperturarPrograma->id);
            })
            ->whereHas('materiasAgregadas', function ($query) use ($idAperturarPrograma) {
                $query->where('idPrograma', $idAperturarPrograma->asignacionPeriodoPrograma->idPrograma);
            })
            ->whereHas('seguimientoMaterias')
            ->where('idMateriaPadre', $id)
            ->distinct()
            ->get();

        $subMaterias = $subMaterias->sortBy(function ($materia) { // Order by the number after the dash
            preg_match('/\d+\s*-\s*(\d+)/', $materia->nombreMateria, $matches);
            return isset($matches[1]) ? (int) $matches[1] : 9999;
        })->values();

        foreach ($subMaterias as $materia) {
            foreach ($materia->grados as $grado) {
                $gradoId = $grado->id;

                $result = Materia::where('idMateriaPadre', $materia->idMateriaPadre)
                    ->whereHas('grados', function ($query) use ($gradoId) {
                        $query->where('id', $gradoId);
                    })
                    ->with([
                        'grados' => function ($query) use ($gradoId) {
                            $query->where('id', $gradoId)
                                ->with(['horarios.sesionMaterias', 'horarios.ficha']);
                        }
                    ])
                    ->get();

                if ($result->isNotEmpty()) {
                    $materiaData = $result->first();

                    foreach ($materiaData->grados as $gradoData) {
                        $horasPorSesion = $gradoData->horarios->sum(function ($horario) {
                            if ($horario->horaInicial && $horario->horaFinal) {
                                return Carbon::parse($horario->horaFinal)->diffInHours(Carbon::parse($horario->horaInicial));
                            }
                            return 0;
                        });

                        $sesionesRegistradas = $gradoData->horarios->flatMap->sesionMaterias->count();

                        $horasEjecutadas     = $horasPorSesion * $sesionesRegistradas;

                        $horasRestantes      = $materiaData->horas - $horasEjecutadas;

                        $grado->horasPorSesion            = $horasPorSesion;
                        $grado->sesionesRegistradas       = $sesionesRegistradas;
                        $grado->horasEjecutadas           = $horasEjecutadas;
                        $grado->horasRestantes            = $horasRestantes;
                        $grado->porcentajeHorasEjecutadas = ($materiaData->horas > 0)
                            ? round(($horasEjecutadas / $materiaData->horas) * 100, 2)
                            : 0;
                    }
                }
            }
        }

        return response()->json($subMaterias);
    }

    /**
     * Get matter father by sub matter
     * @param \Illuminate\Http\Request $request
     * @param string $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function getMateriaPadre(Request $request, string $id): JsonResponse
    {

        $materia = Materia::with('materiaPadre')->findOrFail($id);

        return response()->json($materia);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Materia  $materia
     * @return \Illuminate\Http\Response
     */
    public function show(Materia $materia)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Materia  $materia
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        try {
            DB::beginTransaction();

            $datos = $request->all();

            $materia = Materia::findOrFail($id);

            $materia->update([
                'nombreMateria' => $datos['nombreMateria'],
                'descripcion' => $datos['descripcion'],
                'idAreaConocimiento' => $datos['idAreaConocimiento'],
                'horas' => $datos['horas'],
                'creditos' => $datos['creditos']
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Competencia actualizada correctamente'
            ], 200);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'No se pudo actualizar la competencia',
                'error' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Materia  $materia
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Materia $materia)
    {
        try {
            $materia->delete();
            return response()->json(['menssage' => 'Materia eliminada correcamente']);
        } catch (QueryException $th) {
            return QueryUtil::handleQueryException($th);
        } catch (Exception $th) {
            return QueryUtil::showExceptions($th);
        }
    }

    public function getCompetenciasHijas(Request $request)
    {
        $idMateriaPadre = $request->input('idMateriaPadre');
        $idFicha = $request->input('idFicha');
        $idGradoPrograma = $request->input('idGradoPrograma');

        // Validar TODOS los parámetros requeridos
        if (!$idMateriaPadre) {
            return response()->json([
                'message' => 'ID de competencia padre no proporcionado'
            ], 400);
        }

        if (!$idFicha) {
            return response()->json([
                'message' => 'Ficha no proporcionada'
            ], 400);
        }

        if (!$idGradoPrograma) {
            return response()->json([
                'message' => 'ID de trimestre no proporcionado'
            ], 400);
        }

        // Buscar los RAPs asignados a esa ficha, trimestre y competencia padre
        $raps = GradoMateria::where('idGradoPrograma', $idGradoPrograma)
            ->whereHas('materia', function ($q) use ($idMateriaPadre) {
                $q->where('idMateriaPadre', $idMateriaPadre); // Solo materias hijas de esta competencia
            })
            ->whereHas('horarioMateria', function ($q) use ($idFicha) {
                $q->where('idFicha', $idFicha); // De la ficha seleccionada
            })
            ->with([
                'materia',
                'horarioMateria' => function ($q) use ($idFicha) {
                    $q->where('idFicha', $idFicha)
                        ->with(['dia', 'contrato.persona'])
                        ->withCount(['sesionMaterias as sesiones_realizadas_count' => function ($sq) {
                            $sq->whereNotNull('fechaSesion');
                        }]);
                },
                'gradoPrograma.grado'
            ])
            ->get();

        // Formatear la respuesta
        $resultado = $raps->map(function ($gradoMateria) {
            // Calcular horas usando los horarios asociados
            $horasActuales = 0;
            foreach ($gradoMateria->horarioMateria as $horario) {
                if ($horario->horaInicial && $horario->horaFinal) {
                    $hI = Carbon::parse($horario->horaInicial);
                    $hF = Carbon::parse($horario->horaFinal);
                    $duracionSesion = $hF->diffInMinutes($hI, true) / 60;

                    $sesionesDadas = $horario->sesiones_realizadas_count ?? 0;
                    $horasActuales += $sesionesDadas * $duracionSesion;
                }
            }

            return [
                'id' => $gradoMateria->id,
                'idGradoMateria' => $gradoMateria->id,
                'idMateriaPadre' => $gradoMateria->materia->idMateriaPadre,
                'idMateria' => $gradoMateria->idMateria,
                'nombre' => $gradoMateria->materia->nombreMateria ?? 'Sin nombre',
                'descripcion' => $gradoMateria->materia->descripcion ?? '',
                'codigo' => $gradoMateria->materia->codigo ?? '',
                'estado' => $gradoMateria->estado,
                'horas' => $gradoMateria->materia->horas ?? 0,
                'horasActuales' => round($horasActuales, 2),
                'horasFaltantes' => round(max(0, ($gradoMateria->materia->horas ?? 0) - $horasActuales), 2),
                'porcentajeAvance' => ($gradoMateria->materia->horas ?? 0) > 0
                    ? round(($horasActuales / $gradoMateria->materia->horas) * 100, 2)
                    : 0,
                'trimestre' => [
                    'id' => $gradoMateria->gradoPrograma->grado->id ?? null,
                    'numero' => $gradoMateria->gradoPrograma->grado->numeroGrado ?? null,
                    'fechaInicio' => $gradoMateria->gradoPrograma->fechaInicio ?? null,
                    'fechaFin' => $gradoMateria->gradoPrograma->fechaFin ?? null,
                    'idGradoPrograma' => $gradoMateria->idGradoPrograma
                ],
                'horarios' => [
                    'asignados' => $gradoMateria->horarioMateria
                        ->filter(function ($h) {
                            return $h->idDia != null &&
                                $h->horaInicial != null &&
                                $h->horaFinal != null &&
                                $h->fechaInicial != null &&
                                $h->idContrato != null;
                        })
                        ->map(function ($h) {
                            return [
                                'id' => $h->id,
                                'dia' => $h->dia,
                                'horaInicial' => $h->horaInicial,
                                'horaFinal' => $h->horaFinal,
                                'fechaInicial' => $h->fechaInicial,
                                'fechaFinal' => $h->fechaFinal,
                                'estado' => $h->estado,
                                'instructor' => $h->contrato->persona ?? null
                            ];
                        })->values(),
                    'sinAsignar' => $gradoMateria->horarioMateria
                        ->filter(function ($h) {
                            return $h->idDia != null &&
                                $h->horaInicial != null &&
                                $h->horaFinal != null &&
                                $h->fechaInicial != null &&
                                $h->idContrato == null;
                        })
                        ->map(function ($h) {
                            return [
                                'id' => $h->id,
                                'dia' => $h->dia,
                                'horaInicial' => $h->horaInicial,
                                'horaFinal' => $h->horaFinal,
                                'fechaInicial' => $h->fechaInicial,
                                'fechaFinal' => $h->fechaFinal,
                                'estado' => $h->estado,
                                'instructor' => null
                            ];
                        })->values()
                ]
            ];
        });

        return response()->json([
            'message' => 'RAPs obtenidos correctamente',
            'data' => $resultado
        ], 200);
    }

    // busca los instructores los cuales pueden brindar y ser asignados a la competencia o rap
    public function getMateriasInstructores(Request $request)
    {
        $idMateria = $request->input('idMateria');

        // buscar materia con el area de conocimiento
        $materia = Materia::where('id', $idMateria)->with('areaConocimiento')->firstOrFail();

        $areaConocimientoRequerido = $materia->areaConocimiento->id;

        $contratos = Contract::whereHas(
            'asignacionContratoAreaConocimiento',
            function ($query) use ($areaConocimientoRequerido) {
                $query->where('idAreaConocimiento', $areaConocimientoRequerido);
            }
        )
            ->select('id', 'numeroContrato', 'idpersona')
            ->with('persona:id,nombre1,nombre2,apellido1,apellido2,rutaFoto')
            ->get();


        return response()->json([
            'message' => 'Instructores encontrados correctamente',
            'data' => $contratos
        ], 200);
    }

    public function crearCompetencia(Request $request)
    {
        try {
            $datos = $request->all();
            DB::beginTransaction();
            $compe = Materia::create([
                'nombreMateria' => $datos['nombreMateria'],
                'descripcion' => $datos['descripcion'],
                'idAreaConocimiento' => $datos['idAreaConocimiento'],
                'idCompany' => $datos['idCompany'],
                'idEmpresa' => $datos['idCompany']
            ]);

            AgregarMateriaPrograma::create([
                'idMateria' => $compe->id,
                'idPrograma' => $datos['idPrograma']
            ]);
            DB::commit();
            return response()->json([
                'message' => 'Competencia creada correctamente'
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'No se pudo crear la competencia',
                'error' => $e->getMessage()
            ], 400);
        }
    }

    public function getById($id)
    {

        $materia = Materia::with('areaConocimiento')->find($id);

        if (!$materia) {
            return response()->json([
                'message' => 'NO se encontro la competencia',
                'data' => null
            ], 404);
        }

        return response()->json([
            'message' => 'Competencia encontrada correctamente',
            'data' => $materia
        ], 200);
    }
}
