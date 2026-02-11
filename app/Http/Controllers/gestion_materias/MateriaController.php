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
        try{
        $materias = AgregarMateriaPrograma::where('idPrograma', $idPrograma)
            ->with('materia', function ($query) 
                {
                    $query->whereNull('idMateriaPadre');
                })
            ->get()
            ->pluck('materia')
            ->values();
        return response()->json($materias);
        }catch(\Throwable $error){
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
        $idAsignacionPeriodoProgramaJornada = $request->idAsignacionPeriodoProgramaJornada;
        $idGradoPrograma                    = $request->idGradoPrograma;

        $asignacionPeriodoProgramaJornada   = Ficha::findOrFail($idAsignacionPeriodoProgramaJornada);

        $subMaterias = Materia::with([
            'grados' => function ($query) use ($idAsignacionPeriodoProgramaJornada, $idGradoPrograma) {
                $query->where('idGradoPrograma', $idGradoPrograma)
                    ->whereHas('horarios', function ($subQuery) use ($idAsignacionPeriodoProgramaJornada) {
                        $subQuery->whereIn('id', function ($maxQuery) use ($idAsignacionPeriodoProgramaJornada) {
                            $maxQuery->select(DB::raw('MAX(id)'))
                                ->from('horarioMateria')
                                ->where('idAsignacionPeriodoJornada', $idAsignacionPeriodoProgramaJornada)
                                ->groupBy('idAsignacionPeriodoJornada', 'idGradoMateria');
                        });
                    })
                    ->with(['horarios' => function ($subQuery) use ($idAsignacionPeriodoProgramaJornada) {
                        $subQuery->whereIn('id', function ($maxQuery) use ($idAsignacionPeriodoProgramaJornada) {
                            $maxQuery->select(DB::raw('MAX(id)'))
                                ->from('horarioMateria')
                                ->where('idAsignacionPeriodoJornada', $idAsignacionPeriodoProgramaJornada)
                                ->groupBy('idAsignacionPeriodoJornada', 'idGradoMateria');
                        })->with([
                            'infraestructura.sede',
                            'infraestructura.inventario',
                            'dia.jornadas',
                            'asignacionPeriodoProgramaJornada.asignacionPeriodoPrograma.programa',
                            'contrato.persona',
                            'sesionMaterias.asistencia.matriculaAcademica',
                            'sesionMaterias.calificacionSesiones',
                        ]);
                    }]);
            },
            'seguimientoMaterias' => function ($query) use ($idAsignacionPeriodoProgramaJornada) {
                $query->where('idFicha', $idAsignacionPeriodoProgramaJornada);
            },
        ])
            ->whereHas('grados', function ($query) use ($idAsignacionPeriodoProgramaJornada, $idGradoPrograma) {
                $query->where('idGradoPrograma', $idGradoPrograma)
                    ->whereHas('horarios', function ($subQuery) use ($idAsignacionPeriodoProgramaJornada) {
                        $subQuery->whereIn('id', function ($maxQuery) use ($idAsignacionPeriodoProgramaJornada) {
                            $maxQuery->select(DB::raw('MAX(id)'))
                                ->from('horarioMateria')
                                ->where('idAsignacionPeriodoJornada', $idAsignacionPeriodoProgramaJornada)
                                ->groupBy('idAsignacionPeriodoJornada', 'idGradoMateria');
                        });
                    });
            })
            ->whereHas('grados.horarios.asignacionPeriodoProgramaJornada', function ($subQJornada) use ($asignacionPeriodoProgramaJornada) {
                $subQJornada->where('id', $asignacionPeriodoProgramaJornada->id);
            })
            ->whereHas('materiasAgregadas', function ($query) use ($asignacionPeriodoProgramaJornada) {
                $query->where('idPrograma', $asignacionPeriodoProgramaJornada->asignacionPeriodoPrograma->idPrograma);
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
                                ->with(['horarios.sesionMaterias', 'horarios.asignacionPeriodoProgramaJornada']);
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
    public function update(Request $request, int $id)
    {
        try {
            DB::beginTransaction();

            $datos = $request->all();

            $materia = Materia::findOrFail($id);

            $materia->update([
                'nombreMateria' => $datos['nombreMateria'],
                'descripcion' => $datos['descripcion'],
                'idAreaConocimiento' => $datos['idAreaConocimiento']
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

    public function getCompeteciasHijas(int $idMateriaPadre)
    {
        if(!$idMateriaPadre){
            return response()->json([
                    'message' => 'Materias hijas obtenidas correctamente'
                ], 400);
        }

        $materias = Materia::where('idMateriaPadre', $idMateriaPadre)->get();

        return response()->json([
            'message' => 'Materias hijas obtenidas correctamente',
            'data' => $materias
        ], 200);
    }

    public function crearCompetencia(Request $request) 
    {
        try{
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
            ],201);
        }catch(\Throwable $e){
            DB::rollBack();
            return response()->json([
                'message' => 'No se pudo crear la competencia',
                'error' => $e->getMessage()
            ],400);
        }
    }

    public function getById(int $id){

        $materia = Materia::select(
            'id',
            'nombreMateria',
            'descripcion',
            'idAreaConocimiento'
        )->find($id);

        if(!$materia){
            return response()->json([
                'message' => 'NO se encontro la competencia',
                'data' => null
            ],404);
        }

        return response()->json([
            'message' => 'Competencia encontrada correctamente',
            'data' => $materia
        ],200);
    }
}
