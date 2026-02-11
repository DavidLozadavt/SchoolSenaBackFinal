<?php

namespace App\Http\Controllers\gestion_horarios;

use App\Models\Grado;
use App\Models\GradoPrograma;
use App\Util\QueryUtil;
use Exception;
use App\Http\Controllers\Controller;
use App\Models\GradoMateria;
use App\Models\HorarioMateria;
use App\Models\TipoGrado;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpParser\Node\Stmt\TryCatch;

use function PHPUnit\Framework\isEmpty;

class GradoProgramaController extends Controller
{
    private array $relations;
    private array $columns;

    function __construct()
    {
        $this->relations = [];
        $this->columns = ["*"];
    }
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        try {
            $dataEncoded = $request->input('data_encoded');
            $data = $dataEncoded ? json_decode($dataEncoded, true) : null;

            $gradoProgramas = GradoPrograma::with($data['relations'] ?? $this->relations)
                ->whereHas('programa', function ($query) {
                    QueryUtil::whereCompany($query);
                })
                ->join('grado', 'grado.id', '=', 'gradoPrograma.idGrado')
                ->orderBy('grado.numeroGrado', 'asc')
                ->select('gradoPrograma.*');

            $gradoProgramas = QueryUtil::where($gradoProgramas, $data, 'idPrograma');
            $gradoProgramas = QueryUtil::where($gradoProgramas, $data, 'idJornada');

            $gradoProgramas = $gradoProgramas->get($data['columns'] ?? $this->columns);


            // Filtrar los horarios con fechaFinal igual a null
            $horariosConFechaNull = $gradoProgramas->flatMap(function ($gradoPrograma) {
                return $gradoPrograma->materias->flatMap(function ($materia) {
                    return $materia->horarios->filter(function ($horario) {
                        return $horario->fechaFinal === null;
                    });
                });
            });

            $horarios = [];

            if ($horariosConFechaNull->isNotEmpty()) {
                foreach ($horariosConFechaNull as $key => $horario) {
                    $horarios[] = $horario->load('infraestructura.sede');
                }
            }

            return response()->json([
                'gradoProgramas' => $gradoProgramas,
                'horarios'       => $horarios ?? null
            ], 200);
        } catch (QueryException $th) {
            QueryUtil::handleQueryException($th);
        } catch (Exception $th) {
            QueryUtil::showExceptions($th);
        }
    }

    /**
     * agregar trimestre con minimo una materia a la ficha.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function addTrimestreFicha(Request $request)
    {
        DB::beginTransaction();
        try{
            $datos = $request->validate([
                'idPrograma' => 'required|integer',
                'numeroGrado' => 'required|integer',
                'fechaInicio' => 'required|date',
                'fechaFin' => 'required|date',
                'idFicha' => 'required|integer',
                'materias' => 'required|array',
            ]);

            // consultar el id del tipo de grado(TRIMESTRE)
                $tipoGrado = TipoGrado::where('nombreTipoGrado', 'TRIMESTRE')->firstOrFail();
            // buscar el grado con ese tipo de grado
            $grado = Grado::where('idTipoGrado', $tipoGrado->id)
                ->where('numeroGrado', $datos['numeroGrado'])
                ->firstOrFail();
                
            $gradoPrograma = GradoPrograma::updateOrCreate(
                [
                    'idPrograma' => $datos['idPrograma'],
                    'idGrado' => $grado->id,
                ],
                [
                    'fechaInicio' => $datos['fechaInicio'],
                    'fechaFin' => $datos['fechaFin'],
                    'estado' => 'PENDIENTE'
                ]
            );

            // crear gradoMateria con las materias seleccionadas (asignar las materias al trimestre)
            // en el array de materias solo contiene los ids
                foreach ($datos['materias'] as $nueva) {
                    $newGradoMateria = GradoMateria::create([
                        'idGradoPrograma' => $gradoPrograma->id,
                        'idMateria' => $nueva['id'],
                        'estado' => 'PENDIENTE'
                    ]);

                    // crear el horarioMateria vacio con el id de la ficha
                    HorarioMateria::create([
                        'estado' => 'PENDIENTE',
                        'idFicha' => $datos['idFicha'],
                        'idGradoMateria' => $newGradoMateria->id
                    ]);
                }

            DB::commit();
            return response()->json([
                'message' => 'Trimestre con materias creado exitosamente'
            ], 201);

        }catch(\Throwable $e){
            DB::rollBack();
            return response()->json([
                'message' => 'No se pudo agregar el trimestre',
                'error' => $e->getMessage()
            ],400);
        }
    }

    public function addCompetenciasTrimestre(Request $request)
    {
        DB::beginTransaction();
        try{
            $datos = $request->validate([
                'idGradoPrograma' => 'required|integer',
                'materias' => 'required|array',
            ]);

            // en el array de materias solo contiene los ids
                foreach ($datos['materias'] as $nueva) {
                    $newGradoMateria = GradoMateria::create([
                        'idGradoPrograma' => $datos['idGradoPrograma'],
                        'idMateria' => $nueva['id'],
                        'estado' => 'PENDIENTE'
                    ]);

                    // crear el horarioMateria vacio con el id de la ficha
                    HorarioMateria::create([
                        'estado' => 'PENDIENTE',
                        'idFicha' => $datos['idFicha'],
                        'idGradoMateria' => $newGradoMateria->id
                    ]);
                }

            DB::commit();
            return response()->json([
                'message' => 'Competencias agregadas exitosamente'
            ], 201);

        }catch(\Throwable $e){
            DB::rollBack();
            return response()->json([
                'message' => 'No se pudo agregar las competencias',
                'error' => $e->getMessage()
            ],400);
        }
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        try {
            $data = $request->all();

            $idPrograma = $data['idPrograma'];
            $idTipoGrado = $data['idTipoGrado'] ?? 2;

            $grados = Grado::where('idTipoGrado', $idTipoGrado)
                ->whereIn('numeroGrado', $data['grados'])
                ->pluck('id');

            $entrantes = [];

            foreach ($grados as $grado) {
                $newEntrante = [
                    'idGrado' => $grado,
                    'idPrograma' => $idPrograma
                ];
                array_push($entrantes, $newEntrante);
            }

            if ($this->checkGrados($entrantes)) {
                return response()->json(['message' => 'No puedes asignar un grado de un tipo diferente'], 500);
            }

            $actuales = GradoPrograma::select('idPrograma', 'idGrado')
                ->where('idPrograma', $idPrograma)
                ->get();

            $eliminar = $actuales->isNotEmpty()
                ? $actuales->map(function ($item) {
                    return ['idPrograma' => $item->idPrograma, 'idGrado' => $item->idGrado];
                })->reject(function ($eliminarItem) use ($entrantes) {
                    return collect($entrantes)->pluck('idGrado')->contains($eliminarItem['idGrado']);
                })->values()->all()
                : [];

            $this->deleteGrades($idPrograma, $eliminar);

            foreach ($entrantes as $gradoPrograma) {
                // Buscar o crear el GradoPrograma
                $newGrado = GradoPrograma::firstOrCreate($gradoPrograma);

            }

            $gradosPrograma = GradoPrograma::with($data['relations'] ?? $this->relations)
                ->where('idPrograma', $idPrograma)
                ->get($data['columns'] ?? $this->columns);

            return response()->json($gradosPrograma, 201);
        } catch (QueryException $th) {
            QueryUtil::handleQueryException($th);
        } catch (Exception $th) {
            QueryUtil::showExceptions($th);
        }
    }

    /**
     * Delete gradoPrograma and groups related to this with participants
     * @param mixed $idPrograma
     * @param mixed $eliminar
     * @return void
     */
    private function deleteGrades($idPrograma, $eliminar): void
    {
        DB::transaction(function () use ($idPrograma, $eliminar) {
            if (!empty($eliminar)) {
                $gradosEliminados = GradoPrograma::where('idPrograma', $idPrograma)
                    ->whereIn('idGrado', collect($eliminar)->pluck('idGrado'))
                    ->get();
                foreach ($gradosEliminados as $grado) {

                }
                GradoPrograma::where('idPrograma', $idPrograma)
                    ->whereIn('idGrado', collect($eliminar)->pluck('idGrado'))
                    ->delete();
            }
        });
    }

    private function checkGrados(array $grados)
    {
        foreach ($grados as $grado) {
            $gradoModel = Grado::find($grado['idGrado']);

            $isDiferent = GradoPrograma::where('idPrograma', $gradoModel->idPrograma)
                ->whereHas('grado', function ($query) use ($gradoModel) {
                    $query->where('idTipoGrado', '<>', $gradoModel->idTipoGrado);
                })
                ->exists();

            if ($isDiferent) {
                return true;
            }
        }

        return false;
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\GradoPrograma  $gradoPrograma
     * @return \Illuminate\Http\Response
     */
    public function show(GradoPrograma $gradoPrograma)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\GradoPrograma  $gradoPrograma
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, GradoPrograma $gradoPrograma)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\GradoPrograma  $gradoPrograma
     * @return \Illuminate\Http\Response
     */
    public function destroy(GradoPrograma $gradoPrograma)
    {
        //
    }
}
