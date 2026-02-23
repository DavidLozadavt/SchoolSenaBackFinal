<?php

namespace App\Http\Controllers\gestion_horarios;

use App\Enums\EstadoGradoPrograma;
use DateTime;
use Exception;
use Carbon\Carbon;
use App\Util\KeyUtil;
use App\Util\QueryUtil;
use App\Models\Contrato;
use App\Models\GradoMateria;
use Illuminate\Http\Request;
use App\Models\SesionMateria;
use App\Models\HorarioMateria;
use App\Traits\CalculateEndDate;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use App\Enums\EstadoHorarioMateria;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Mail;
use Illuminate\Database\QueryException;
use App\Mail\EmailDocenteHorarioMateria;
use App\Models\AsignacionPeriodoPrograma;
use App\Http\Controllers\MateriaController;
use App\Models\Ficha;
use App\Models\Dia;

class HorarioMateriaController extends Controller
{

    private array $relations;
    private array $columns;

    function __construct()
    {
        $this->relations = [];
        $this->columns = ["*"];
    }

    public function index(Request $request)
    {
        try {
            $dataEncoded = $request->input('data_encoded');
            $data = $dataEncoded ? json_decode($dataEncoded, true) : null;

            $horarioMaterias = HorarioMateria::with($data['relations'] ?? $this->relations)
                ->where('estado', EstadoHorarioMateria::ASIGNADO)
                ->whereNotNull('idDia');

            if (isset($data['idPrograma'])) {
                $horarioMaterias = $horarioMaterias->whereHas('materia', function ($query) use ($data) {
                    $query->whereHas('grado', function ($query) use ($data) {
                        QueryUtil::where($query, $data, 'idPrograma');
                    });
                });
            }

            $horarioMaterias = QueryUtil::where($horarioMaterias, $data, 'idGradoMateria');

            if (isset($data['idFicha'])) {
                $horarioMaterias = $horarioMaterias
                    ->where('idFicha', $data['idFicha']);
            }
            $horarioMaterias = QueryUtil::where($horarioMaterias, $data, 'idDia');

            return response()->json($horarioMaterias->get($data['columns'] ?? $this->columns));
        } catch (QueryException $th) {
            QueryUtil::handleQueryException($th);
        } catch (Exception $th) {
            QueryUtil::showExceptions($th);
        }
    }

    /**
     * Create new horarioMateria
     *
     * @param Request $request
     * @return void
     */
    public function store(Request $request)
    {
        DB::beginTransaction();
        try {
            $data = $request->all();

            $idGradoMateria = $data['idGradoMateria'];
            $idFicha        = $data['idFicha'];
            $fechaInicio    = $data['fechaInicio'];
            $fechaFin       = $data['fechaFin'];
            $observacion    = $data['observacion'] ?? null;
            $horarios       = $data['horarios'] ?? [];

            if (empty($horarios)) {
                return response()->json(['message' => 'No se enviaron horarios'], 400);
            }

            // Buscar el primer horario 'vacío' (idDia nulo) que coincida
            $horarioBase = HorarioMateria::where('idGradoMateria', $idGradoMateria)
                ->where('idFicha', $idFicha)
                ->whereNull('idDia')
                ->first();

            $results = [];

            foreach ($horarios as $index => $horario) {
                $horarioData = [
                    'idDia'                       => $horario['idDia'],
                    'horaInicial'                 => $horario['horaInicio'],
                    'horaFinal'                   => $horario['horaFin'],
                    'fechaInicial'                => $fechaInicio,
                    'fechaFinal'                  => $fechaFin,
                    'observacion'                 => $observacion,
                    'idGradoMateria'              => $idGradoMateria,
                    'idFicha'                     => $idFicha,
                    'idInfraestructura'           => $horarioBase ? $horarioBase->idInfraestructura : null,

                ];

                // Determinar ID a excluir si estamos actualizando el horario base
                $excludeId = ($index === 0 && $horarioBase) ? $horarioBase->id : null;

                // VALIDACIÓN DE CRUCE
                if ($this->verCruce($horarioData, $excludeId)) {
                    $dia = Dia::find($horarioData['idDia']);
                    DB::rollBack();
                    return response()->json([
                        'message' => 'El horario del día ' . $dia->dia .
                            ' de ' . $horarioData['horaInicial'] . ' a ' . $horarioData['horaFinal'] .
                            ' se cruza con otra materia en el mismo rango de fechas.'
                    ], 422);
                }

                if ($index === 0 && $horarioBase) {
                    $horarioBase->update($horarioData);
                    $this->generatePastSessions($horarioBase);
                    $results[] = $horarioBase;
                } else {
                    $newHorario = HorarioMateria::create($horarioData);
                    $this->generatePastSessions($newHorario);
                    $results[] = $newHorario;
                }
            }

            DB::commit();
            return response()->json($results, 201);
        } catch (QueryException $th) {
            DB::rollBack();
            QueryUtil::handleQueryException($th);
        } catch (Exception $th) {
            DB::rollBack();
            QueryUtil::showExceptions($th);
        }
    }

    /**
     * Generate session records for past dates if the schedule starts in the past
     *
     * @param HorarioMateria $horarioMateria
     * @return void
     */
    private function generatePastSessions(HorarioMateria $horarioMateria)
    {
        $startDate = Carbon::parse($horarioMateria->fechaInicial);
        $endDate = Carbon::now();

        // If start date is in the future, do nothing
        if ($startDate->gt($endDate)) {
            return;
        }

        // Respect the schedule's end date if it's before today
        if ($horarioMateria->fechaFinal) {
            $scheduleEnd = Carbon::parse($horarioMateria->fechaFinal);
            if ($scheduleEnd->lt($endDate)) {
                $endDate = $scheduleEnd;
            }
        }

        $currentDate = $startDate->copy();

        // Get the last session number to increment from
        $lastSession = SesionMateria::where('idHorarioMateria', $horarioMateria->id)->max('numeroSesion') ?? 0;

        while ($currentDate->lte($endDate)) {
            $carbonDay = $currentDate->dayOfWeek;
            $dbIdDia = ($carbonDay == 0) ? 7 : $carbonDay;

            if ($dbIdDia == $horarioMateria->idDia) {
                // Check if session already exists for this date to avoid duplicates
                $exists = SesionMateria::where('idHorarioMateria', $horarioMateria->id)
                    ->whereDate('fechaSesion', $currentDate->toDateString())
                    ->exists();

                if (!$exists) {
                    $lastSession++;
                    SesionMateria::create([
                        'numeroSesion' => $lastSession,
                        'idHorarioMateria' => $horarioMateria->id,
                        'fechaSesion' => $currentDate->toDateString(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
            $currentDate->addDay();
        }
    }

    /**
     * validate time crossing in the infrastructure
     *
     * @param array $data
     * @param int $currentHorarioMateriaId
     * @return boolean
     */
    private function verCruce(array $data, $currentHorarioMateriaId = null): bool
    {
        // Filtrar por Ficha (Grupo)
        $query = HorarioMateria::where('idFicha', $data['idFicha'])
            // Filtrar por Día
            ->where('idDia', $data['idDia'])
            // Excluir el horario actual si se está editando
            ->when($currentHorarioMateriaId, function ($q) use ($currentHorarioMateriaId) {
                return $q->where('id', '<>', $currentHorarioMateriaId);
            });

        // Validar cruce de FECHAS
        $query->where(function ($q) use ($data) {
            $q->whereDate('fechaInicial', '<=', $data['fechaFinal'])
                ->whereDate('fechaFinal', '>=', $data['fechaInicial']);
        });

        // Validar cruce de HORAS
        $query->where(function ($q) use ($data) {
            $q->where(function ($sub) use ($data) {
                $sub->whereTime('horaInicial', '<', $data['horaFinal'])
                    ->whereTime('horaFinal', '>', $data['horaInicial']);
            });
        });

        return $query->exists();
    }

    /**
     * Verify periodo
     *
     * @param array $data
     * @return boolean
     */
    private function verifyPeriodo(array $data): bool
    {
        $query = HorarioMateria::where('idAsignacionPeriodoPrograma', $data['idAsignacionPeriodoPrograma'])
            ->where('idDia', $data['idDia'])
            ->where('idAsignacionPeriodoPrograma', '!=', $data['idAsignacionPeriodoPrograma']);
        if (isset($data['id'])) {
            $query->where('id', $data['id']);
        }
        return $query->exists();
    }

    public function update(Request $request, int $id)
    {
        $data = $request->all();

        DB::beginTransaction();

        try {

            $cantidadDias = $data['horarioMateria']['cantidadDias'] ?? null;
            if ($cantidadDias) {
                unset($data['horarioMateria']['cantidadDias']);
            }

            $fechaInicial = $data['horarioMateria']['fechaInicial'] ?? null;
            $idDia        = $data['horarioMateria']['idDia'] ?? null;

            $fechaInicialObj = Carbon::parse($fechaInicial);

            if ($fechaInicialObj->dayOfWeek !== $idDia) {
                return response()->json([
                    'message'      => "La fecha inicial no coincide con el día especificado",
                    'fechaInicial' => $fechaInicialObj->format('Y-m-d'),
                    'diaSemana'    => $fechaInicialObj->dayOfWeek
                ], 400);
            }

            $horarioMateria = HorarioMateria::find($id);

            $horasRap = $horarioMateria->materia->materia->horas ?? 0;

            $idFicha = $horarioMateria->idAsignacionPeriodoJornada;
            $ficha   = Ficha::find($idFicha);
            $porcentajeEjecucion = $ficha->porcentajeEjecucion;

            $totalHorasRap = ($horasRap * $porcentajeEjecucion) / 100;

            $fechaFinalScheduleCreate = $this->calculateEndDate(
                $horarioMateria->idGradoMateria,
                $data['horarioMateria']['fechaInicial'],
                $data['horarioMateria']['idDia'],
                intval($totalHorasRap),
                $data['horarioMateria']['horaInicial'],
                $data['horarioMateria']['horaFinal'],
                $horarioMateria->id
            );

            $data['horarioMateria']['fechaFinal'] = $fechaFinalScheduleCreate;

            $horarioMateriaExistResponse = $this->validateTimeHorarioMateriaUpdate($horarioMateria, $data['horarioMateria'], $fechaFinalScheduleCreate);

            $horarioMateriaExist = $horarioMateriaExistResponse->getData();

            if ($horarioMateriaExist->isExist) {
                return response()->json([
                    'message'        => 'Horario ocupado en la misma hora y día por el horario',
                    'horarioMateria' => $horarioMateriaExist->horarioMateria,
                ], 422);
            }

            $horarioMateria->update($data['horarioMateria']);
            $horarioMateria->load($data['relations'] ?? $this->relations);

            DB::commit();

            return response()->json($horarioMateria, 200);
        } catch (QueryException $th) {
            DB::rollBack();
            QueryUtil::handleQueryException($th);
        } catch (Exception $th) {
            DB::rollBack();
            QueryUtil::showExceptions($th);
        }
    }

    /**
     * Delete horarioMateria by id
     *
     * @param integer $id
     * @return void
     */
    public function destroy(int $id): JsonResponse
    {
        DB::beginTransaction();

        try {
            $horarioMateria = HorarioMateria::findOrFail($id);

            $idGradoMateria             = $horarioMateria->idGradoMateria;
            $idInfraestructura          = $horarioMateria->idInfraestructura;
            $idContrato                 = $horarioMateria->idContrato;
            $idAsignacionPeriodoJornada = $horarioMateria->idAsignacionPeriodoJornada;

            $sesionMaterias = $horarioMateria->sesionMaterias()->get();

            if (
                $sesionMaterias->isEmpty() ||
                $sesionMaterias->every(
                    fn($sesion): bool =>
                    $sesion->asistencia()->count() == 0 && $sesion->calificacionSesiones()->count() == 0
                )
            ) {
                $horarioMateria->sesionMaterias()->delete();
            } else {
                return response()->json([
                    'message' => 'No es posible eliminar este horario porque tiene sesiones con asistencias o calificaciones registradas.'
                ], 422);
            }

            $horarioMateria->delete();

            $maxHorarios = DB::table('horarioMateria')
                ->where('idGradoMateria', $idGradoMateria)
                ->where('idAsignacionPeriodoJornada', $idAsignacionPeriodoJornada)
                ->max('id');

            if (!$maxHorarios) {
                DB::commit();
                return response()->json(['message' => 'Horario eliminado y no quedan más horarios en este grado.'], 200);
            }

            $horario = HorarioMateria::find($maxHorarios);

            if (!$horario) {
                DB::commit();
                return response()->json(['message' => 'Horario eliminado, pero no se encontró un horario restante válido.'], 200);
            }

            $horasRap = $horario->materia->materia->horas ?? 0;

            $ficha = Ficha::find($horario->idAsignacionPeriodoJornada);

            if ($ficha && isset($horario->idDia)) {
                $porcentajeEjecucion = $ficha->porcentajeEjecucion;
                $totalHorasRap = ($horasRap * $porcentajeEjecucion) / 100;

                $fechaFinalSchedule = $this->calculateEndDate(
                    $idGradoMateria,
                    $horario->fechaInicial,
                    $horario->idDia,
                    intval($totalHorasRap),
                    $horario->horaInicial,
                    $horario->horaFinal,
                    $horario->id
                );
            } else {
                $fechaFinalSchedule = null;
            }

            $horario->update([
                'idInfraestructura' => $idInfraestructura,
                'fechaFinal'        => $fechaFinalSchedule,
                'idContrato'        => $idContrato,
            ]);

            HorarioMateria::where('idGradoMateria', $idGradoMateria)
                ->whereNotNull(['idDia', 'horaInicial', 'horaFinal'])
                ->orderBy('fechaInicial', 'asc')
                ->get();

            DB::commit();

            return response()->json($horario, 200);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Ocurrio un error al eliminar el horario' . $e], 500);
        }
    }

    private function getDataHorarioMaterias($data)
    {
        $horarioMaterias = HorarioMateria::with($data['relations'] ?? $this->relations)
            ->whereNotNull('idDia');

        // Filter by `idPrograma` if it exists
        if (isset($data['idPrograma'])) {
            $horarioMaterias->whereHas('materia.grado', function ($query) use ($data) {
                $query->where('idPrograma', $data['idPrograma']);
            });
        }

        // Filter by `idGradoMateria` if it exists
        if (isset($data['idGradoMateria'])) {
            $horarioMaterias->where('idGradoMateria', $data['idGradoMateria']);
        }

        // Filter by `idAsignacionPeriodoJornada` if it exists
        if (isset($data['idAsignacionPeriodoJornada'])) {
            $horarioMaterias->where('idAsignacionPeriodoJornada', $data['idAsignacionPeriodoJornada']);
        }

        // Filter by `idDia` if it exists
        if (isset($data['idDia'])) {
            $horarioMaterias->where('idDia', $data['idDia']);
        }

        return $horarioMaterias->get();
    }

    /**
     * Update teacher to matter
     *
     * @param Request $request
     * @param string $id
     * @return JsonResponse
     */
    public function updateTeacherHorarioMateria(Request $request): JsonResponse
    {
        $idContrato = $request->input('idContrato');
        $horarios = $request->input('horarios');

        if (!$idContrato || !is_array($horarios)) {
            return response()->json([
                'message' => 'El contrato y la lista de horarios son obligatorios'
            ], 400);
        }

        try {
            DB::beginTransaction();

            foreach ($horarios as $h) {
                $horarioMateria = HorarioMateria::findOrFail($h['id']);

                // Validar cruces para el docente
                $validacion = $this->validateHorariosByDocente($horarioMateria, $idContrato);
                $resultado = $validacion->getData();

                if ($resultado->isExist) {
                    DB::rollBack();
                    return response()->json([
                        'message' => "El docente ya tiene una clase asignada para el mismo horario."
                    ], 422);
                }

                $horarioMateria->update([
                    'idContrato' => $idContrato,
                    'estado' => EstadoHorarioMateria::ASIGNADO
                ]);
            }

            DB::commit();

            return response()->json([
                'message' => 'Instructor asignado correctamente a todos los horarios seleccionados'
            ], 200);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al asignar el instructor',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Unassign teacher to schedule by idGradoMateria
     * @param string|int $idGradoMateria
     * @return JsonResponse
     */
    public function unassignTeacherSchedule(string|int $idGradoMateria): JsonResponse
    {
        try {
            $horarios = horariomateria::where('idGradoMateria', $idGradoMateria)->get();

            if ($horarios->isEmpty()) {
                return response()->json([
                    'message' => 'No se encontró ninguna asignación para el grado/materia especificado'
                ], 404);
            }

            DB::beginTransaction();

            $updatedRows = horariomateria::where('idGradoMateria', $idGradoMateria)
                ->update(['idContrato' => null]);

            if ($updatedRows === 0) {
                DB::rollBack();
                return response()->json([
                    'message' => 'No se realizó ninguna actualización, es posible que ya estuviera desasignado'
                ], 400);
            }

            DB::commit();

            return response()->json(['message' => 'Docente desasignado correctamente'], 200);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'No se logró desasignar el docente debido a un error interno'
            ], 500);
        }
    }

    /**
     * Update schedule with teacher without validating intersections
     * @param \Illuminate\Http\Request $request
     * @param string $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateTeacherHorarioMateriaWithoutValidating(Request $request, string $id): JsonResponse
    {
        $data = $request->all();
        $horarioMateria = HorarioMateria::findOrFail($id);

        if ($this->getDataHorarioMaterias($data)->isEmpty()) {
            return response()->json([
                'message' => 'No hay horarios de materias para asignar al docente',
            ], 422);
        }

        horariomateria::where('idGradoMateria', $horarioMateria->idGradoMateria)->update(['idContrato' => $data['idContrato']]);

        $horarioMateria->update([
            'idContrato' => $data['idContrato'],
        ]);
        $this->getDataAndSendEmail($horarioMateria);
        return response()->json($horarioMateria->load('asignacionPeriodoProgramaJornada.jornada'), 200);
    }

    /**
     * Validate horario to assign teacher
     *
     * @param HorarioMateria $horarioMateria
     * @param string $idContrato
     * @return JsonResponse
     */
    private function validateHorariosByDocente(HorarioMateria $horarioMateria, string $idContrato): JsonResponse
    {
        $conflictingHorario = HorarioMateria::with(
            'infraestructura.sede.ciudad',
            'dia',
            'materia.grado',
            'materia.materia',
            'ficha.jornada',
            'contrato.persona'
        )
            ->when($horarioMateria->id, function ($query) use ($horarioMateria) {
                $query->where('id', '<>', $horarioMateria->id);
            })
            ->where('estado', EstadoHorarioMateria::ASIGNADO)
            ->where('idContrato', $idContrato)
            ->where('idDia', $horarioMateria->idDia)
            ->where(function ($query) use ($horarioMateria) {
                $query->where(function ($query) use ($horarioMateria) {
                    $query->whereTime('horaInicial', '>=', $horarioMateria->horaInicial)
                        ->whereTime('horaInicial', '<', $horarioMateria->horaFinal);
                })->orWhere(function ($query) use ($horarioMateria) {
                    $query->whereTime('horaFinal', '>', $horarioMateria->horaInicial)
                        ->whereTime('horaFinal', '<=', $horarioMateria->horaFinal);
                })->orWhere(function ($query) use ($horarioMateria) {
                    $query->whereTime('horaInicial', '<', $horarioMateria->horaInicial)
                        ->whereTime('horaFinal', '>', $horarioMateria->horaFinal);
                });
            })
            ->when(isset($horarioMateria->fechaInicial) ? $horarioMateria->fechaInicial : null, function ($query) use ($horarioMateria) {
                $query->whereDate('fechaInicial', '>=', $horarioMateria->fechaInicial)
                    ->whereDate('fechaFinal', '<=', $horarioMateria->fechaFinal);
            })
            ->first();

        return response()->json([
            'isExist' => !is_null($conflictingHorario),
            'horarioMateria' => $conflictingHorario
        ]);
    }

    /**
     * Assign teacher to matter
     *
     * @param integer $idGradoMateria
     * @param Request $request  => { "idContrato": 1, "idHorarioMateria": 1 }
     * @return JsonResponse
     */
    public function assignTeacherToMatter(Request $request, int $idHorarioMateria)
    {
        $data = $request->all();

        $horarioMateria = HorarioMateria::where('id', $idHorarioMateria)->get();

        foreach ($horarioMateria as $horario) {
            if ($horario->idContrato != null && $horario->idContrato === $data['idContrato']) {
                return response()->json(['error' => 'El docente ya se encuentra asignado a esta materia'], 500);
            }
        }

        if ($horarioMateria->isEmpty()) {
            return response()->json(['error' => 'No hay registros de horario materia'], 404);
        }

        $newHorariosMateria = [];

        foreach ($horarioMateria as $horario) {
            $horario->update([
                'fechaFinal' => now(),
                'idContrato' => $data['idContrato'] //
            ]);

            $newHorarioMateria = HorarioMateria::create([
                'horaInicial'       => $horario->horaInicial,
                'horaFinal'         => $horario->horaFinal,
                'estado'            => $horario->estado,
                'idGradoMateria'    => $horario->idGradoMateria,
                'idDia'             => $horario->idDia,
                'idInfraestructura' => $horario->idInfraestructura,
                'fechaInicial'      => now(),
                'fechaFinal'        => null,
                'idContrato'        => $data['idContrato'],
                'idAsignacionPeriodoJornada' => $horario->idAsignacionPeriodoJornada,
            ]);

            $newHorariosMateria[] = $newHorarioMateria->load('materia.docente.user.persona');
        }

        $this->getDataAndSendEmail($newHorarioMateria);

        return response()->json(end($newHorariosMateria), 201);
    }

    /**
     * Create new horarioMateria in base with id other
     *
     * @param Request $request
     * @param string $idHorarioMateria
     * @return void
     */
    public function updateCreateNewHorario(Request $request, $idHorarioMateria)
    {
        $data = $request->all();

        $cantidadDias = $data['horarioMateria']['cantidadDias'] ?? null;
        if ($cantidadDias) {
            unset($data['horarioMateria']['cantidadDias']);
        }

        $fechaInicial = $data['horarioMateria']['fechaInicial'] ?? null;
        $idDia        = $data['horarioMateria']['idDia'] ?? null;

        $fechaInicialObj = Carbon::parse($fechaInicial);

        if ($fechaInicialObj->dayOfWeek !== $idDia) {
            return response()->json([
                'message'      => "La fecha inicial no coincide con el día especificado",
                'fechaInicial' => $fechaInicialObj->format('Y-m-d'),
                'diaSemana'    => $fechaInicialObj->dayOfWeek
            ], 400);
        }

        $horariosMateria = HorarioMateria::with(['materia.materia'])->where('id', $idHorarioMateria)->get();

        $horasRap                   = $horariosMateria[0]['materia']['materia']['horas'] ?? 0;
        $idGradoMateria             = $horariosMateria[0]['idGradoMateria'];
        $idMateria                  = $horariosMateria[0]->materia->idMateria;
        $idAsignacionPeriodoJornada = $horariosMateria[0]->idAsignacionPeriodoJornada;

        $idFicha             = $horariosMateria[0]['idAsignacionPeriodoJornada'];
        $ficha               = Ficha::find($idFicha);
        $porcentajeEjecucion = $ficha->porcentajeEjecucion;

        if (intval($porcentajeEjecucion) === 0) {
            return response()->json([
                'message' => 'No puedes crear un nuevo horario porque el porcentaje de ejecución de la ficha es cero'
            ], 422);
        }

        // Buscar ultimo horario que tenga el mismo RAP y sea INTERRUMPIDO
        $horarioInterrumpido = HorarioMateria::where('idAsignacionPeriodoJornada', $idAsignacionPeriodoJornada)
            ->where('estado', EstadoHorarioMateria::INTERRUMPIDO)
            ->whereHas('materia', function ($query) use ($idMateria) {
                $query->where('idMateria', $idMateria);
            })
            ->first();

        $totalHorasRap = ($horasRap * $porcentajeEjecucion) / 100;

        if ($horarioInterrumpido) {
            $totalHorasRap = $this->calculateHoursWorked($idMateria, $idAsignacionPeriodoJornada, $totalHorasRap);
        }

        DB::beginTransaction();

        try {

            $fechaFinalScheduleCreate = $this->calculateEndDate(
                $idGradoMateria,
                $data['horarioMateria']['fechaInicial'],
                $data['horarioMateria']['idDia'],
                intval($totalHorasRap),
                $data['horarioMateria']['horaInicial'],
                $data['horarioMateria']['horaFinal']
            );

            $horarioMateriaExistResponse = $this->validateTimeHorarioMateria($horariosMateria, $data['horarioMateria'], $fechaFinalScheduleCreate);
            $horarioMateriaExist = $horarioMateriaExistResponse->getData();

            if ($horarioMateriaExist->isExist) {
                return response()->json([
                    'message'        => 'Horario ocupado en la misma hora y día por el horario',
                    'horarioMateria' => $horarioMateriaExist->horarioMateria,
                ], 422);
            }

            if ($horariosMateria->isEmpty()) {
                return response()->json(['error' => 'No hay registros de horario materia'], 404);
            }

            $newHorariosMateria = [];

            $cantHorarios = $horariosMateria->count();
            $endDateFound = false; // Validar que sus fechas finales del mismo grado materia no sean iguales

            foreach ($horariosMateria as $horario) {

                if (isset($horario) && $cantHorarios > 1) {
                    $fechaHorario  = DateTime::createFromFormat('Y-m-d', $horario->fechaFinal);
                    $fechaSchedule = DateTime::createFromFormat('Y-m-d', $fechaFinalScheduleCreate);
                    if ($fechaHorario && $fechaSchedule && $fechaHorario == $fechaSchedule) {
                        $endDateFound = true;
                        break;
                    }
                }

                $idGradoMateria = isset($data['horarioMateria']['idGradoMateria']) ? $data['horarioMateria']['idGradoMateria'] : $horario->idGradoMateria;
                $fechaInicial   = isset($fechaInicial)                             ? $fechaInicial                             : $horario->fechaInicial;
                $horaInicial    = isset($data['horarioMateria']['horaInicial'])    ? $data['horarioMateria']['horaInicial']    : $horario->horaInicial;
                $horaFinal      = isset($data['horarioMateria']['horaFinal'])      ? $data['horarioMateria']['horaFinal']      : $horario->horaFinal;
                $idDia          = isset($idDia)                                    ? $idDia                                    : $horario->idDia;
                $horasRap       = $horario->materia->materia->horas;
                $creditos       = $horario->materia->materia->creditos;

                if (!$horasRap) {
                    return response()->json(['message' => 'No se puede crear un nuevo horario debido a que el rap ' . $horario->materia->materia->nombreMateria . ' no contiene horas'], 422);
                }

                $newHorarioMateria = HorarioMateria::create([
                    'horaInicial'       => $horaInicial,
                    'horaFinal'         => $horaFinal,
                    'estado'            => isset($data['horarioMateria']['estado']) ? $data['horarioMateria']['estado'] : EstadoHorarioMateria::ASIGNADO,
                    'idGradoMateria'    => $idGradoMateria,
                    'idDia'             => $idDia,
                    'idInfraestructura' => isset($data['horarioMateria']['idInfraestructura']) ? $data['horarioMateria']['idInfraestructura'] : $horario->idInfraestructura,
                    'fechaInicial'      => $fechaInicial,
                    'fechaFinal'        => $fechaFinalScheduleCreate,
                    'idContrato'        => isset($data['horarioMateria']['idContrato']) ? $data['horarioMateria']['idContrato'] : null,
                    'idAsignacionPeriodoJornada' => $horario->idAsignacionPeriodoJornada,
                    'observacion'                => isset($data['horarioMateria']['observacion']) ? $data['horarioMateria']['observacion'] : null,
                ]);

                if ($cantidadDias) {
                    $this->createSessions($cantidadDias, $newHorarioMateria);
                }

                $newHorariosMateria[] = $newHorarioMateria->load('dia', 'materia.materia', 'materia.grado.programa', 'asignacionPeriodoProgramaJornada.asignacionPeriodoPrograma.periodo');
            }

            if ($endDateFound) {
                DB::rollBack();
                return response()->json([
                    'message' => 'No es posible crear este horario porque la fecha final coincide con otro existente. Por favor, ajusta los horarios y vuelve a intentarlo.'
                ], 422);
            }

            DB::commit();

            return response()->json(end($newHorariosMateria), 201);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error al crear el horario'], 500);
        }
    }

    /**
     * Calculates the remaining hours to be completed for a given RAP.
     *
     * @param string|int $idMateria ID of the subject.
     * @param string|int $idAsignacionPeriodoJornada ID of the period-assignment schedule.
     * @param string|int $totalHorasRap Total expected hours for the RAP.
     * @return int Remaining hours to be completed.
     */
    private function calculateHoursWorked(string|int $idMateria, string|int $idAsignacionPeriodoJornada, string|int $totalHorasRap): int
    {
        // Calcular la cantidad de horas restantes para esos nuevos horarios que han sido INTERRUMPIDO y que estan para el mismo rap
        $totalHorasRealizadas = DB::table('sesionMateria as s')
            ->join('horarioMateria as hm', 'hm.id', '=', 's.idHorarioMateria')
            ->join('gradoMateria as gm', 'gm.id', '=', 'hm.idGradoMateria')
            ->where('gm.idMateria',  $idMateria)
            ->where('hm.idAsignacionPeriodoJornada', $idAsignacionPeriodoJornada)
            ->selectRaw('SUM(TIMESTAMPDIFF(MINUTE, hm.horaInicial, hm.horaFinal)) / 60 AS totalHorasRealizadas')
            ->value('totalHorasRealizadas');
        return $totalHorasRealizadas ? $totalHorasRap - $totalHorasRealizadas : $totalHorasRap;
    }

    /**
     * Create all sessions where the start date is less than today to create a new schedule
     * @param string|int $cantDays
     * @param \App\Models\HorarioMateria $horarioMateria
     * @return void
     */
    private function createSessions(string|int $cantDays, HorarioMateria $horarioMateria): void
    {
        $cantidad = intval($cantDays);
        $sesiones = [];

        for ($key = 0; $key < $cantidad; $key++) {
            $sesiones[] = [
                'numeroSesion'     => $key + 1,
                'idHorarioMateria' => $horarioMateria->id,
                'fechaSesion'      => null,
                'created_at'       => now(),
                'updated_at'       => now(),
            ];
        }

        SesionMateria::insert($sesiones);
    }

    /**
     * Validate time of horarioMateria
     *
     * @param mixed $horariosMateria
     * @param mixed $data
     * @return JsonResponse
     */
    private function validateTimeHorarioMateria($horariosMateria, $data, $fechaFinalNewSchedule = null): JsonResponse
    {
        $idAsignacionPeriodoJornada = $horariosMateria[0]['idAsignacionPeriodoJornada']  ?? $horariosMateria->idAsignacionPeriodoJornada;
        $idGradoMateria             = $horariosMateria[0]['idGradoMateria']              ?? $horariosMateria->idGradoMateria;
        $horasRap                   = $horariosMateria[0]['materia']['materia']['horas'] ?? 0;

        $idDia                      = $data['idDia'];
        $idContrato                 = $data['idContrato']   ?? null;
        $fechaInicialScheduleCreate = $data['fechaInicial'] ?? null;
        $horaInicial                = $data['horaInicial']  ?? null;
        $horaFinal                  = $data['horaFinal']    ?? null;


        foreach ($horariosMateria as $horarioMateria) {
            $fechaInicial = $horarioMateria['fechaInicial'] ?? $horarioMateria->fechaInicial;
            $fechaFinal   = $horarioMateria['fechaFinal']   ?? $horarioMateria->fechaFinal;

            $gradoMateria    = GradoMateria::findOrFail($idGradoMateria);
            $idGradoPrograma = $gradoMateria->idGradoPrograma;

            $query = HorarioMateria::with([
                'infraestructura.sede.ciudad',
                'dia',
                'materia.grado',
                'materia.materia',
                'asignacionPeriodoProgramaJornada.jornada',
                'contrato.persona'
            ])
                ->when(isset($horarioMateria->id) ? $horarioMateria->id : null, function ($query) use ($horarioMateria) {
                    return $query->where('id', '<>', $horarioMateria->id);
                })
                ->where('idAsignacionPeriodoJornada', $idAsignacionPeriodoJornada)
                ->where('idDia', $idDia)
                ->where('idGradoMateria', $idGradoMateria)
                ->whereHas('materia', function ($query) use ($idGradoPrograma) {
                    $query->where('idGradoPrograma', $idGradoPrograma);
                });

            if ($idContrato) {
                $query->where('idContrato', $idContrato);
            }

            $query->where(function ($query) use ($data, $idDia, $fechaInicial, $fechaFinalNewSchedule) {
                $query->where(function ($query) use ($data, $idDia) {
                    $query->where('idDia', $idDia)
                        ->whereTime('horaInicial', '<=', $data['horaInicial'])
                        ->whereTime('horaFinal', '>', $data['horaInicial']);
                })
                    ->orWhere(function ($query) use ($data, $idDia) {
                        $query->where('idDia', $idDia)
                            ->whereTime('horaInicial', '<', $data['horaFinal'])
                            ->whereTime('horaFinal', '>=', $data['horaFinal']);
                    })
                    ->orWhere(function ($query) use ($data, $idDia) {
                        $query->where('idDia', $idDia)
                            ->whereTime('horaInicial', '>=', $data['horaInicial'])
                            ->whereTime('horaFinal', '<=', $data['horaFinal']);
                    })
                    ->orWhere(function ($query) use ($data, $idDia) {
                        $query->where('idDia', $idDia)
                            ->whereTime('horaInicial', '<=', $data['horaInicial'])
                            ->whereTime('horaFinal', '>=', $data['horaFinal']);
                    })
                    ->when(isset($fechaInicial) ? $fechaInicial : null, function ($query) use ($fechaInicial, $fechaFinalNewSchedule) {
                        $query->whereDate('fechaInicial', '>=', $fechaInicial)
                            ->whereDate('fechaFinal', '<=', $fechaFinalNewSchedule);
                    });
            });
        }

        return response()->json([
            'isExist'        => $query->exists(),
            'horarioMateria' => $query->first(),
        ]);
    }

    /**
     * Validate time of horarioMateria only to update
     *
     * @param mixed $horariosMateria
     * @param mixed $data
     * @return JsonResponse
     */
    private function validateTimeHorarioMateriaUpdate($horarioMateria, $data, $fechaFinalNewSchedule = null): JsonResponse
    {
        $idAsignacionPeriodoJornada = $horariosMateria[0]['idAsignacionPeriodoJornada']  ?? $horarioMateria->idAsignacionPeriodoJornada;
        $idGradoMateria             = $horariosMateria[0]['idGradoMateria']              ?? $horarioMateria->idGradoMateria;
        $horasRap                   = $horariosMateria[0]['materia']['materia']['horas'] ?? 0;

        $idDia                      = $data['idDia'];
        $idContrato                 = $data['idContrato']   ?? null;
        $fechaInicialScheduleCreate = $data['fechaInicial'] ?? null;
        $horaInicial                = $data['horaInicial']  ?? null;
        $horaFinal                  = $data['horaFinal']    ?? null;


        $fechaInicial = $horarioMateria['fechaInicial'] ?? $horarioMateria->fechaInicial;
        $fechaFinal   = $horarioMateria['fechaFinal']   ?? $horarioMateria->fechaFinal;

        $gradoMateria    = GradoMateria::findOrFail($idGradoMateria);
        $idGradoPrograma = $gradoMateria->idGradoPrograma;

        $query = HorarioMateria::with([
            'infraestructura.sede.ciudad',
            'dia',
            'materia.grado',
            'materia.materia',
            'asignacionPeriodoProgramaJornada.jornada',
            'contrato.persona'
        ])
            ->when(isset($horarioMateria->id) ? $horarioMateria->id : null, function ($query) use ($horarioMateria) {
                return $query->where('id', '<>', $horarioMateria->id);
            })
            ->where('idAsignacionPeriodoJornada', $idAsignacionPeriodoJornada)
            ->where('idDia', $idDia)
            ->where('idGradoMateria', $idGradoMateria)
            ->whereHas('materia', function ($query) use ($idGradoPrograma) {
                $query->where('idGradoPrograma', $idGradoPrograma);
            });

        if ($idContrato) {
            $query->where('idContrato', $idContrato);
        }

        $query->where(function ($query) use ($data, $idDia, $fechaInicial, $fechaFinalNewSchedule) {
            $query->where(function ($query) use ($data, $idDia) {
                $query->where('idDia', $idDia)
                    ->whereTime('horaInicial', '<=', $data['horaInicial'])
                    ->whereTime('horaFinal', '>', $data['horaInicial']);
            })
                ->orWhere(function ($query) use ($data, $idDia) {
                    $query->where('idDia', $idDia)
                        ->whereTime('horaInicial', '<', $data['horaFinal'])
                        ->whereTime('horaFinal', '>=', $data['horaFinal']);
                })
                ->orWhere(function ($query) use ($data, $idDia) {
                    $query->where('idDia', $idDia)
                        ->whereTime('horaInicial', '>=', $data['horaInicial'])
                        ->whereTime('horaFinal', '<=', $data['horaFinal']);
                })
                ->orWhere(function ($query) use ($data, $idDia) {
                    $query->where('idDia', $idDia)
                        ->whereTime('horaInicial', '<=', $data['horaInicial'])
                        ->whereTime('horaFinal', '>=', $data['horaFinal']);
                })
                ->when(isset($fechaInicial) ? $fechaInicial : null, function ($query) use ($fechaInicial, $fechaFinalNewSchedule) {
                    $query->whereDate('fechaInicial', '>=', $fechaInicial)
                        ->whereDate('fechaFinal', '<=', $fechaFinalNewSchedule);
                });
        });

        return response()->json([
            'isExist'        => $query->exists(),
            'horarioMateria' => $query->first(),
        ]);
    }


    /**
     * Send email with data horarioMateria
     *
     * @param HorarioMateria $horarioMateria
     * @return void
     */
    private function getDataAndSendEmail(HorarioMateria $horarioMateria): void
    {
        $contrato = $horarioMateria->contrato;
        $persona = $contrato->persona;

        $gradoMateria = $horarioMateria->materia;
        $materia = $gradoMateria->materia;

        // Create notification
        $this->createNotification($persona['id'], $materia['nombreMateria'], 'ASIGNACIÓN DE MATERIA', 'SE HA CREADO UNA ASIGNACIÓN A LA MATERIA ');

        $this->sendEmailTeacher($persona['email'], $persona['nombre1'], $persona['apellido1'], $materia['nombreMateria']);
    }

    /**
     * Send email to teacher when assign to matter
     *
     * @param string $email
     * @param string $nombre1
     * @param string $apellido1
     * @param string $materia
     * @return void
     */
    public function sendEmailTeacher($email, $nombre1, $apellido1, $materia): void
    {
        //Mail::to($email)->send(new EmailDocenteHorarioMateria($nombre1, $apellido1, $materia));
    }

    /**
     * Create notification when assign teacher to matter
     *
     * @param Request $request
     * @return void
     */
    public function createNotification($idUsuarioReceptor, $materia, $asunto, $mensaje): void
    {
        $user = KeyUtil::user();

        $idPersona = $user->persona->id;

        DB::table('notificacion')->insert([
            'idEstado'           => 1,
            'fecha'              => now(),
            'hora'               => now(),
            'asunto'             => $asunto, //'ASIGNACIÓN DE MATERIA'
            'mensaje'            => $mensaje . ' ' . $materia, // 'SE HA CREADO UNA ASIGNACIÓN A LA MATERIA '
            'idUsuarioReceptor'  => $idUsuarioReceptor, // Estudiante
            'idUsuarioRemitente' => $idPersona, // Admin
            'idTipoNotificacion' => 2,
            'idCompany'          => KeyUtil::idCompany(),
            'created_at'         => now(),
            'updated_at'         => now(),
        ]);
    }

    /**
     * Get matters assigned and filtered by idPeriodo, idPrograma and idJornada
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getMattersByJornadaPeriodo(Request $request): JsonResponse
    {

        $dataEncoded = $request->input('data_encoded');
        $data        = $dataEncoded ? json_decode($dataEncoded, true) : null;

        $idPeriodo   = $data['idPeriodo'];
        $idPrograma  = $data['idPrograma'];
        $idJornada   = $data['idJornada'];

        $matters = AsignacionPeriodoPrograma::join('periodo as P', 'P.id', '=', 'asignacionPeriodoPrograma.idPeriodo')
            ->join('programa as PR', 'PR.id', '=', 'asignacionPeriodoPrograma.idPrograma')
            ->join('gradoPrograma as GP', 'GP.idPrograma', '=', 'PR.id')
            ->join('asignacionPeriodoProgramaJornada as APPJ', 'APPJ.idAsignacion', '=', 'asignacionPeriodoPrograma.id')
            ->join('jornada as J', 'J.id', '=', 'APPJ.idJornada')
            ->join('gradoMateria as GM', 'GM.idGradoPrograma', '=', 'GP.id')
            ->join('Materia as M', function ($join) { // Verify if matter is the company
                $join->on('M.id', '=', 'GM.idMateria')
                    ->where('M.idCompany', '=', KeyUtil::idCompany());
            })
            ->where('P.id', $idPeriodo)
            ->where('PR.id', $idPrograma)
            ->where('J.id', $idJornada)
            ->select('M.*') // Only matters data
            ->get();

        if ($matters->count() == 0) {
            return response()->json(['message' => 'No hay materias, verifica que existan y esten asignadas'], 404);
        }

        return response()->json($matters);
    }

    /**
     * Get matters assigned and filtered by idPeriodo, idPrograma and idJornada
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getMattersByJornadaPeriodoPrograma($idFicha): JsonResponse
    {
        try {
            if (empty($idFicha)) {
                return response()->json([
                    'message' => 'El idFicha es obligatorio para realizar la consulta'
                ], 400);
            }

            $materias = HorarioMateria::where('idFicha', $idFicha)
                ->with('gradoMateria.materia')
                ->get();

            if ($materias->isEmpty()) {
                return response()->json([
                    'message' => 'No se encontraron registros para la ficha enviada'
                ], 404);
            }

            return response()->json([
                'message' => 'Consulta realizada correctamente',
                'data' => $materias
            ], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Error al consultar las materias por jornada y periodo',
                'error'   => $e->getMessage(),
                // útil en desarrollo, quítalo en producción si quieres
                'line'    => $e->getLine(),
                'file'    => $e->getFile(),
            ], 500);
        }
    }

    /**
     * Obtener trimestres de una ficha con cálculo automático de horas
     * Este método agrupa horarios por trimestre y materia, 
     * calculando horas totales, actuales y faltantes para cada materia.
     */
    public function getTrimestresFicha($idFicha)
    {
        if (empty($idFicha)) {
            return response()->json(['message' => 'No se proporcionó ninguna ficha'], 400);
        }

        $fichaPrograma = Ficha::where('id', $idFicha)
            ->with('aperturarPrograma')
            ->firstOrFail();

        $idPrograma = $fichaPrograma->aperturarPrograma->idPrograma;

        if (!$idPrograma) {
            return response()->json([
                'message' => 'La ficha no tiene un programa asociado',
                'id' => $idPrograma
            ], 200);
        }

        // Traemos los horarios con sus relaciones
        $horarios = HorarioMateria::where('idFicha', $idFicha)
            ->whereHas('gradoMateria.gradoPrograma', function ($q) use ($idPrograma) {
                $q->where('idPrograma', $idPrograma);
            })
            ->with([
                'gradoMateria.materia',
                'gradoMateria.gradoPrograma.grado',
                'gradoMateria.gradoPrograma',
                'dia',
                'contrato.persona:id,nombre1,nombre2,apellido1,apellido2,rutaFoto,email'
            ])
            ->get();

        if ($horarios->isEmpty()) {
            return response()->json(['message' => 'No se encontraron materias asignadas', 'data' => []], 200);
        }

        // Reorganizamos la data
        $resultado = $horarios
            // Agrupamos por GRADO (trimestre)
            ->groupBy(function ($h) {
                return $h->gradoMateria
                    ->gradoPrograma
                    ->grado
                    ->id;
            })

            // Recorremos cada grado
            ->map(function ($horariosPorGrado) {
                // Tomamos el grado una sola vez
                $gradoPrograma = $horariosPorGrado->first()
                    ->gradoMateria
                    ->gradoPrograma;

                if (!$gradoPrograma) {
                    return [
                        'grado' => null,
                        'materias' => [],
                        'idGradoPrograma' => null
                    ];
                }

                $hoy = Carbon::today();

                $estadoActual = $gradoPrograma->estado;

                if (!in_array($estadoActual, [
                    EstadoGradoPrograma::FINALIZADO,
                    EstadoGradoPrograma::CANCELADO,
                    EstadoGradoPrograma::INTERRUMPIDO
                ])) {

                    if ($hoy->lt(Carbon::parse($gradoPrograma->fechaInicio))) {
                        $gradoPrograma->estado = EstadoGradoPrograma::PENDIENTE;
                    } elseif ($hoy->between(
                        Carbon::parse($gradoPrograma->fechaInicio),
                        Carbon::parse($gradoPrograma->fechaFin)
                    )) {
                        $gradoPrograma->estado = EstadoGradoPrograma::EN_CURSO;
                    } else {
                        $gradoPrograma->estado = EstadoGradoPrograma::FINALIZADO;
                    }

                    $gradoPrograma->save();
                }

                return [
                    'grado' => [
                        'id' => $gradoPrograma->grado->id,
                        'nombre' => $gradoPrograma->grado->nombreGrado,
                        'numeroGrado' => $gradoPrograma->grado->numeroGrado,
                        'fechaInicio' => $gradoPrograma->fechaInicio,
                        'fechaFin' => $gradoPrograma->fechaFin,
                        'estado' => $gradoPrograma->estado,
                        'idGradoPrograma' => $gradoPrograma->id
                    ],
                    // Agrupamos ahora por materia
                    'materias' => $horariosPorGrado
                        ->groupBy('gradoMateria.idMateria')
                        ->filter(function ($horariosPorMateria) {
                            $materia = $horariosPorMateria->first()
                                ->gradoMateria
                                ->materia;

                            return is_null($materia->idMateriaPadre);
                        })
                        ->map(function ($horariosPorMateriaPadre, $idMateriaPadre) use ($horariosPorGrado) {
                            $materiaPadre = $horariosPorMateriaPadre->first()
                                ->gradoMateria
                                ->materia;

                            // Obtenemos todos los horarios de los hijos (RAPs) de esta materia padre
                            // que pertenecen a este mismo grado/trimestre
                            $horariosDeHijos = $horariosPorGrado->filter(function ($h) use ($idMateriaPadre) {
                                return $h->gradoMateria->materia->idMateriaPadre == $idMateriaPadre;
                            });

                            // Combinamos los horarios del padre (si tiene) con los de sus hijos
                            $todosLosHorarios = $horariosPorMateriaPadre->concat($horariosDeHijos);

                            // AQUÍ CALCULAMOS LAS HORAS (usando todos los horarios combinados)
                            $horasData = $this->calcularHorasMateria($todosLosHorarios);
                            $gradoMateriaParaEstado = $horariosPorMateriaPadre->first()->gradoMateria;

                            return [
                                'id' => $materiaPadre->id,
                                'nombre' => $materiaPadre->nombreMateria,
                                'descripcion' => $materiaPadre->descripcion,
                                'estado' => $gradoMateriaParaEstado->estado,
                                'horasTotales' => $horasData['horasTotales'],
                                'horasActuales' => $horasData['horasActuales'],
                                'horasFaltantes' => $horasData['horasFaltantes'],
                                'porcentajeAvance' => $horasData['porcentajeAvance'],

                                // Horarios de los hijos agrupados por asignación
                                'horarios' => [
                                    'asignados' => $horariosDeHijos
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
                                                'rap' => $h->gradoMateria->materia->nombreMateria
                                            ];
                                        })->values(),
                                    'sinAsignar' => $horariosDeHijos
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
                                                'instructor' => null,
                                                'rap' => $h->gradoMateria->materia->nombreMateria
                                            ];
                                        })->values()
                                ]
                            ];
                        })->values()
                ];
            })->values();

        // Respuesta final
        return response()->json([
            'message' => 'Trimestres obtenidos correctamente',
            'data' => $resultado
        ], 200);
    }

    /**
     * Calcula las horas totales, actuales y faltantes de una materia
     * basándose en todos sus horarios asignados.
     * 
     * @param \Illuminate\Support\Collection $horarios - Colección de HorarioMateria
     * @return array [horasTotales, horasActuales, horasFaltantes, porcentajeAvance]
     */
    private function calcularHorasMateria($horarios)
    {
        $horasTotales = 0;
        $horasActuales = 0;
        $fechaHoy = now(); // Fecha actual del servidor

        foreach ($horarios as $horario) {
            // Calcular cuántas horas hay en cada sesión
            $horaInicial = \Carbon\Carbon::parse($horario->horaInicial);
            $horaFinal = \Carbon\Carbon::parse($horario->horaFinal);
            $horasPorSesion = $horaFinal->diffInHours($horaInicial, true); // true = con decimales

            // Obtener fechas del periodo
            $fechaInicial = \Carbon\Carbon::parse($horario->fechaInicial);
            $fechaFinal = \Carbon\Carbon::parse($horario->fechaFinal);

            // Verificar que el horario tenga un día asignado
            if (!$horario->dia || !isset($horario->dia->dia)) {
                continue; // Si no tiene día, lo saltamos
            }

            // Contar cuántos días de este tipo hay en el rango total
            $diasTotalesEnRango = $this->contarDiasEnRango(
                $fechaInicial,
                $fechaFinal,
                $horario->dia->dia
            );

            // Calcular horas totales de este horario
            $horasTotalesHorario = $diasTotalesEnRango * $horasPorSesion;
            $horasTotales += $horasTotalesHorario;

            // Calcular horas que ya transcurrieron hasta hoy
            if ($fechaHoy->greaterThanOrEqualTo($fechaInicial)) {
                // Si hoy es después o igual al inicio, calculamos días transcurridos

                // Usamos la fecha menor entre hoy y la fecha final
                $fechaLimite = $fechaHoy->lessThan($fechaFinal) ? $fechaHoy : $fechaFinal;

                $diasTranscurridos = $this->contarDiasEnRango(
                    $fechaInicial,
                    $fechaLimite,
                    $horario->dia->dia
                );

                $horasActuales += $diasTranscurridos * $horasPorSesion;
            }
            // Si hoy es antes del inicio, horasActuales queda en 0 para este horario
        }

        // Calcular horas faltantes
        $horasFaltantes = max(0, $horasTotales - $horasActuales);

        // Calcular porcentaje de avance
        $porcentajeAvance = $horasTotales > 0
            ? round(($horasActuales / $horasTotales) * 100, 2)
            : 0;

        return [
            'horasTotales' => round($horasTotales, 2),
            'horasActuales' => round($horasActuales, 2),
            'horasFaltantes' => round($horasFaltantes, 2),
            'porcentajeAvance' => $porcentajeAvance
        ];
    }

    /**
     * Cuenta cuántos días de la semana específicos (ej: LUNES) 
     * existen entre dos fechas.
     * 
     * @param \Carbon\Carbon $fechaInicio
     * @param \Carbon\Carbon $fechaFin
     * @param string $nombreDia - Nombre del día (LUNES, MARTES, etc.)
     * @return int - Cantidad de días encontrados
     */
    private function contarDiasEnRango($fechaInicio, $fechaFin, $nombreDia)
    {
        // Mapeo de nombres en español a números de Carbon
        // Carbon: 0 = Domingo, 1 = Lunes, 2 = Martes, ...
        $diasSemana = [
            'DOMINGO' => 0,
            'LUNES' => 1,
            'MARTES' => 2,
            'MIERCOLES' => 3,
            'MIÉRCOLES' => 3,
            'JUEVES' => 4,
            'VIERNES' => 5,
            'SABADO' => 6,
            'SÁBADO' => 6
        ];

        // Convertir el nombre del día a número
        $diaNumero = $diasSemana[strtoupper($nombreDia)] ?? null;

        if ($diaNumero === null) {
            return 0; // Día no válido
        }

        // Inicializar contador
        $contador = 0;

        // Clonar para no modificar las fechas originales
        $inicio = \Carbon\Carbon::parse($fechaInicio);
        $fin = \Carbon\Carbon::parse($fechaFin);

        // Iterar día por día
        while ($inicio->lessThanOrEqualTo($fin)) {
            // Si el día de la semana coincide, contar
            if ($inicio->dayOfWeek === $diaNumero) {
                $contador++;
            }
            $inicio->addDay(); // Avanzar al siguiente día
        }

        return $contador;
    }

    /*
        asigna nuevas competencias al trimestre
    */
    public function addCompetenciasTrimestre(Request $request)
    {
        try {
            $datos = $request->all();

            if (!$datos['idTrimestre']) {
                return response()->json([
                    'message' => 'No se ha proporcionado el trimestre'
                ], 400);
            }

            DB::beginTransaction();
            foreach ($datos['competencia'] as $competencia) {
                $newGradoMateria = GradoMateria::create([
                    'idGradoPrograma' => $datos['idTrimestre'],
                    'idMateria' => $competencia->id,
                    'estado' => 'PENDIENTE'
                ]);

                HorarioMateria::create([
                    'estado' => 'PENDIENTE',
                    'idGradoMateria' => $newGradoMateria->id,
                    'idFicha' => $datos['idFicha']
                ]);
            }
            DB::commit();
            return response()->json([
                'message' => 'Competencias asignadas correctamente'
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Ha ocurrido un error al asignar las competencias',
                'error' => $e->getMessage()
            ]);
        }
    }
}
