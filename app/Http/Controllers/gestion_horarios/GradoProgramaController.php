<?php

namespace App\Http\Controllers;

use App\Http\Controllers\gestion_publicaciones\GrupoGeneralController;
use App\Models\Grado;
use App\Models\GradoPrograma;
use App\Models\GrupoGeneral;
use App\Models\ParticipanteGrupoGeneral;
use App\Util\KeyUtil;
use App\Util\QueryUtil;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
