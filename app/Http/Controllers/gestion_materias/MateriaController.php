<?php

namespace App\Http\Controllers\gestion_materias;

use App\Enums\Estado;
use App\Enums\EstadoHorarioMateria;
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
use App\Models\AperturarPrograma;
use App\Models\AsignacionContratoAreaConocimiento;
use App\Models\Contract;
use App\Models\Grado;
use App\Models\GradoMateria;
use App\Models\GradoPrograma;
use App\Models\HorarioMateria;
use App\Models\MatriculaAcademica;
use App\Services\TrimestreActualFichaService;

use function PHPSTORM_META\map;
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

    public function getAllCompetences(): JsonResponse
    {
        $materias = Materia::where('idMateriaPadre', null)->get();
        $resultado = $materias->map(function ($materia) {
            return [
                'id' => $materia->id,
                'nombreMateria' => $materia->nombreMateria,
                'codigo' => $materia->codigo,
                'horas' => $materia->horas_programa,
                'descripcion' => $materia->descripcion,
                'isCompleta' => false,
                'estado' => '',
                'idCategoriaFormacion' => $materia->idCategoriaFormacion
            ];
        });
        return response()->json($resultado);
    }


    public function getAllCompetencesByFicha(Request $request): JsonResponse
    {
        $idFicha = $request->input('idFicha');
        $soloTransversales = filter_var($request->input('soloTransversales'), FILTER_VALIDATE_BOOLEAN);
        $excluirCompletadas = filter_var(
            $request->input('excluirCompletadas', $soloTransversales ? true : false),
            FILTER_VALIDATE_BOOLEAN
        );
        try {
<<<<<<< HEAD
            $ficha = Ficha::findOrFail($idFicha);
            $apertura = AperturarPrograma::findOrFail($ficha->idAsignacion);
            $gradoPrograma = GradoPrograma::where('idGrado', $ficha->idGrado)
                ->where('idPrograma', $apertura->idPrograma)
                ->first();
            
            // obtener materias asociadas al grado (PRIMERO, SEGUNDO, ETC)
            $gradosMateria = GradoMateria::where('idGradoPrograma', $gradoPrograma->id)
                ->whereHas('materia', function ($query) {
                    $query->whereNull('idMateriaPadre');
                })
=======
            if (empty($idFicha)) {
                return response()->json([
                    'message' => 'El idFicha es requerido'
                ], 400);
            }

            if (empty($idPrograma)) {
                $ficha = Ficha::with('asignacion')->find($idFicha);
                $idPrograma = $ficha?->asignacion?->idPrograma;
            }

            if (empty($idPrograma)) {
                return response()->json([
                    'message' => 'No se pudo determinar el programa de la ficha'
                ], 400);
            }

            // Obtener todos los estados de matrícula de la ficha para determinar asignación y avance
            // Esta es ahora nuestra única fuente de verdad para este reporte
            $matriculasFicha = MatriculaAcademica::where('idFicha', $idFicha)
                ->select('idMateria', 'estado')
>>>>>>> 0285130cc19333fa73d825bca3d7f5e03e608e1f
                ->with('materia')
                ->get();

            $resultado = $gradosMateria->map(function ($gradoMateria) use ($apertura, $ficha, $gradoPrograma) {
                $materia = $gradoMateria->materia;
                $fechaFinalClases = $apertura->fechaFinalClases ?? null;
                $fechaActual = Carbon::now();

                // Obtener los IDs de las materias hijas (RAPs)
                $hijosMateriaIds = Materia::where('idMateriaPadre', $materia->id)->pluck('id');
                
                // Obtener los gradoMateria correspondientes a los hijos y al padre
                $gradoMateriaIds = GradoMateria::where('idGradoPrograma', $gradoPrograma->id)
                    ->whereIn('idMateria', $hijosMateriaIds)
                    ->pluck('id')
                    ->push($gradoMateria->id);

<<<<<<< HEAD
                // Obtener horarios asociados
                $horarios = HorarioMateria::where('idFicha', $ficha->id)
                    ->whereIn('idGradoMateria', $gradoMateriaIds)
                    ->with(['contrato.persona', 'asignacionSesion.contrato.persona'])
                    ->get();

                $asignados = $horarios->filter(function ($h) {
                    return $h->estado !== EstadoHorarioMateria::PENDIENTE;
                })->map(function ($h) {
                    $persona = $h->contrato ? $h->contrato->persona : null;
                    return [
                        'id' => $h->id,
                        'estado' => $h->estado,
                        'idContrato' => $h->idContrato,
                        'instructor' => $persona,
                        'persona' => $persona,
                        'asignacionSesion' => HorarioMateria::asignacionesEspecialesApi($h)
                    ];
                })->values()->all();

                $sinAsignar = $horarios->filter(function ($h) {
                    return $h->estado === EstadoHorarioMateria::PENDIENTE;
                })->map(function ($h) {
                    return [
                        'id' => $h->id,
                        'estado' => $h->estado,
                        'idContrato' => $h->idContrato,
                        'instructor' => null,
                        'persona' => null,
                        'asignacionSesion' => []
                    ];
                })->values()->all();
=======
            // Identificamos las competencias padre (competencias) asociadas a estos RAPs matriculados
            // Obtenemos los padres de las materias encontradas en la matrícula
            $padresIds = $matriculasFicha->map(fn($m) => $m->materia?->idMateriaPadre)->filter()->unique();

            $idsCategoriaTransversal = collect();
            if ($soloTransversales) {
                $idsCategoriaTransversal = DB::table('categoriaFormacion')
                    ->where(function ($q) {
                        $q->whereRaw('UPPER(TRIM(nombre)) LIKE ?', ['%TRASVERSAL%'])
                            ->orWhereRaw('UPPER(TRIM(nombre)) LIKE ?', ['%TRANSVERSAL%']);
                    })
                    ->pluck('id');

                if ($idsCategoriaTransversal->isEmpty()) {
                    return response()->json([]);
                }
            }

            $materiasQuery = Materia::whereIn('materia.id', $padresIds)
                ->join('agregarMateriaPrograma', 'agregarMateriaPrograma.idMateria', '=', 'materia.id')
                ->where('agregarMateriaPrograma.idPrograma', $idPrograma)
                ->select('materia.*', 'agregarMateriaPrograma.horas as horas_programa');

            if ($soloTransversales) {
                $materiasQuery->whereIn('materia.idCategoriaFormacion', $idsCategoriaTransversal);
            }

            $materiasPrograma = $materiasQuery->get();
            $padresParaRaps = $materiasPrograma->pluck('id')->unique()->values();
            if ($padresParaRaps->isEmpty()) {
                return response()->json([]);
            }

            // Obtenemos todos los RAPs posibles de estas competencias para cruzar con la matrícula
            $todosRaps = Materia::whereIn('idMateriaPadre', $padresParaRaps)
                ->join('agregarMateriaPrograma', 'agregarMateriaPrograma.idMateria', '=', 'materia.id')
                ->where('agregarMateriaPrograma.idPrograma', $idPrograma)
                ->select('materia.*', 'agregarMateriaPrograma.horas as horas_programa')
                ->get()
                ->groupBy('idMateriaPadre');

            $categoriasPorId = DB::table('categoriaFormacion')
                ->whereIn('id', $materiasPrograma->pluck('idCategoriaFormacion')->filter()->unique())
                ->pluck('nombre', 'id');

            // Mapear cada competencia encontrada
            $resultado = $materiasPrograma->map(function ($materia) use ($todosRaps, $matriculasAgrupadas, $categoriasPorId) {
                // RAPs de esta competencia particular
                $rapsDeEstaCompetencia = $todosRaps->get($materia->id, collect());
                $rapsIds = $rapsDeEstaCompetencia->pluck('id');

                // Solo consideramos los RAPs que están realmente presentes en la matrícula de esta ficha
                $rapsIdsEnMatricula = $rapsIds->filter(fn($id) => $matriculasAgrupadas->has($id));

                if ($rapsIdsEnMatricula->isEmpty()) {
                    return null;
                }

                $totalRaps = $rapsIdsEnMatricula->count();
                $rapsFinalizados = 0;

                foreach ($rapsIdsEnMatricula as $rapId) {
                    $estudiantes = $matriculasAgrupadas->get($rapId, collect());
                    // Si al menos 5 estudiantes aparecen como EVALUADO, FINALIZADO o APROBADO, se cuenta el RAP como completado
                    if ($estudiantes->filter(fn($m) => in_array(strtoupper($m->estado), ['FINALIZADO', 'EVALUADO', 'APROBADO', 'COMPLETADO']))->count() >= 5) {
                        $rapsFinalizados++;
                    }
                }

                $estaFinalizada = ($rapsFinalizados >= $totalRaps);
                //Para pruebas: 
                //$estaFinalizada = true;
                if ($totalRaps === 0) $estaFinalizada = false;
>>>>>>> 0285130cc19333fa73d825bca3d7f5e03e608e1f

                $idCat = $materia->idCategoriaFormacion;

                return [
                    'id' => (int) $materia->id,
                    'nombreMateria' => $materia->nombreMateria,
                    'codigo' => $materia->codigo,
                    'horas' => $materia->horas,
                    'descripcion' => $materia->descripcion,
<<<<<<< HEAD
                    'isCompleta' => $fechaFinalClases ? $fechaActual->greaterThanOrEqualTo(Carbon::parse($fechaFinalClases)) : false,
                    'estado' => $materia->estado ?? '',
                    'idGradoMateria' => $gradoMateria->id,
                    'idMateriaPadre' => $materia->idMateriaPadre,
                    'idCategoriaFormacion' => $materia->idCategoriaFormacion,
                    'horarios' => [
                        'asignados' => $asignados,
                        'sinAsignar' => $sinAsignar
                    ]
                ];
            });
=======
                    'isCompleta' => $estaFinalizada,
                    'estado' => $estaFinalizada ? 'COMPLETADO' : 'PENDIENTE',
                    'idCategoriaFormacion' => $idCat !== null ? (int) $idCat : null,
                    'categoriaFormacionNombre' => $idCat !== null ? ($categoriasPorId[$idCat] ?? null) : null,
                ];
            })->filter()->values();

            if ($excluirCompletadas) {
                $resultado = $resultado
                    ->filter(fn ($row) => empty($row['isCompleta']) && strtoupper((string) ($row['estado'] ?? '')) !== 'COMPLETADO')
                    ->values();
            }

>>>>>>> 0285130cc19333fa73d825bca3d7f5e03e608e1f
            return response()->json($resultado);
        } catch (\Throwable $error) {
            return response()->json([
                'message' => 'No se pudieron cargar las materias',
                'error' => $error->getMessage()
            ], 500);
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

                $idPrograma = $idAperturarPrograma->aperturarPrograma->idPrograma;
                $result = Materia::where('materia.idMateriaPadre', $materia->idMateriaPadre)
                    ->join('agregarMateriaPrograma', 'agregarMateriaPrograma.idMateria', '=', 'materia.id')
                    ->where('agregarMateriaPrograma.idPrograma', $idPrograma)
                    ->select('materia.*', 'agregarMateriaPrograma.horas as horas_programa')
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

                        $horasRestantes      = $materiaData->horas_programa - $horasEjecutadas;

                        $grado->horasPorSesion            = $horasPorSesion;
                        $grado->sesionesRegistradas       = $sesionesRegistradas;
                        $grado->horasEjecutadas           = $horasEjecutadas;
                        $grado->horasRestantes            = $horasRestantes;
                        $grado->porcentajeHorasEjecutadas = ($materiaData->horas_programa > 0)
                            ? round(($horasEjecutadas / $materiaData->horas_programa) * 100, 2)
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
            $raps = Materia::where('idMateriaPadre', $materia->id)->get();

            // si trae raps y el area de conocimiento es diferente, actualiza el area de conocimiento de los raps
            if ($raps->isNotEmpty() && $materia->idAreaConocimiento != $datos['idAreaConocimiento'] && $materia->idMateriaPadre == null) {
                foreach ($raps as $rap) {
                    $rap->update([
                        'idAreaConocimiento' => $datos['idAreaConocimiento']
                    ]);
                }
            }

            $materia->update([
                'nombreMateria' => $datos['nombreMateria'],
                'descripcion' => $datos['descripcion'],
                'idAreaConocimiento' => $datos['idAreaConocimiento'],
                'codigo' => $datos['codigo'],
                'creditos' => $datos['creditos'],
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

        // Validar TODOS los parámetros requeridos
        if (!$idMateriaPadre) {
            return response()->json([
                'message' => 'Competencia padre no proporcionado'
            ], 400);
        }

        if (!$idFicha) {
            return response()->json([
                'message' => 'Ficha no proporcionada'
            ], 400);
        }

        // Cargar la ficha
        $ficha = Ficha::with('aperturarPrograma')->find($idFicha);
        if (!$ficha) {
            return response()->json([
                'message' => 'Ficha no encontrada'
            ], 404);
        }

        $idPrograma = $ficha->aperturarPrograma->idPrograma;
        $gradoPrograma = GradoPrograma::where('idGrado', $ficha->idGrado)
          ->where('idPrograma', $idPrograma)
          ->firstOrFail();
        // Buscar los RAPs asignados a esa ficha, trimestre y competencia padre
        $raps = GradoMateria::where('idGradoPrograma', $gradoPrograma->id)
            ->whereHas('materia', function ($q) use ($idMateriaPadre) {
                $q->where('idMateriaPadre', $idMateriaPadre); // Solo materias hijas de esta competencia
            })
            ->with('materia')
            ->get()
            ->map(function ($gradoMateria) use ($ficha) {
                $materia = $gradoMateria->materia;
                $horarios = HorarioMateria::where('idFicha', $ficha->id)
                  ->where('idGradoMateria', $gradoMateria->id)
                  ->with(['contrato.persona', 'asignacionSesion.contrato.persona'])
                  ->get();

                $asignados = $horarios->filter(function ($h) {
                    return $h->estado !== EstadoHorarioMateria::PENDIENTE;
                })->map(function ($h) {
                    $persona = $h->contrato ? $h->contrato->persona : null;
                    return [
                        'id' => $h->id,
                        'estado' => $h->estado,
                        'idContrato' => $h->idContrato,
                        'instructor' => $persona,
                        'persona' => $persona,
                        'asignacionSesion' => HorarioMateria::asignacionesEspecialesApi($h)
                    ];
                })->values()->all();

                $sinAsignar = $horarios->filter(function ($h) {
                    return $h->estado === EstadoHorarioMateria::PENDIENTE;
                })->map(function ($h) {
                    return [
                        'id' => $h->id,
                        'estado' => $h->estado,
                        'idContrato' => $h->idContrato,
                        'instructor' => null,
                        'persona' => null,
                        'asignacionSesion' => []
                    ];
                })->values()->all();

                return [
                    'id' => $materia->id,
                    'nombreMateria' => $materia->nombreMateria,
                    'codigo' => $materia->codigo,
                    'horas' => $materia->horas,
                    'descripcion' => $materia->descripcion,
                    'isCompleta' => false,
                    'horarios' => [
                        'asignados' => $asignados,
                        'sinAsignar' => $sinAsignar
                    ],
                    'estado' => $materia->estado ?? '',
                    'idGradoMateria' => $gradoMateria->id,
                    'idMateriaPadre' => $materia->idMateriaPadre,
                    'idCategoriaFormacion' => $materia->idCategoriaFormacion
                ];
            });

<<<<<<< HEAD
=======
            $horasActuales = 0;
            $fechaFinalRap = null;

            foreach ($todosLosHorariosFicha as $horario) {
                if ($horario->horaInicial && $horario->horaFinal) {
                    $hI = Carbon::parse($horario->horaInicial);
                    $hF = Carbon::parse($horario->horaFinal);
                    $duracionSesion = $hF->diffInMinutes($hI, true) / 60;

                    $sesionesDadas = $horario->sesiones_realizadas_count ?? 0;
                    $horasActuales += $sesionesDadas * $duracionSesion;
                }
                if ($horario->fechaFinal != null && $horario->estado != EstadoHorarioMateria::PENDIENTE) {
                    $fF = Carbon::parse($horario->fechaFinal);
                    $fechaFinalRap = is_null($fechaFinalRap) ? $fF : ($fF > $fechaFinalRap ? $fF : $fechaFinalRap);
                }
            }

            // Horas requeridas del RAP ajustadas por el % de ejecución de la ficha
            $horasRap = $gradoMateria->materia->horas_programa ?? 0;
            $horasRequeridas = $horasRap * ($porcentajeEjecucion / 100);

            // Detección de estado:
            // Por matrícula (FINALIZADO / EVALUADO / APROBADO con al menos 5 aprendices), o
            // Por horas ejecutadas >= horas requeridas con el % de ejecución
            $finalizadoPorMatricula = $matriculasFicha->get($materiaId, collect())
                ->filter(fn($m) => in_array(strtoupper($m->estado), ['FINALIZADO', 'EVALUADO', 'APROBADO']))->count() >= 5;

            $finalizadoPorHoras = $horasRequeridas > 0 && $horasActuales >= $horasRequeridas;

            // Finalización explícita (botón Finalizar RAP): estado de gradoMateria
            // o todos los horarios DE ESTE RAP (idGradoMateria) en FINALIZADO/EVALUADO.
            // No usar todosLosHorariosFicha por idMateria: puede mezclar trimestres.
            $estadoGrado = strtoupper((string) ($gradoMateria->estado ?? ''));
            $horariosEsteRap = $gradoMateria->horarioMateria;
            $finalizadoPorEstado = $estadoGrado === EstadoHorarioMateria::FINALIZADO
                || (
                    $horariosEsteRap->isNotEmpty()
                    && $horariosEsteRap->every(function ($h) {
                        return in_array(
                            strtoupper((string) ($h->estado ?? '')),
                            [EstadoHorarioMateria::FINALIZADO, EstadoHorarioMateria::EVALUADO],
                            true
                        );
                    })
                );

            $estaFinalizado = $finalizadoPorMatricula || $finalizadoPorHoras || $finalizadoPorEstado;

            return [
                'id' => $gradoMateria->id,
                'idGradoMateria' => $gradoMateria->id,
                'idMateriaPadre' => $gradoMateria->materia->idMateriaPadre,
                'idMateria' => $gradoMateria->idMateria,
                'nombre' => $gradoMateria->materia->nombreMateria ?? 'Sin nombre',
                'descripcion' => $gradoMateria->materia->descripcion ?? '',
                'codigo' => $gradoMateria->materia->codigo ?? '',
                'estado' => $estaFinalizado ? 'FINALIZADO' : 'PENDIENTE',
                'fechaFinalRap' => $fechaFinalRap instanceof Carbon ? $fechaFinalRap->format('Y-m-d') : null,
                'horas' => $gradoMateria->materia->horas_programa ?? 0,
                'horasActuales' => round($horasActuales, 2),
                'horasFaltantes' => round(max(0, ($gradoMateria->materia->horas_programa ?? 0) - $horasActuales), 2),
                'porcentajeAvance' => ($gradoMateria->materia->horas_programa ?? 0) > 0
                    ? round(($horasActuales / $gradoMateria->materia->horas_programa) * 100, 2)
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
                                'instructor' => $h->contrato->persona ?? null,
                                'asignacionSesion' => \App\Models\HorarioMateria::asignacionesEspecialesApi($h),
                            ];
                        })->values(),
                    'sinAsignar' => $gradoMateria->horarioMateria
                        ->filter(function ($h) {
                            return $h->idDia != null &&
                                $h->horaInicial != null &&
                                $h->horaFinal != null &&
                                $h->fechaInicial != null &&
                                $h->idContrato == null &&
                                $h->estado != EstadoHorarioMateria::INTERRUMPIDO;
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
                                'instructor' => null,
                                'asignacionSesion' => \App\Models\HorarioMateria::asignacionesEspecialesApi($h),
                            ];
                        })->values()
                ]
            ];
        });

>>>>>>> 0285130cc19333fa73d825bca3d7f5e03e608e1f
        return response()->json([
            'message' => 'materias obtenidas correctamente',
            'data' => $raps
        ], 200);
    }

    // busca los instructores los cuales pueden brindar y ser asignados a la competencia o rap
    public function getMateriasInstructores(Request $request)
    {
        $idMateria = $request->input('idMateria');

        // buscar materia con el area de conocimiento
        $materia = Materia::where('id', $idMateria)->with('areaConocimiento')->firstOrFail();

        if ($materia->idAreaConocimiento == null) {
            return response()->json([
                'message' => 'Competencia sin área de conocimiento',
                'data' => []
            ], 400);
        }

        $areaConocimientoRequerido = $materia->areaConocimiento->id;

        $contratos = Contract::whereHas(
            'asignacionContratoAreaConocimiento',
            function ($query) use ($areaConocimientoRequerido) {
                $query->where('idAreaConocimiento', $areaConocimientoRequerido);
            }
        )
            ->select('id', 'numeroContrato', 'idpersona')
            ->with([
                'persona:id,nombre1,nombre2,apellido1,apellido2,rutaFoto',
                'asignacionCategoriaFormacionContrato.categoriaFormacion'
            ])
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
            $materiaPadre = Materia::where('id', $datos['idMateriaPadre'])->first();
            $idCompany = KeyUtil::idCompany();
            $idFicha = $datos['idFicha'];

            $newMateria = Materia::create([
                'nombreMateria' => $datos['nombreMateria'],
                'descripcion' => $datos['descripcion'],
                'idAreaConocimiento' => $datos['idMateriaPadre'] != null ? $materiaPadre->idAreaConocimiento : $datos['idAreaConocimiento'],
                'idMateriaPadre' => $datos['idMateriaPadre'] ?? null,
                'creditos' => $datos['creditos'],
                'codigo' => $datos['codigo'],
                'idCompany' => $idCompany,
                'idEmpresa' => $idCompany,
            ]);

            if ($idFicha) {
                $ficha = Ficha::with('aperturarPrograma')->find($idFicha);
                $gradoPrograma = GradoPrograma::where('idGrado', $ficha->idGrado)
                    ->where('idPrograma', $ficha->aperturarPrograma->idPrograma)
                    ->firstOrFail();
                GradoMateria::create([
                    'idGradoPrograma' => $gradoPrograma->id,
                    'idMateria' => $newMateria->id,
                ]);
            }

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

    public function getById(int $id)
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

    public function deleteMateriaFicha(Request $request)
    {
        try {
            $idMateria = $request->input('idMateria');
            $idFicha = $request->input('idFicha');

            if ($idFicha && $idGradoMateria) {
                $bloqueo = TrimestreActualFichaService::abortSiGradoMateriaNoActual(
                    (int) $idGradoMateria,
                    (int) $idFicha
                );
                if ($bloqueo) {
                    return $bloqueo;
                }
            }

            DB::beginTransaction();

            // gradoMateria tiene idMateria y idGradoPrograma
            $ficha = Ficha::with('asignacion')->find($idFicha);
            $gradoPrograma = GradoPrograma::where('idPrograma', $ficha->asignacion->idPrograma)
              ->where('idGrado', $ficha->idGrado)
              ->firstOrFail();

            $materia = Materia::find($idMateria);
    
            if($materia->idMateriaPadre == null){
                $hijos = Materia::where('idMateriaPadre', $materia->id)->get();
                foreach($hijos as $h){
                    $gradoMateria = GradoMateria::where('idGradoPrograma', $gradoPrograma->id)
                    ->where('idMateria', $h->id)
                    ->first();

                    $horarios = HorarioMateria::where('idFicha', $ficha->id)
                      ->where('idGradoMateria', $gradoMateria->id)
                      ->get(); 
                    
                    if($horarios->isNotEmpty()){
                        return response()->json([
                            'message' => 'No se puede eliminar la materia porque tiene horarios asociados.'
                        ], 400);
                    }
                }
            }

            $gradoMateriaPadre = GradoMateria::where('idGradoPrograma', $gradoPrograma->id)
                ->where('idMateria', $materia->id)
                ->first();
            
            // consultar horarios
            $horarios = HorarioMateria::where('idFicha', $ficha->id)
              ->where('idGradoMateria', $gradoMateriaPadre->id)
              ->get(); 
            
            if($horarios->isNotEmpty()){
                return response()->json([
                    'message' => 'No se puede eliminar la materia porque tiene horarios asociados.'
                ], 400);
            }

            $gradoMateriaPadre->delete();

            DB::commit();
            return response()->json([
                'message' => 'Materia eliminada correctamente.'
            ], 200);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error al eliminar', 'error' => $e->getMessage()], 400);
        }
    }

    public function getMattersChildren(Request $request)
    {
        $idMateriaPadre = $request->input('idMateriaPadre');

        $materias = Materia::where('idMateriaPadre', $idMateriaPadre)->get();

        return response()->json([
            'message' => 'Materias encontradas correctamente',
            'data' => $materias
        ], 200);
    }
}
