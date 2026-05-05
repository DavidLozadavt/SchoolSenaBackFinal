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
use App\Models\AsignacionSesion;
use App\Models\HorarioMateria;
use App\Models\Materia;
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
use App\Mail\MailService;
use App\Models\AgregarMateriaPrograma;
use App\Models\Ficha;
use App\Models\Dia;
use App\Models\Asistencia;
use App\Models\DetalleRmi;
use App\Models\GradoPrograma;
use App\Models\Rmi;
use App\Models\MatriculaAcademica;
use App\Models\NotificacionSistema;

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

    public function assignSharedInstructor(Request $request): JsonResponse
    {
        DB::beginTransaction();
        try {
            $idContrato = $request->idContrato;
            $horarioIds = $request->horarios; // Array de IDs de HorarioMateria

            if (empty($idContrato) || empty($horarioIds)) {
                return response()->json(['message' => 'Faltan datos obligatorios'], 400);
            }

            // Buscamos las asignaciones de sesión de tipo compartido que tengan idContrato null
            $asignaciones = AsignacionSesion::whereIn('idHorarioMateria', $horarioIds)
                ->where('tipoAsignacion', 'HORARIO COMPARTIDO')
                ->whereNull('idContrato')
                ->get();

            if ($asignaciones->isEmpty()) {
                return response()->json(['message' => 'No se encontraron horarios compartidos pendientes de asignación'], 404);
            }

            foreach ($asignaciones as $asignacion) {
                $asignacion->update(['idContrato' => $idContrato]);
                
                // Duplicar el horario para el segundo profe
                $clon = HorarioMateria::duplicarParaAsignacion($asignacion);
                if ($clon) {
                    $asignacion->update(['idHorarioMateria' => $clon->id]);
                }
            }

            DB::commit();

            return response()->json(['message' => 'Instructor secundario asignado correctamente'], 200);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al asignar instructor secundario',
                'error' => $e->getMessage()
            ], 500);
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
            $esCompartido   = $data['esCompartido'] ?? false;
            $horarios       = $data['horarios'] ?? [];
            $observacion  = $data['observacion'] ?? null;

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

                    if ($esCompartido) {
                        AsignacionSesion::create([
                            'tipoAsignacion'   => 'HORARIO COMPARTIDO',
                            'fechaInicio'      => $fechaInicio,
                            'fechaFin'         => $fechaFin,
                            'idContrato'       => null,
                            'idHorarioMateria' => $horarioBase->id,
                            'observacion'      => $observacion,
                        ]);
                    }

                    $results[] = $horarioBase;
                } else {
                    $newHorario = HorarioMateria::create($horarioData);
                    $this->generatePastSessions($newHorario);

                    if ($esCompartido) {
                        AsignacionSesion::create([
                            'tipoAsignacion'   => 'HORARIO COMPARTIDO',
                            'fechaInicio'      => $fechaInicio,
                            'fechaFin'         => $fechaFin,
                            'idContrato'       => null,
                            'idHorarioMateria' => $newHorario->id,
                            'observacion'      => $observacion,
                        ]);
                    }

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
                    $nuevaSesion = SesionMateria::create([
                        'numeroSesion' => $lastSession,
                        'idHorarioMateria' => $horarioMateria->id,
                        'fechaSesion' => $currentDate->toDateString(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    // Buscar todos los aprendices matriculados en esta Ficha y Materia
                    $matriculas = MatriculaAcademica::where('idFicha', $horarioMateria->idFicha)
                        ->whereHas('materia', function ($query) use ($horarioMateria) {
                            $query->whereHas('gradoMateria', function ($q) use ($horarioMateria) {
                                $q->where('id', $horarioMateria->idGradoMateria);
                            });
                        })
                        ->get();

                    if ($matriculas->isEmpty()) {
                        // Si la consulta anterior no trajo resultados, intentar buscar la materia directo
                        $matriculas = MatriculaAcademica::where('idFicha', $horarioMateria->idFicha)
                            ->where('idMateria', $horarioMateria->materia->idMateria ?? null)
                            ->get();
                    }

                    // Crear el registro de asistencia por defecto en "false" (NO ASISTIÓ)
                    $asistenciaData = [];
                    $now = now();
                    foreach ($matriculas as $matricula) {
                        $asistenciaData[] = [
                            'idSesionMateria' => $nuevaSesion->id,
                            'idMatriculaAcademica' => $matricula->id,
                            'asistio' => false,
                            'horaLLegada' => null,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }

                    if (!empty($asistenciaData)) {
                        Asistencia::insert($asistenciaData);
                    }
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
            ->where('estado', [EstadoHorarioMateria::PENDIENTE, EstadoHorarioMateria::ASIGNADO, EstadoHorarioMateria::INTERRUMPIDO])
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

            $idFicha = $horarioMateria->idAsignacionPeriodoJornada;
            $ficha   = Ficha::with('aperturarPrograma')->find($idFicha);
            $porcentajeEjecucion = $ficha->porcentajeEjecucion ?? 100;
            $idPrograma = $ficha->aperturarPrograma->idPrograma;

            $amp = AgregarMateriaPrograma::where('idMateria', $horarioMateria->materia->idMateria)
                ->where('idPrograma', $idPrograma)
                ->first();
            $horasRap = $amp ? $amp->horas : 0;

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

            // Buscar todos los horarios que correspondan al mismo slot (incluyendo compartidos/duplicados)
            $horariosRelacionados = HorarioMateria::where('idFicha', $horarioMateria->idFicha)
                ->where('idGradoMateria', $horarioMateria->idGradoMateria)
                ->where('idDia', $horarioMateria->idDia)
                ->where('horaInicial', $horarioMateria->horaInicial)
                ->where('horaFinal', $horarioMateria->horaFinal)
                ->where('fechaInicial', $horarioMateria->fechaInicial)
                ->get();

            // Verificar si alguno de los horarios en este slot tiene asistencias o RMIs con datos
            foreach ($horariosRelacionados as $hr) {
                // Verificar asistencia real (donde alguien asistió)
                $hasAsistencia = $hr->sesionMaterias()->whereHas('asistencia', fn($q) => $q->where('asistio', true))->exists();
                if ($hasAsistencia) {
                    return response()->json([
                        'message' => 'No es posible eliminar este horario porque tiene asistencias registradas en este bloque.'
                    ], 422);
                }

                // Verificar si el RMI tiene reportes activos o archivos subidos
                $hasActiveRmi = $hr->detallesRmi()->where(function($q) {
                    $q->where('estado', '!=', 'PENDIENTE')
                      ->orWhereNotNull('archivoPago')
                      ->orWhereNotNull('urlInforme')
                      ->orWhereNotNull('numeroPlanilla');
                })->exists();

                if ($hasActiveRmi) {
                    return response()->json([
                        'message' => 'No es posible eliminar este horario porque tiene reportes de RMI activos o archivos asociados.'
                    ], 422);
                }
            }

            foreach ($horariosRelacionados as $hr) {
                // 1. Eliminar vinculaciones de sesiones especiales (compartido/reemplazo)
                AsignacionSesion::where('idHorarioMateria', $hr->id)->delete();

                // 2. Eliminar sesiones y sus asistencias (solo si no tienen asistencias reales, ya validado)
                $hr->sesionMaterias()->each(function($sesion) {
                    $sesion->asistencia()->delete();
                    $sesion->delete();
                });

                // 3. Eliminar detalles RMI (solo si están pendientes, ya validado)
                $hr->detallesRmi()->delete();

                // 4. Decidir si borrar el registro o dejarlo como placeholder
                $totalRecordsForRap = HorarioMateria::where('idGradoMateria', $hr->idGradoMateria)->count();

                if ($totalRecordsForRap > 1) {
                    // Si hay otros horarios para este RAP (otros días u otros clones), borramos este registro físico
                    $hr->delete();
                } else {
                    // Si es el último registro del RAP, lo limpiamos para que quede como slot disponible (placeholder)
                    $hr->update([
                        'idDia'             => null,
                        'idInfraestructura' => null,
                        'idContrato'        => null,
                        'fechaFinal'        => null,
                        'horaInicial'       => null,
                        'horaFinal'         => null,
                        'observacion'       => null,
                        'estado'            => EstadoHorarioMateria::PENDIENTE
                    ]);
                }
            }

            DB::commit();

            return response()->json(null, 204);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Ocurrio un error al eliminar el horario',
                'error'   => $e->getMessage()
            ], 500);
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
                        'message' => "El docente ya tiene una clase asignada para el mismo horario.",
                        'conflicto' => $resultado->horarioMateria
                    ], 422);
                }

                if ($horarioMateria->estado != 'FINALIZADO' && $horarioMateria->estado != 'INTERRUMPIDO') {
                    $horarioMateria->update([
                        'idContrato' => $idContrato,
                        'estado' => EstadoHorarioMateria::ASIGNADO
                    ]);

                    // Generar RMI ahora que el horario ya tiene idContrato y fechas
                    $horarioMateria->refresh();
                    HorarioMateria::generarRmis($horarioMateria);
                }

                // Actualizar el estado del detalle RMI del periodo actual a PENDIENTE
                $periodoActual = now()->format('Y-m');
                $rmiActual = Rmi::where('periodo', $periodoActual)->first();
                $horarios = HorarioMateria::where('idContrato', $idContrato)->get();

                if ($rmiActual && $horarios) {
                    foreach ($horarios as $horario) {
                        DetalleRmi::where('idHorarioMateria', $horario->id)
                            ->where('idRmi', $rmiActual->id)
                            ->update([
                                'estado'      => 'PENDIENTE',
                                'observacion' => null
                            ]);
                    }
                }
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
     * Unassign teacher to schedule
     * @param Request $request
     * @return JsonResponse
     */
    public function unassignTeacherSchedule(Request $request): JsonResponse
    {
        $horarios = $request->input('horarios');

        if (!is_array($horarios)) {
            return response()->json([
                'message' => 'La lista de horarios es obligatoria'
            ], 400);
        }

        try {
            DB::beginTransaction();

            foreach ($horarios as $h) {
                $horarioMateria = HorarioMateria::findOrFail($h['id']);

                if ($horarioMateria->estado == EstadoHorarioMateria::ASIGNADO) {
                    $horarioMateria->update([
                        'idContrato' => null,
                        'estado' => EstadoHorarioMateria::PENDIENTE
                    ]);
                } else {
                    $horarioMateria->update([
                        'idContrato' => null
                    ]);
                }
                $detallesRmi = DetalleRmi::where('idHorarioMateria', $horarioMateria->id)->get();
                foreach ($detallesRmi as $detalleRmi) {
                    $detalleRmi->update([
                        'estado' => 'PENDIENTE',
                        'estadoAsociado' => 0
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'message' => 'Instructor desasignado correctamente de todos los horarios'
            ], 200);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al desasignar el instructor',
                'error' => $e->getMessage()
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
            'gradoMateria.materia',
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
                $query->whereDate('fechaInicial', '<=', $horarioMateria->fechaFinal)
                    ->whereDate('fechaFinal', '>=', $horarioMateria->fechaInicial);
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
                $idFicha = $horario->idAsignacionPeriodoJornada;
                $ficha   = Ficha::with('aperturarPrograma')->find($idFicha);
                $idPrograma = $ficha->aperturarPrograma->idPrograma;

                $amp = AgregarMateriaPrograma::where('idMateria', $horario->materia->idMateria)
                    ->where('idPrograma', $idPrograma)
                    ->first();
                $horasRap       = $amp ? $amp->horas : 0;
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
        $gradoMateria    = GradoMateria::findOrFail($idGradoMateria);
        $idGradoPrograma = $gradoMateria->idGradoPrograma;
        $idPrograma      = GradoPrograma::findOrFail($idGradoPrograma)->idPrograma;

        $amp = AgregarMateriaPrograma::where('idMateria', $gradoMateria->idMateria)
            ->where('idPrograma', $idPrograma)
            ->first();
        $horasRap = $amp ? $amp->horas : 0;

        $idDia                      = $data['idDia'];
        $idContrato                 = $data['idContrato']   ?? null;
        $fechaInicialScheduleCreate = $data['fechaInicial'] ?? null;
        $horaInicial                = $data['horaInicial']  ?? null;
        $horaFinal                  = $data['horaFinal']    ?? null;


        $fechaInicial = $horarioMateria['fechaInicial'] ?? $horarioMateria->fechaInicial;
        $fechaFinal   = $horarioMateria['fechaFinal']   ?? $horarioMateria->fechaFinal;

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
                ->with('contrato.persona')
                ->with('asignacionSesion.contrato.persona')
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

        // Obtener los datos de matricula académica para validar asignación y estados
        $matriculasFicha = MatriculaAcademica::where('idFicha', $idFicha)
            ->select('idMateria', 'estado')
            ->get()
            ->groupBy('idMateria');

        // Traemos los horarios con sus relaciones
        $horarios = HorarioMateria::where('idFicha', $idFicha)
            ->whereHas('gradoMateria.gradoPrograma', function ($q) use ($idPrograma) {
                $q->where('idPrograma', $idPrograma);
            })
            ->with([
                'gradoMateria.materia.agregarMateriaPrograma' => function ($q) use ($idPrograma) {
                    $q->where('idPrograma', $idPrograma);
                },
                'gradoMateria.gradoPrograma.grado',
                'gradoMateria.gradoPrograma',
                'dia',
                'contrato.persona:id,nombre1,nombre2,apellido1,apellido2,rutaFoto,email',
                'asignacionSesion.contrato.persona'
            ])
            ->withCount(['sesionMaterias as sesiones_realizadas_count' => function ($q) {
                $q->whereNotNull('fechaSesion');
            }])
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
            ->map(function ($horariosPorGrado) use ($horarios, $matriculasFicha, $idPrograma) {
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
                        ->map(function ($horariosPorMateriaPadre, $idMateriaPadre) use ($horariosPorGrado, $horarios, $matriculasFicha, $idPrograma) {
                            $materiaPadre = $horariosPorMateriaPadre->first()
                                ->gradoMateria
                                ->materia;

                            // Actualizar las horas del padre basándose en la suma de sus hijos (RAPs) para este programa
                            $hijosQuery = Materia::where('materia.idMateriaPadre', $materiaPadre->id)
                                ->join('agregarMateriaPrograma', 'agregarMateriaPrograma.idMateria', '=', 'materia.id')
                                ->where('agregarMateriaPrograma.idPrograma', $idPrograma);

                            if ($hijosQuery->exists()) {
                                $horasSum = $hijosQuery->sum('agregarMateriaPrograma.horas');

                                $ampPadre = AgregarMateriaPrograma::updateOrCreate(
                                    ['idMateria' => $materiaPadre->id, 'idPrograma' => $idPrograma],
                                    ['horas' => $horasSum]
                                );

                                $materiaPadre->setRelation('agregarMateriaPrograma', collect([$ampPadre])); // Actualizar relación cargada
                            }

                            $horasPadre = $materiaPadre->agregarMateriaPrograma->first()->horas ?? 0;

                            // Horarios de hijos en TODA la ficha y solo en este trimestre
                            $horariosRapsFicha = $horarios->filter(fn($h) => $h->gradoMateria->materia->idMateriaPadre == $idMateriaPadre);
                            $horariosDeHijos = $horariosPorGrado->filter(fn($h) => $h->gradoMateria->materia->idMateriaPadre == $idMateriaPadre);

                            // Horas acumuladas (globales de la ficha)
                            $horasData = $this->calcularHorasMateria($horarios->filter(fn($h) => $h->gradoMateria->idMateria == $idMateriaPadre || $h->gradoMateria->materia->idMateriaPadre == $idMateriaPadre));

                            // Estado global de RAPs en la ficha basado en MatriculaAcademica
                            // Si al menos 5 aprendices están EVALUADOS, FINALIZADOS o APROBADOS en MatriculaAcademica, se marca como terminado
                            $estadoRapsGlobal = $horariosRapsFicha->groupBy('gradoMateria.idMateria')->map(function ($g, $idMateria) use ($matriculasFicha) {
                                $matriculas = $matriculasFicha->get($idMateria, collect());
                                return $matriculas->filter(fn($m) => in_array(strtoupper($m->estado), ['FINALIZADO', 'EVALUADO', 'APROBADO']))->count() >= 5;
                            });

                            // Sincronizar estados de RAPs en este trimestre si ya están finalizados en matricula
                            foreach ($horariosDeHijos as $_h) {
                                if (($estadoRapsGlobal[$_h->gradoMateria->idMateria] ?? false) && !in_array($_h->gradoMateria->estado, [EstadoHorarioMateria::FINALIZADO, EstadoHorarioMateria::EVALUADO])) {
                                    $_h->gradoMateria->update(['estado' => EstadoHorarioMateria::FINALIZADO]);
                                }
                            }

                            // Verificar y actualizar estado de la competencia (Padre)
                            $gradoMateriaParaEstado = $horariosPorMateriaPadre->first()->gradoMateria;
                            $todosRapsTerminados = $estadoRapsGlobal->isNotEmpty() && $estadoRapsGlobal->every(fn($f) => $f);
                            if ($todosRapsTerminados || ($horasData['horasActuales'] >= $horasPadre && $horasData['horasActuales'] > 0)) {
                                if ($gradoMateriaParaEstado->estado != EstadoHorarioMateria::FINALIZADO) {
                                    $gradoMateriaParaEstado->update(['estado' => EstadoHorarioMateria::FINALIZADO]);
                                }
                            }

                            return [
                                'id' => $materiaPadre->id,
                                'nombre' => $materiaPadre->nombreMateria,
                                'descripcion' => $materiaPadre->descripcion,
                                'estado' => $gradoMateriaParaEstado->estado,
                                'idMateriaPadre' => $materiaPadre->idMateriaPadre,
                                'idGradoMateria' => $gradoMateriaParaEstado->id,
                                'horasTotales' => $horasPadre,
                                'horasActuales' => $horasData['horasActuales'],
                                'horasFaltantes' => max(0, $horasPadre - $horasData['horasActuales']),
                                'porcentajeAvance' => $horasData['porcentajeAvance'],

                                // Horarios de los hijos agrupados por asignación
                                'horarios' => [
                                    'asignados' => $horariosDeHijos
                                        ->filter(fn($h) => $h->idDia != null && $h->horaInicial != null && $h->horaFinal != null && $h->fechaInicial != null && $h->idContrato != null)
                                        ->map(function ($h) use ($estadoRapsGlobal) {
                                            $isFinished = $estadoRapsGlobal[$h->gradoMateria->idMateria] ?? false;
                                            return [
                                                'id' => $h->id,
                                                'dia' => $h->dia,
                                                'horaInicial' => $h->horaInicial,
                                                'horaFinal' => $h->horaFinal,
                                                'fechaInicial' => $h->fechaInicial,
                                                'fechaFinal' => $h->fechaFinal,
                                                'estado' => $isFinished ? EstadoHorarioMateria::FINALIZADO : $h->estado,
                                                'instructor' => $h->contrato->persona ?? null,
                                                'asignacionSesion' => $h->asignacionSesion ?? [],
                                                'rap' => $h->gradoMateria->materia->nombreMateria
                                            ];
                                        })->values(),
                                    'sinAsignar' => $horariosDeHijos
                                        ->filter(fn($h) => $h->idDia != null && $h->horaInicial != null && $h->horaFinal != null && $h->fechaInicial != null && $h->idContrato == null && $h->estado == EstadoHorarioMateria::PENDIENTE)
                                        ->map(function ($h) use ($estadoRapsGlobal) {
                                            $isFinished = $estadoRapsGlobal[$h->gradoMateria->idMateria] ?? false;
                                            return [
                                                'id' => $h->id,
                                                'dia' => $h->dia,
                                                'horaInicial' => $h->horaInicial,
                                                'horaFinal' => $h->horaFinal,
                                                'fechaInicial' => $h->fechaInicial,
                                                'fechaFinal' => $h->fechaFinal,
                                                'estado' => $isFinished ? EstadoHorarioMateria::FINALIZADO : $h->estado,
                                                'instructor' => null,
                                                'asignacionSesion' => $h->asignacionSesion ?? [],
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

        foreach ($horarios as $horario) {
            // Calcular cuánto dura cada sesión en horas (con decimales)
            $horaInicial = \Carbon\Carbon::parse($horario->horaInicial);
            $horaFinal = \Carbon\Carbon::parse($horario->horaFinal);
            $horasPorSesion = $horaFinal->diffInMinutes($horaInicial, true) / 60;

            // Horas Totales: Calculadas por el rango de fechas (Teórico)
            $fechaInicial = \Carbon\Carbon::parse($horario->fechaInicial);
            $fechaFinal = \Carbon\Carbon::parse($horario->fechaFinal);

            if ($horario->dia && isset($horario->dia->dia)) {
                $diasTotalesEnRango = $this->contarDiasEnRango(
                    $fechaInicial,
                    $fechaFinal,
                    $horario->dia->dia
                );
                $horasTotales += $diasTotalesEnRango * $horasPorSesion;
            }

            // Horas Actuales: Basadas en las sesiones que YA se han dado (fechaSesion no null)
            // Usamos el contador cargado con withCount para evitar N+1
            $sesionesDadas = $horario->sesiones_realizadas_count ?? 0;
            $horasActuales += $sesionesDadas * $horasPorSesion;
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

    public function finalizarRap(Request $request)
    {
        try {
            $idGradoMateria = $request->input('idGradoMateria');
            DB::beginTransaction();
            $horarios = HorarioMateria::where('idGradoMateria', $idGradoMateria)
                ->whereNotIn('estado', [EstadoHorarioMateria::EVALUADO, EstadoHorarioMateria::FINALIZADO])
                ->get();

            foreach ($horarios as $horario) {
                $horario->estado = EstadoHorarioMateria::FINALIZADO;
                $horario->save();

                $gradoMateria = GradoMateria::find($horario->idGradoMateria);
                $gradoMateria->estado = EstadoHorarioMateria::FINALIZADO;
                $gradoMateria->save();

                // Fuente de verdad: Actualizar MatriculaAcademica para todos los estudiantes en esta ficha y materia
                MatriculaAcademica::where('idFicha', $horario->idFicha)
                    ->where('idMateria', $gradoMateria->idMateria)
                    ->update(['estado' => 'FINALIZADO']);
            }

            // INFORMACION PARA ENVIAR EN EL EMAIL Y LA NOTIFICACION

            // RAP, Competencia y Trimestre
            $gradoMateria = GradoMateria::with(['materia.padre', 'gradoPrograma.grado'])
                ->find($idGradoMateria);

            $nombreRap = $gradoMateria->materia->nombreMateria ?? 'Materia/RAP';
            $nombreCompetencia = $gradoMateria->materia->padre->nombreMateria ?? 'Competencia';
            $numeroTrimestre = $gradoMateria->gradoPrograma->grado->numeroGrado ?? 'N/A';

            // Ficha e Instructor
            $horarioConDocente = HorarioMateria::with(['contrato.persona.usuario', 'ficha'])
                ->where('idGradoMateria', $idGradoMateria)
                ->whereNotNull('idContrato')
                ->first();
            $ficha = $horarioConDocente->ficha;

            if ($horarioConDocente && $horarioConDocente->contrato && $horarioConDocente->contrato->persona) {
                $instructor = $horarioConDocente->contrato->persona;
                $ficha = $ficha->codigo;
                $correo = $instructor->email;
                $nombreDocente = $instructor->nombre1 . ' ' . $instructor->apellido1;
                $asunto = "Ha finalizado el RAP: " . $nombreRap;
                $mensaje = "Hola $nombreDocente,\n\n"
                    . "Te informamos que el RAP $nombreRap \n perteneciente a la competencia $nombreCompetencia "
                    . "ha finalizado. \n\n"
                    . "Ficha: $ficha \n"
                    . "Trimestre: $numeroTrimestre \n"
                    . "Por favor evalúa en Sofía Plus y carga los juicios evaluativos.\n\n"
                    . "Gracias por tu labor.";

                \App\Jobs\SendBasicEmail::dispatch($correo, $asunto, $mensaje);

                if ($instructor && $instructor->usuario) {
                    NotificacionSistema::create([
                        'fecha' => now()->toDateString(),
                        'hora' => now()->toTimeString(),
                        'asunto' => 'RAP FINALIZADO',
                        'mensaje' => "El RAP $nombreRap de la competencia $nombreCompetencia ha sido finalizado para la ficha $ficha.",
                        'estado_id' => 1, // Pendiente/No leído
                        'idUsuarioReceptor' => $instructor->usuario->id,
                        'idUsuarioRemitente' => KeyUtil::user()->id,
                        'idTipoNotificacion' => 1,
                        'idEmpresa' => KeyUtil::idCompany(),
                        'route' => '/raps'
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'message' => 'Rap finalizado correctamente'
            ], 200);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'message' => 'Ha ocurrido un error al finalizar el rap',
                'error' => $th->getMessage()
            ]);
        }
    }

    public function interrumpirRap(Request $request)
    {
        try {
            $idGradoMateria = $request->input('idGradoMateria');
            DB::beginTransaction();
            $horarios = HorarioMateria::where('idGradoMateria', $idGradoMateria)
                ->whereNotIn('estado', [EstadoHorarioMateria::EVALUADO, EstadoHorarioMateria::FINALIZADO])
                ->get();

            foreach ($horarios as $horario) {
                $horario->estado = EstadoHorarioMateria::INTERRUMPIDO;
                $horario->save();
            }
            DB::commit();
            return response()->json([
                'message' => 'Rap interrumpido correctamente'
            ], 200);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'message' => 'Ha ocurrido un error al interrumpir el rap',
                'error' => $th->getMessage()
            ]);
        }
    }

    public function getRapsEvaluarContrato(Request $request)
    {
        try {
            $contratos = $request->input('contratos');

            if (empty($contratos) || !is_array($contratos)) {
                return response()->json([], 400);
            }

            $contratoIds = collect($contratos)->pluck('id')->filter()->toArray();

            // Obtener las fichas asociadas a esos contratos (via horarioMateria)
            $fichaIds = HorarioMateria::whereIn('idContrato', $contratoIds)
                ->whereNotNull('idFicha')
                ->pluck('idFicha')
                ->unique()
                ->toArray();

            // Obtener los idMateria que el contrato imparte (via gradoMateria)
            $materiaIds = HorarioMateria::whereIn('idContrato', $contratoIds)
                ->whereNotNull('idGradoMateria')
                ->with('gradoMateria')
                ->get()
                ->pluck('gradoMateria.idMateria')
                ->filter()
                ->unique()
                ->toArray();

            if (empty($fichaIds) || empty($materiaIds)) {
                return response()->json([], 200);
            }

            // RAPs cuyo estado en MatriculaAcademica sea FINALIZADO, EVALUADO o APROBADO (con al menos 5 aprendices)
            // para las fichas y materias que maneja el contrato
            $materiasFinalizadasIds = MatriculaAcademica::whereIn('idFicha', $fichaIds)
                ->whereIn('idMateria', $materiaIds)
                ->whereIn('estado', ['FINALIZADO', 'EVALUADO', 'APROBADO'])
                ->select('idMateria')
                ->groupBy('idMateria')
                ->havingRaw('COUNT(*) >= 5')
                ->pluck('idMateria')
                ->toArray();

            $materias = Materia::whereIn('id', $materiasFinalizadasIds)->get();

            return response()->json($materias, 200);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Ha ocurrido un error al obtener las materias',
                'error' => $th->getMessage()
            ], 500);
        }
    }
}
