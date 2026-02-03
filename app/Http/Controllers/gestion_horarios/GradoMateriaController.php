<?php

namespace App\Http\Controllers\gestion_horarios;

use Exception;
use App\Enums\Estado;
use App\Models\Materia;
use App\Util\QueryUtil;
use App\Models\GradoMateria;
use Illuminate\Http\Request;
use App\Models\HorarioMateria;
use App\Enums\EstadoGradoMateria;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use App\Models\MatriculaAcademica;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Storage;
use App\Models\Pensum\AgregarMateriaPrograma;
use App\Models\Ficha;

class GradoMateriaController extends Controller
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
            $dataEncoded = $request->input('data');
            $data = $dataEncoded ? json_decode($dataEncoded, true) : null;
            $gradoMaterias = GradoMateria::with($data['relations'] ?? $this->relations)
                ->whereHas('materia', function ($query) {
                    QueryUtil::whereCompany($query);
                });
            $gradoMaterias = QueryUtil::where($gradoMaterias, $data, 'idGradoPrograma');
            $gradoMaterias = QueryUtil::where($gradoMaterias, $data, 'idMateria');
            return response()->json($gradoMaterias->get($data['columns'] ?? $this->columns));
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
        $data = $request->all();
        $gradoMateriaData = $data['gradoMateria'];

        $idJornada                   = $gradoMateriaData['idJornada']                   ?? null;
        $idMateria                   = $gradoMateriaData['idMateria']                   ?? null;
        $idAsignacionPeriodoPrograma = $gradoMateriaData['idAsignacionPeriodoPrograma'] ?? null;
        $idGradoPrograma             = $gradoMateriaData['idGradoPrograma']             ?? null;

        unset($gradoMateriaData['idJornada'], $gradoMateriaData['idAsignacionPeriodoPrograma']);
        try {

            $raps = Materia::where('idMateriaPadre', $idMateria)->get();

            if ($this->validateCompentenceRaps($idGradoPrograma, $raps)) {
                return response()->json([
                    'message' => 'No puedes agregar esta competencia porque sus raps se encuentran calificados'
                ], 422);
            }

            $this->updateValidateRaps($raps, $idAsignacionPeriodoPrograma);

            try {

                if ($request->hasFile('documentoMateria')) {

                    $document = $request->file('documentoMateria');
                    $nombreArchivo = uniqid('document')
                        . '_grado' . $gradoMateriaData['idGradoPrograma'] . '_materia' . $gradoMateriaData['idMateria'] . '.pdf';
                    $rutaAlmacenamiento = $document->storeAs('public/documentos/materia', $nombreArchivo);
                    $rutaDocumentoGuardado = Storage::url($rutaAlmacenamiento);
                    $gradoMateriaData['rutaDocumento'] = $rutaDocumentoGuardado;
                } else {
                    $gradoMateriaData['rutaDocumento'] = null;
                }
            } catch (\Throwable $th) {
                return response()->json(['error' => $th], 500);
            }
            unset($gradoMateriaData['documentoMateria']);

            $gradoMateria = GradoMateria::create($gradoMateriaData);

            $grado = $gradoMateria->grado;

            $programa = $grado->programa()->with([
                'asignacionesPeriodoProgramas' => function ($asignacion) {
                    return $asignacion->where('estado', Estado::ENCURSO)
                        ->orWhere('estado', Estado::ABIERTO);
                },
                'asignacionesPeriodoProgramas.jornadas'
            ])->first();

            if (is_null($programa)) {
                return response()->json(['message' => 'El programa no existe'], 404);
            }

            if ($programa->asignacionesPeriodoProgramas->isEmpty()) {
                return response()->json(['message' => 'No hay asignaciones de periodos'], 404);
            }

            $ficha = Ficha::where('idAsignacion', $idAsignacionPeriodoPrograma)->first();
            $horario = HorarioMateria::create([
                'idFicha' => $ficha->id,
                'idGradoMateria'             => $gradoMateria->id,
            ]);

            $idPrograma = $ficha->asignacionPeriodoPrograma->idPrograma;

            $raps = Materia::where('idMateriaPadre', $idMateria)
                ->whereHas('materiasAgregadas', function ($query) use ($idPrograma) {
                    $query->where('idPrograma', $idPrograma);
                })
                ->get();

            foreach ($raps as $rap) {
                $this->createRapHorario($idGradoPrograma, $rap, $ficha);
            }

            $horario->load(
                'ficha.asignacionPeriodoPrograma',
                'ficha.jornada',
                'materia.materia',
                'materia.grado',
                'contrato.persona.usuario.persona',
                'dia',
                'infraestructura.sede.ciudad'
            );

            return response()->json($horario, 201);
        } catch (QueryException $e) {
            if ($e->errorInfo[1] === 1062) {
                return response()->json([
                    'message' => 'No se pudo crear la competencia, porque ya existe una entrada duplicada.'
                ], 409);
            } else {
                throw $e;
            }
        } catch (Exception $e) {
            return response()->json(['error' => $e], 500);
        }
    }

    private function createRapHorario(int $idGradoPrograma, Materia $rap, Ficha $ficha = null, string $fechaInicial = null): HorarioMateria
    {

        $gradoMateria = GradoMateria::create([
            'idGradoPrograma' => $idGradoPrograma,
            'idMateria'       => $rap->id,
        ]);

        // AgregarMateriaPrograma::create([
        //     'idPrograma'     => $ficha->asignacionPeriodoPrograma->idPrograma,
        //     'idMateria'      => $rap->id,
        // ]);

        $horario = HorarioMateria::create([
            'idFicha' => $ficha->id,
            'idGradoMateria'             => $gradoMateria->id,
            'fechaInicial'               => $fechaInicial ?? optional($ficha->asignacionPeriodoPrograma->periodo)->fechaInicial ?? now(),
        ]);

        /* MatriculaAcademica::where('idMateria', $rap->id)
            ->update(['idGradoMateria' => $gradoMateria->id]); */

        return $horario->load([
            'ficha.asignacionPeriodoPrograma',
            'ficha.jornada',
            'materia.materia',
            'materia.grado',
            'contrato.persona.usuario.persona',
            'dia',
            'infraestructura.sede.ciudad'
        ]);
    }

    /**
     * Validate rap by id and change or update state
     * @param \Illuminate\Support\Collection $raps
     * @param string|int|null $idAsignacionPeriodoPrograma
     * @return void
     */
    private function updateValidateRaps(Collection $raps, string|int|null $idAsignacionPeriodoPrograma): void
    {
        $idFicha = Ficha::where('idAsignacion', $idAsignacionPeriodoPrograma)->value('id');

        if (!$idFicha) {
            return;
        }

        /* foreach ($raps as $rap) {
            if ($rap->estado === EstadoGradoMateria::PENDIENTE) {
                $isRapPending = MatriculaAcademica::where('idFicha', $idFicha)
                    ->where('idMateria', $rap->id)
                    ->where('estado', Estado::PENDIENTE)
                    ->exists();

                if ($isRapPending) {
                    $rap->update([
                        'estado' => EstadoGradoMateria::FINALIZADO,
                    ]);
                }
            }
        } */
    }

    /**
     * Validate if compentence is completed
     * @param string|int|null $idGradoPrograma
     * @param \Illuminate\Support\Collection $raps
     * @return bool
     */
    private function validateCompentenceRaps(string|int|null $idGradoPrograma, Collection $raps): bool
    {

        $idsRapsMaterias = $raps->pluck('id');

        if ($idsRapsMaterias->isEmpty()) {
            return false;
        }

        $totalRaps = $idsRapsMaterias->count();

        $countCompleted = GradoMateria::whereIn('idMateria', $idsRapsMaterias)
            ->where('idGradoPrograma', $idGradoPrograma)
            ->where('estado', EstadoGradoMateria::FINALIZADO)
            ->count();

        return $countCompleted === $totalRaps;
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\GradoMateria  $gradoMateria
     * @return \Illuminate\Http\Response
     */
    public function show(GradoMateria $gradoMateria)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\GradoMateria  $gradoMateria
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, int $id)
    {
        $data = $request->all();
        $gradoMateriaData = $data['gradoMateria'];

        $idJornada = $gradoMateriaData['idJornada'] ?? null; // Usar null si no existe
        $idPeriodo = $gradoMateriaData['idPeriodo'] ?? null;

        unset($gradoMateriaData['idJornada'], $gradoMateriaData['idPeriodo']);
        $gradoMateria = GradoMateria::findOrFail($id);
        try {

            if ($request->hasFile('documentoMateria')) {

                if (!empty($gradoMateria->rutaDocumento)) {
                    Storage::delete($gradoMateria->rutaDocumento);
                }

                $document = $request->file('documentoMateria');
                $nombreArchivo = uniqid('document')
                    . '_grado' . $gradoMateriaData['idGradoPrograma'] . '_materia' . $gradoMateriaData['idMateria'] . '.pdf';
                $rutaAlmacenamiento = $document->storeAs('public/documentos/materia', $nombreArchivo);
                $rutaDocumentoGuardado = Storage::url($rutaAlmacenamiento);
                $gradoMateriaData['rutaDocumento'] = $rutaDocumentoGuardado;
            } else {
                if (!($gradoMateria->rutaDocumento)) {
                    $gradoMateriaData['rutaDocumento'] = null;
                }
            }
            unset($gradoMateriaData['documentoMateria']);

            $gradoMateria->update($gradoMateriaData);
            $gradoMateria->load('horarios.ficha');

            $horariosArray = $gradoMateria->horarios->toArray();

            $horariosFiltrados = array_values(array_filter($horariosArray, function ($horario) use ($idJornada) {
                // Verifica si 'ficha' existe
                return isset($horario['ficha']) &&
                    $horario['ficha']['idJornada'] == $idJornada;
            }));

            // Obtener el primer resultado o null si no se encontró
            $horarioFiltrado = !empty($horariosFiltrados) ? $horariosFiltrados[0] : null;

            $horario = HorarioMateria::with([
                'ficha.asignacionPeriodoPrograma',
                'ficha.jornada',
                'materia.materia',
                'materia.grado',
                'contrato.persona.usuario.persona',
                'dia',
                'infraestructura.sede.ciudad'
            ])->find($horarioFiltrado['id']);

            return response()->json($horario, 201);
        } catch (QueryException $th) {
            QueryUtil::handleQueryException($th);
        } catch (Exception $th) {
            QueryUtil::showExceptions($th);
        } catch (Exception $th) {
            return response()->json(['error' => $th], 500);
        }
    }

    /**
     * Delete rap with schedule
     * @param int $id
     * @param int|string $idAsignacionPeriodoJornada
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(int $idGradoMateria, int|string $idAsignacionPeriodoJornada)
    {

        try {
            DB::beginTransaction();  // Iniciar una transacción

            $gradoMateria = GradoMateria::findOrFail($idGradoMateria);
            $asignacionPeriodoJornada = Ficha::find($idAsignacionPeriodoJornada);

            $isHorarioOcupped = HorarioMateria::where('idGradoMateria', $idGradoMateria)
                ->where('idAsignacionPeriodoJornada', $idAsignacionPeriodoJornada)
                ->where(function ($query) {
                    $query->whereNotNull('idContrato')
                        ->orWhereNotNull('idDia')
                        ->orWhereNotNull('idInfraestructura');
                })
                ->exists();

            if ($isHorarioOcupped) {
                return response()->json(['message' => 'No puedes eliminar la materia asignada porque se encuentra en uso con horarios'], 400);
            }

            // Eliminar registros relacionados en la tabla HorarioMateria usando 'idGradoMateria'
            HorarioMateria::where('idGradoMateria', $idGradoMateria)->delete();

            // Eliminar el documento asociado, si existe
            if (!!($gradoMateria->rutaDocumento)) {
                Storage::delete($gradoMateria->rutaDocumento);
            }

            // return response()->json($gradoMateria->idMateria);
            $this->deleteRapHorario($gradoMateria, $asignacionPeriodoJornada);

            // Eliminar el GradoMateria
            $gradoMateria->delete();

            DB::commit();  // Confirmar la transacción

            return response()->json(['message' => 'Materia desasignada correctamente y registros relacionados eliminados'], 200);
        } catch (QueryException $th) {
            DB::rollBack();  // Revertir la transacción si ocurre un error
            QueryUtil::handleQueryException($th);
        } catch (Exception $th) {
            DB::rollBack();  // Revertir la transacción si ocurre un error
            QueryUtil::showExceptions($th);
        }
    }

    private function deleteRapHorario(GradoMateria $gradoMateria, Ficha $ficha): void
    {
        $competence = Materia::with('subMaterias')->where('id', $gradoMateria->idMateria)->first();

        if (!$competence) {
            return;
        }

        $idPrograma = $ficha->asignacionPeriodoPrograma->idPrograma;

        $raps = Materia::with(['grados'])->where('idMateriaPadre', $competence->id)
            ->whereHas('materiasAgregadas', function ($query) use ($idPrograma) {
                $query->where('idPrograma', $idPrograma);
            })
            ->get();

        foreach ($raps as $rap) {
            //MatriculaAcademica::where('idMateria', $rap->id)->update(['idGradoMateria' => NULL]);
            foreach ($rap->grados as $grado) {

                $schedule = HorarioMateria::where('idGradoMateria', $grado->id)
                    ->where('idAsignacionPeriodoJornada', $ficha->id)
                    ->first();

                if ($schedule) {
                    $schedule->delete();
                    $grado->delete();
                }
            }
        }
    }

    public function obtenerDocentesAsignados($idMateria)
    {
        try {
            $docentesAsignados = GradoMateria::where('idMateria', $idMateria)
                ->whereNotNull('idDocente')
                ->with('grado.programa', 'docentes.user.persona') // Cargar relación con el grado, su programa, el docente, su usuario y su persona
                ->get();

            $response = $docentesAsignados->map(function ($item) {
                return [
                    'gradoPrograma' => [
                        'id' => $item->grado->id,
                        'nombrePrograma' => $item->grado->programa->nombrePrograma,
                    ],
                    'nombreCompleto' => $item->docentes->user->persona->nombre1 . ' ' . $item->docentes->user->persona->nombre2 . ' ' . $item->docentes->user->persona->apellido1 . ' ' . $item->docentes->user->persona->apellido2,
                    'email' => $item->docentes->user->email,
                    'celular' => $item->docentes->user->persona->celular,
                    'rutaFoto' => $item->docentes->user->persona->rutaFoto
                ];
            });

            return response()->json($response);
        } catch (\Exception $e) {
            // Manejo de errores
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
