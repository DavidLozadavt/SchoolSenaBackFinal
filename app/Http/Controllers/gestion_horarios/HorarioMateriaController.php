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
use Illuminate\Support\Facades\Schema;
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
use App\Models\HistorialHorarioMateria;
use App\Services\TrimestreActualFichaService;

class HorarioMateriaController extends Controller
{

    private array $relations;
    private array $columns;

    /**
     * Estados de horarioMateria que sí ocupan franja / disponibilidad activa.
     * INTERRUMPIDO, FINALIZADO y EVALUADO NO deben bloquear al instructor ni la franja.
     */
    private const ESTADOS_HORARIO_ACTIVOS = [
        EstadoHorarioMateria::PENDIENTE,
        EstadoHorarioMateria::ASIGNADO,
    ];

    /** Estados que hacen que el instructor quede ocupado en cruce de docente. */
    private const ESTADOS_QUE_OCUPAN_INSTRUCTOR = [
        EstadoHorarioMateria::ASIGNADO,
    ];

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

            $bloqueo = TrimestreActualFichaService::abortSiAlgunHorarioNoActual(
                array_map(fn ($id) => ['id' => (int) $id], (array) $horarioIds)
            );
            if ($bloqueo) {
                return $bloqueo;
            }

            $compartidos = AsignacionSesion::whereIn('idHorarioMateria', $horarioIds)
                ->where('tipoAsignacion', 'HORARIO COMPARTIDO')
                ->whereNull('idContrato')
                ->get();

            if ($compartidos->isEmpty()) {
                return response()->json(['message' => 'No se encontraron horarios compartidos pendientes de asignación'], 404);
            }

            foreach ($compartidos as $compartido) {
                $compartido->update(['idContrato' => $idContrato]);
                HorarioMateria::duplicarParaAsignacionCompartida($compartido);
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
            $festivos  = $data['festivos'] ?? false;

            $bloqueo = TrimestreActualFichaService::abortSiGradoMateriaNoActual(
                (int) $idGradoMateria,
                (int) $idFicha
            );
            if ($bloqueo) {
                return $bloqueo;
            }

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
                    'festivos'                    => $festivos,
                ];

                // Determinar ID a excluir si estamos actualizando el horario base
                $excludeId = ($index === 0 && $horarioBase) ? $horarioBase->id : null;

                // Si la fecha de inicio quedó libre (hueco de una interrupción) pero la
                // proyección de horas cubre fechas que siguen ocupadas, recortar el rango
                // a las ocurrencias consecutivas realmente disponibles.
                $fechaFinLibre = $this->limitarFechaFinalAOcurrenciasLibres($horarioData, $excludeId);
                if ($fechaFinLibre === null) {
                    $dia = Dia::find($horarioData['idDia']);
                    DB::rollBack();
                    return response()->json([
                        'message' => 'El horario del día ' . ($dia->dia ?? '') .
                            ' de ' . $horarioData['horaInicial'] . ' a ' . $horarioData['horaFinal'] .
                            ' se cruza con otra materia en el mismo rango de fechas.'
                    ], 422);
                }
                $horarioData['fechaFinal'] = $fechaFinLibre;

                // VALIDACIÓN DE CRUCE (por fechas de clase reales, no solo el rango)
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
                            'idHorarioMateria' => $horarioBase->id,
                            'tipoAsignacion'   => 'HORARIO COMPARTIDO',
                            'fechaInicio'      => $horarioData['fechaInicial'],
                            'fechaFin'         => $horarioData['fechaFinal'],
                            'observacion'      => $observacion,
                            'idContrato'       => null,
                        ]);
                    }

                    $results[] = $horarioBase;
                } else {
                    $newHorario = HorarioMateria::create($horarioData);
                    $this->generatePastSessions($newHorario);

                    if ($esCompartido) {
                        AsignacionSesion::create([
                            'idHorarioMateria' => $newHorario->id,
                            'tipoAsignacion'   => 'HORARIO COMPARTIDO',
                            'fechaInicio'      => $horarioData['fechaInicial'],
                            'fechaFin'         => $horarioData['fechaFinal'],
                            'observacion'      => $observacion,
                            'idContrato'       => null,
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
     * Cruce real: misma ficha, mismo día, horas superpuestas y al menos una
     * fecha de clase en común. Un hueco (fecha interrumpida) no bloquea.
     */
    private function verCruce(array $data, $currentHorarioMateriaId = null): bool
    {
        $existentes = $this->horariosCandidatosCruce($data, $currentHorarioMateriaId);
        if ($existentes->isEmpty()) {
            return false;
        }

        $tz = config('app.timezone');
        $nuevas = $this->ocurrenciasEnRango(
            $data['fechaInicial'] ?? null,
            $data['fechaFinal'] ?? null,
            $data['idDia'] ?? null,
            $tz
        );
        if (empty($nuevas)) {
            return false;
        }

        $nuevasSet = array_flip($nuevas);
        foreach ($existentes as $existente) {
            foreach ($this->ocurrenciasDeHorario($existente, $tz) as $fecha) {
                if (isset($nuevasSet[$fecha])) {
                    return true;
                }
            }
        }

        return false;
    }

    private function horariosCandidatosCruce(array $data, $currentHorarioMateriaId = null)
    {
        $query = HorarioMateria::where('idFicha', $data['idFicha'])
            ->where('idDia', $data['idDia'])
            ->whereIn('estado', self::ESTADOS_HORARIO_ACTIVOS)
            ->when($currentHorarioMateriaId, function ($q) use ($currentHorarioMateriaId) {
                return $q->where('id', '<>', $currentHorarioMateriaId);
            });

        $query->where(function ($q) use ($data) {
            $q->whereDate('fechaInicial', '<=', $data['fechaFinal'])
                ->whereDate('fechaFinal', '>=', $data['fechaInicial']);
        });

        $query->where(function ($q) use ($data) {
            $q->where(function ($sub) use ($data) {
                $sub->whereTime('horaInicial', '<', $data['horaFinal'])
                    ->whereTime('horaFinal', '>', $data['horaInicial']);
            });
        });

        $query->where('festivos', $data['festivos'] ?? false);

        return $query->get();
    }

    /**
     * Recorta fechaFinal a las ocurrencias consecutivas desde el inicio que no
     * estén ocupadas. Null si la primera fecha ya está ocupada.
     */
    private function limitarFechaFinalAOcurrenciasLibres(array $data, $excludeId = null): ?string
    {
        $tz = config('app.timezone');
        $nuevas = $this->ocurrenciasEnRango(
            $data['fechaInicial'] ?? null,
            $data['fechaFinal'] ?? null,
            $data['idDia'] ?? null,
            $tz
        );
        if (empty($nuevas)) {
            return $data['fechaFinal'] ?? $data['fechaInicial'] ?? null;
        }

        $ocupadas = [];
        foreach ($this->horariosCandidatosCruce($data, $excludeId) as $existente) {
            foreach ($this->ocurrenciasDeHorario($existente, $tz) as $fecha) {
                $ocupadas[$fecha] = true;
            }
        }

        if (empty($ocupadas)) {
            return $data['fechaFinal'] ?? $nuevas[count($nuevas) - 1];
        }

        $libres = [];
        foreach ($nuevas as $fecha) {
            if (isset($ocupadas[$fecha])) {
                break;
            }
            $libres[] = $fecha;
        }

        if (empty($libres)) {
            return null;
        }

        return $libres[count($libres) - 1];
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
            $horarioMateria = HorarioMateria::with('gradoMateria.gradoPrograma.grado')->findOrFail($id);

            $idFicha = (int) $horarioMateria->idFicha;
            $numeroHorario = (int) ($horarioMateria->gradoMateria?->gradoPrograma?->grado?->numeroGrado ?? 0);
            $maxNumero = TrimestreActualFichaService::maxNumeroGradoFicha($idFicha);

            if ($maxNumero <= 0 || $numeroHorario <= 0 || $numeroHorario !== $maxNumero) {
                DB::rollBack();
                return TrimestreActualFichaService::respuestaBloqueo();
            }

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
        $horariosRequest = $request->input('horarios');

        if (!$idContrato || !is_array($horariosRequest)) {
            return response()->json([
                'message' => 'El contrato y la lista de horarios son obligatorios'
            ], 400);
        }

        $bloqueo = TrimestreActualFichaService::abortSiAlgunHorarioNoActual($horariosRequest);
        if ($bloqueo) {
            return $bloqueo;
        }

        try {
            DB::beginTransaction();

            foreach ($horariosRequest as $h) {
                $idHorario = is_array($h) ? ($h['id'] ?? null) : ($h->id ?? null);
                if (!$idHorario) {
                    continue;
                }

                $horarioMateria = HorarioMateria::findOrFail($idHorario);
                $estadoActual = strtoupper(trim((string) $horarioMateria->estado));

                // INTERRUMPIDO: no se asigna por este flujo.
                if ($estadoActual === EstadoHorarioMateria::INTERRUMPIDO) {
                    continue;
                }

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

                // FINALIZADO / EVALUADO: solo idContrato (trazabilidad); no cambiar estado.
                if (in_array($estadoActual, [EstadoHorarioMateria::FINALIZADO, EstadoHorarioMateria::EVALUADO], true)) {
                    $horarioMateria->update([
                        'idContrato' => $idContrato,
                    ]);
                } else {
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
                $horariosDelContrato = HorarioMateria::where('idContrato', $idContrato)->get();

                if ($rmiActual && $horariosDelContrato->isNotEmpty()) {
                    foreach ($horariosDelContrato as $horario) {
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

        $bloqueo = TrimestreActualFichaService::abortSiAlgunHorarioNoActual($horarios);
        if ($bloqueo) {
            return $bloqueo;
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
            // Solo ASIGNADO bloquea al instructor; INTERRUMPIDO / FINALIZADO / EVALUADO liberan
            ->whereIn('estado', self::ESTADOS_QUE_OCUPAN_INSTRUCTOR)
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
                ->whereIn('estado', self::ESTADOS_HORARIO_ACTIVOS)
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
            ->whereIn('estado', self::ESTADOS_HORARIO_ACTIVOS)
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
                ->with([
                    'dia',
                    'gradoMateria.materia',
                    'gradoMateria.gradoPrograma.grado',
                    'contrato.persona',
                    'asignacionSesion.contrato.persona',
                ])
                // Sin filtro por fecha: histórico + actual + futuro de la ficha.
                ->orderBy('fechaInicial')
                ->orderBy('horaInicial')
                ->get();

            // Normalizar fechas/horas para el calendario (strings Y-m-d / H:i).
            $maxNumeroTrimestre = TrimestreActualFichaService::maxNumeroGradoFicha((int) $idFicha);

            $data = $materias->map(function (HorarioMateria $h) use ($maxNumeroTrimestre) {
                $arr = $h->toArray();
                try {
                    if (!empty($h->fechaInicial)) {
                        $arr['fechaInicial'] = Carbon::parse($h->fechaInicial)->format('Y-m-d');
                    }
                    if (!empty($h->fechaFinal)) {
                        $arr['fechaFinal'] = Carbon::parse($h->fechaFinal)->format('Y-m-d');
                    }
                } catch (\Throwable $e) {
                    // conservar valor original si no es parseable
                }
                if (!empty($h->horaInicial)) {
                    $arr['horaInicial'] = substr((string) $h->horaInicial, 0, 5);
                }
                if (!empty($h->horaFinal)) {
                    $arr['horaFinal'] = substr((string) $h->horaFinal, 0, 5);
                }
                // Alias de instructor para el frontend del calendario.
                $arr['instructor'] = $h->contrato?->persona;

                $numeroTrimestre = (int) ($h->gradoMateria?->gradoPrograma?->grado?->numeroGrado ?? 0);
                $arr['numeroTrimestre'] = $numeroTrimestre > 0 ? $numeroTrimestre : null;
                $arr['esTrimestreActual'] = $numeroTrimestre > 0
                    && $maxNumeroTrimestre > 0
                    && $numeroTrimestre === $maxNumeroTrimestre;
                $arr['idHorarioMateria'] = (int) $h->id;

                return $arr;
            })->values();

            return response()->json([
                'message' => 'Consulta realizada correctamente',
                'data' => $data,
                'maxNumeroTrimestre' => $maxNumeroTrimestre,
                'trimestreActual' => $maxNumeroTrimestre > 0 ? $maxNumeroTrimestre : null,
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
                'asignacionSesion.contrato.persona',
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
                        'numeroGrado' => (int) $gradoPrograma->grado->numeroGrado,
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

                            // Verificar y actualizar estado de la competencia (Padre):
                            // Únicamente cuando TODOS sus RAPs de la ficha están aprobados/finalizados.
                            // No usar horas acumuladas como atajo: la competencia agrupa RAPs, no actividades.
                            $gradoMateriaParaEstado = $horariosPorMateriaPadre->first()->gradoMateria;
                            $todosRapsTerminados = $estadoRapsGlobal->isNotEmpty() && $estadoRapsGlobal->every(fn($f) => $f);
                            if ($todosRapsTerminados) {
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
                                                'asignacionSesion' => HorarioMateria::asignacionesEspecialesApi($h),
                                                'rap' => $h->gradoMateria->materia->nombreMateria
                                            ];
                                        })->values(),
                                    'sinAsignar' => $horariosDeHijos
                                        ->filter(fn($h) => $h->idDia != null && $h->horaInicial != null && $h->horaFinal != null && $h->fechaInicial != null && $h->idContrato == null && $h->estado != EstadoHorarioMateria::INTERRUMPIDO)
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
                                                'asignacionSesion' => HorarioMateria::asignacionesEspecialesApi($h),
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
                if (! $horario->horaInicial || ! $horario->horaFinal || ! $horario->fechaInicial) {
                    continue;
                }

                $horaInicial = \Carbon\Carbon::parse($horario->horaInicial);
                $horaFinal = \Carbon\Carbon::parse($horario->horaFinal);
                $horasPorSesion = $horaFinal->diffInMinutes($horaInicial, true) / 60;

                // Horas programadas según ocurrencias reales del rango (un hueco ya no suma).
                $tz = config('app.timezone');
                $diasTotalesEnRango = count($this->ocurrenciasDeHorario($horario, $tz));
                $horasTotales += $diasTotalesEnRango * $horasPorSesion;

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
            $validated = $request->validate([
                'idGradoMateria' => 'required',
                'idFicha' => 'nullable',
                'fechaFinal' => 'required|date',
                'observacion' => 'nullable|string|max:2000',
            ]);

            $idGradoMateria = $validated['idGradoMateria'];
            $idFicha = $validated['idFicha'] ?? $request->input('idFicha');
            $bloqueo = TrimestreActualFichaService::abortSiGradoMateriaNoActual(
                (int) $idGradoMateria,
                $idFicha ? (int) $idFicha : null
            );
            if ($bloqueo) {
                return $bloqueo;
            }

            $tz = config('app.timezone');
            $nuevaFecha = Carbon::parse((string) $validated['fechaFinal'], $tz)->startOfDay();
            $fechaFinalNueva = $nuevaFecha->toDateString();
            $observacion = trim((string) ($validated['observacion'] ?? ''));

            $horarios = HorarioMateria::where('idGradoMateria', $idGradoMateria)
                ->whereNotIn('estado', [EstadoHorarioMateria::EVALUADO, EstadoHorarioMateria::FINALIZADO])
                ->get();

            if ($horarios->isEmpty()) {
                return response()->json([
                    'message' => 'No hay horarios pendientes de finalizar para este RAP.',
                ], 422);
            }

            $fechaFinalActualRap = null;
            $fechaInicialMinima = null;
            foreach ($horarios as $horario) {
                if ($horario->fechaFinal) {
                    $fF = Carbon::parse((string) $horario->fechaFinal, $tz)->startOfDay();
                    if ($fechaFinalActualRap === null || $fF->gt($fechaFinalActualRap)) {
                        $fechaFinalActualRap = $fF;
                    }
                }
                if ($horario->fechaInicial) {
                    $fI = Carbon::parse((string) $horario->fechaInicial, $tz)->startOfDay();
                    if ($fechaInicialMinima === null || $fI->lt($fechaInicialMinima)) {
                        $fechaInicialMinima = $fI;
                    }
                }
            }

            if ($fechaInicialMinima && $nuevaFecha->lt($fechaInicialMinima)) {
                return response()->json([
                    'message' => 'La fecha no puede ser anterior a la fecha inicial del RAP.',
                ], 422);
            }

            if ($fechaFinalActualRap && $nuevaFecha->gt($fechaFinalActualRap)) {
                return response()->json([
                    'message' => 'La fecha no puede ser mayor que la fecha final actual del RAP.',
                ], 422);
            }

            foreach ($horarios as $horario) {
                $bloqueoSesiones = $this->validarSesionesSinDatosPosteriores((int) $horario->id, $nuevaFecha);
                if ($bloqueoSesiones !== null) {
                    return $bloqueoSesiones;
                }
            }

            $idUsuario = auth()->id() ?? KeyUtil::user()?->id;

            DB::beginTransaction();

            foreach ($horarios as $horario) {
                $fechaFinalAnterior = $horario->fechaFinal
                    ? Carbon::parse((string) $horario->fechaFinal, $tz)->toDateString()
                    : null;

                // Conservar el estado histórico del horario (PENDIENTE, ASIGNADO, INTERRUMPIDO, etc.).
                // Finalizar el RAP no debe sobrescribir estados de clases/horarios anteriores.
                $horario->fechaFinal = $fechaFinalNueva;
                if ($observacion !== '') {
                    $prev = trim((string) ($horario->observacion ?? ''));
                    $horario->observacion = trim($prev . "\n" . '[FINALIZACIÓN] ' . $observacion);
                }
                $horario->save();

                if ($idUsuario && Schema::hasTable('historialHorarioMateria')) {
                    HistorialHorarioMateria::create([
                        'idHorarioMateria' => $horario->id,
                        'tipoAccion' => EstadoHorarioMateria::FINALIZADO,
                        'fechaFinalAnterior' => $fechaFinalAnterior,
                        'fechaFinalNueva' => $fechaFinalNueva,
                        'idUsuario' => (int) $idUsuario,
                        'observacion' => $observacion !== '' ? $observacion : null,
                    ]);
                }

                SesionMateria::where('idHorarioMateria', $horario->id)
                    ->whereDate('fechaSesion', '>', $fechaFinalNueva)
                    ->whereDoesntHave('asistencia')
                    ->when(Schema::hasTable('calificacionSesiones'), function ($q) {
                        $q->whereDoesntHave('calificacionSesiones');
                    })
                    ->delete();

                $gradoMateriaHorario = GradoMateria::find($horario->idGradoMateria);
                if ($gradoMateriaHorario) {
                    $gradoMateriaHorario->estado = EstadoHorarioMateria::FINALIZADO;
                    $gradoMateriaHorario->save();

                    // matriculaAcademica.estado NO admite FINALIZADO/EVALUADO (enum distinto).
                    // Marcar como POR EVALUAR para juicios; no tocar ya APROBADO/REPROBADO/CERRADO.
                    if ($horario->idFicha) {
                        MatriculaAcademica::where('idFicha', $horario->idFicha)
                            ->where('idMateria', $gradoMateriaHorario->idMateria)
                            ->whereNotIn('estado', ['APROBADO', 'REPROBADO', 'CERRADO', 'POR EVALUAR'])
                            ->update(['estado' => 'POR EVALUAR']);
                    }
                }
            }

            $gradoMateria = GradoMateria::with(['materia.padre', 'gradoPrograma.grado'])
                ->find($idGradoMateria);

            if ($gradoMateria) {
                $gradoMateria->estado = EstadoHorarioMateria::FINALIZADO;
                $gradoMateria->save();
            }

            $nombreRap = $gradoMateria->materia->nombreMateria ?? 'Materia/RAP';
            $nombreCompetencia = $gradoMateria->materia->padre->nombreMateria ?? 'Competencia';
            $numeroTrimestre = $gradoMateria->gradoPrograma->grado->numeroGrado ?? 'N/A';

            $horarioConDocente = HorarioMateria::with(['contrato.persona.usuario', 'ficha'])
                ->where('idGradoMateria', $idGradoMateria)
                ->whereNotNull('idContrato')
                ->first();

            if ($horarioConDocente && $horarioConDocente->contrato && $horarioConDocente->contrato->persona) {
                $instructor = $horarioConDocente->contrato->persona;
                $fichaCodigo = $horarioConDocente->ficha->codigo ?? 'N/A';
                $correo = $instructor->email;
                $nombreDocente = $instructor->nombre1 . ' ' . $instructor->apellido1;
                $asunto = "Ha finalizado el RAP: " . $nombreRap;
                $mensaje = "Hola $nombreDocente,\n\n"
                    . "Te informamos que el RAP $nombreRap \n perteneciente a la competencia $nombreCompetencia "
                    . "ha finalizado. \n\n"
                    . "Ficha: $fichaCodigo \n"
                    . "Trimestre: $numeroTrimestre \n"
                    . "Por favor evalúa en Sofía Plus y carga los juicios evaluativos.\n\n"
                    . "Gracias por tu labor.";

                \App\Jobs\SendBasicEmail::dispatch($correo, $asunto, $mensaje);

                $idRemitente = $idUsuario ?? KeyUtil::user()?->id;
                if ($instructor && $instructor->usuario && $idRemitente) {
                    NotificacionSistema::create([
                        'fecha' => now()->toDateString(),
                        'hora' => now()->toTimeString(),
                        'asunto' => 'RAP FINALIZADO',
                        'mensaje' => "El RAP $nombreRap de la competencia $nombreCompetencia ha sido finalizado para la ficha $fichaCodigo.",
                        'estado_id' => 1,
                        'idUsuarioReceptor' => $instructor->usuario->id,
                        'idUsuarioRemitente' => (int) $idRemitente,
                        'idTipoNotificacion' => 1,
                        'idEmpresa' => KeyUtil::idCompany(),
                        'route' => '/raps'
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'message' => 'Rap finalizado correctamente',
                'data' => [
                    'idGradoMateria' => (int) $idGradoMateria,
                    'estado' => EstadoHorarioMateria::FINALIZADO,
                    'fechaFinal' => $fechaFinalNueva,
                ],
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['message' => 'Datos inválidos', 'errors' => $e->errors()], 422);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'message' => 'Ha ocurrido un error al finalizar el rap',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    public function interrumpirRap(Request $request)
    {
        try {
            $validated = $request->validate([
                'idGradoMateria' => 'required',
                'idFicha' => 'nullable',
                'fechaFinal' => 'required|date',
                'observacion' => 'nullable|string|max:2000',
            ]);

            $idGradoMateria = $validated['idGradoMateria'];
            $idFicha = $validated['idFicha'] ?? $request->input('idFicha');
            $bloqueo = TrimestreActualFichaService::abortSiGradoMateriaNoActual(
                (int) $idGradoMateria,
                $idFicha ? (int) $idFicha : null
            );
            if ($bloqueo) {
                return $bloqueo;
            }

            $tz = config('app.timezone');
            $nuevaFecha = Carbon::parse((string) $validated['fechaFinal'], $tz)->startOfDay();
            $fechaFinalNueva = $nuevaFecha->toDateString();
            $observacion = trim((string) ($validated['observacion'] ?? ''));

            $horarios = HorarioMateria::where('idGradoMateria', $idGradoMateria)
                ->whereNotIn('estado', [EstadoHorarioMateria::EVALUADO, EstadoHorarioMateria::FINALIZADO])
                ->get();

            if ($horarios->isEmpty()) {
                return response()->json([
                    'message' => 'No hay horarios pendientes de interrumpir para este RAP.',
                ], 422);
            }

            $fechaFinalActualRap = null;
            $fechaInicialMinima = null;
            foreach ($horarios as $horario) {
                if ($horario->fechaFinal) {
                    $fF = Carbon::parse((string) $horario->fechaFinal, $tz)->startOfDay();
                    if ($fechaFinalActualRap === null || $fF->gt($fechaFinalActualRap)) {
                        $fechaFinalActualRap = $fF;
                    }
                }
                if ($horario->fechaInicial) {
                    $fI = Carbon::parse((string) $horario->fechaInicial, $tz)->startOfDay();
                    if ($fechaInicialMinima === null || $fI->lt($fechaInicialMinima)) {
                        $fechaInicialMinima = $fI;
                    }
                }
            }

            if ($fechaInicialMinima && $nuevaFecha->lt($fechaInicialMinima)) {
                return response()->json([
                    'message' => 'La fecha no puede ser anterior a la fecha inicial del RAP.',
                ], 422);
            }

            if ($fechaFinalActualRap && $nuevaFecha->gte($fechaFinalActualRap)) {
                return response()->json([
                    'message' => 'La fecha de interrupción debe ser menor que la fecha final actual del RAP.',
                ], 422);
            }

            foreach ($horarios as $horario) {
                $bloqueoSesiones = $this->validarSesionesSinDatosPosteriores((int) $horario->id, $nuevaFecha);
                if ($bloqueoSesiones !== null) {
                    return $bloqueoSesiones;
                }
            }

            $idUsuario = auth()->id() ?? KeyUtil::user()?->id;

            DB::beginTransaction();

            foreach ($horarios as $horario) {
                $fechaFinalAnterior = $horario->fechaFinal
                    ? Carbon::parse((string) $horario->fechaFinal, $tz)->toDateString()
                    : null;

                // La fecha seleccionada es el límite inclusivo de la programación.
                // Conservar el estado histórico del horario; la interrupción se registra
                // en el historial y solo deja fuera las sesiones posteriores al límite.
                $horario->fechaFinal = $fechaFinalNueva;
                if ($observacion !== '') {
                    $prev = trim((string) ($horario->observacion ?? ''));
                    $horario->observacion = trim($prev . "\n" . '[INTERRUPCIÓN] ' . $observacion);
                }
                $horario->save();

                if ($idUsuario && Schema::hasTable('historialHorarioMateria')) {
                    HistorialHorarioMateria::create([
                        'idHorarioMateria' => $horario->id,
                        'tipoAccion' => EstadoHorarioMateria::INTERRUMPIDO,
                        'fechaFinalAnterior' => $fechaFinalAnterior,
                        'fechaFinalNueva' => $fechaFinalNueva,
                        'idUsuario' => (int) $idUsuario,
                        'observacion' => $observacion !== '' ? $observacion : null,
                    ]);
                }

                SesionMateria::where('idHorarioMateria', $horario->id)
                    ->whereDate('fechaSesion', '>', $fechaFinalNueva)
                    ->whereDoesntHave('asistencia')
                    ->when(Schema::hasTable('calificacionSesiones'), function ($q) {
                        $q->whereDoesntHave('calificacionSesiones');
                    })
                    ->delete();
            }

            DB::commit();
            return response()->json([
                'message' => 'Rap interrumpido correctamente'
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['message' => 'Datos inválidos', 'errors' => $e->errors()], 422);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'message' => 'Ha ocurrido un error al interrumpir el rap',
                'error' => $th->getMessage()
            ]);
        }
    }

    public function interrumpirHorario(Request $request, int $id): JsonResponse
    {
        return $this->quitarFechaEspecifica($request, $id, EstadoHorarioMateria::INTERRUMPIDO);
    }

    public function finalizarHorario(Request $request, int $id): JsonResponse
    {
        return $this->quitarFechaEspecifica($request, $id, EstadoHorarioMateria::FINALIZADO);
    }

    private function cerrarHorarioAnticipado(Request $request, int $id, string $nuevoEstado): JsonResponse
    {
        try {
            $validated = $request->validate([
                'fechaFinal' => 'required|date',
                'observacion' => 'nullable|string|max:2000',
            ]);

            $horario = HorarioMateria::find($id);
            if (! $horario) {
                return response()->json(['message' => 'Horario no encontrado'], 404);
            }

            if (! in_array($horario->estado, self::ESTADOS_HORARIO_ACTIVOS, true)) {
                return response()->json([
                    'message' => 'Solo se pueden modificar horarios en estado PENDIENTE o ASIGNADO.',
                ], 422);
            }

            $bloqueo = TrimestreActualFichaService::abortSiGradoMateriaNoActual(
                (int) $horario->idGradoMateria,
                $horario->idFicha ? (int) $horario->idFicha : null
            );
            if ($bloqueo) {
                return $bloqueo;
            }

            $tz = config('app.timezone');
            $nuevaFecha = Carbon::parse((string) $validated['fechaFinal'], $tz)->startOfDay();
            $fechaInicial = $horario->fechaInicial
                ? Carbon::parse((string) $horario->fechaInicial, $tz)->startOfDay()
                : null;
            $fechaFinalActual = $horario->fechaFinal
                ? Carbon::parse((string) $horario->fechaFinal, $tz)->startOfDay()
                : null;

            if ($fechaInicial && $nuevaFecha->lt($fechaInicial)) {
                return response()->json([
                    'message' => 'La fecha no puede ser anterior a la fecha inicial del horario.',
                ], 422);
            }

            if ($fechaFinalActual && $nuevaFecha->gt($fechaFinalActual)) {
                return response()->json([
                    'message' => 'La fecha no puede ser mayor que la fecha final actual del horario.',
                ], 422);
            }

            $hoy = Carbon::today($tz);
            if ($nuevaFecha->lt($hoy)) {
                $bloqueoSesiones = $this->validarSesionesSinDatosPosteriores($horario->id, $nuevaFecha);
                if ($bloqueoSesiones !== null) {
                    return $bloqueoSesiones;
                }
            }

            $idUsuario = auth()->id() ?? KeyUtil::user()?->id;
            if (! $idUsuario) {
                return response()->json(['message' => 'Sesión inválida'], 401);
            }

            $fechaFinalAnterior = $horario->fechaFinal
                ? Carbon::parse((string) $horario->fechaFinal, $tz)->toDateString()
                : null;
            $fechaFinalNueva = $nuevaFecha->toDateString();
            $observacion = trim((string) ($validated['observacion'] ?? ''));

            DB::transaction(function () use (
                $horario,
                $nuevoEstado,
                $fechaFinalAnterior,
                $fechaFinalNueva,
                $idUsuario,
                $observacion,
                $nuevaFecha
            ) {
                $horario->fechaFinal = $fechaFinalNueva;
                $horario->estado = $nuevoEstado;
                if ($observacion !== '') {
                    $marca = $nuevoEstado === EstadoHorarioMateria::INTERRUMPIDO
                        ? '[INTERRUPCIÓN]'
                        : '[FINALIZACIÓN]';
                    $prev = trim((string) ($horario->observacion ?? ''));
                    $horario->observacion = trim($prev . "\n" . $marca . ' ' . $observacion);
                }
                $horario->save();

                if (Schema::hasTable('historialHorarioMateria')) {
                    HistorialHorarioMateria::create([
                        'idHorarioMateria' => $horario->id,
                        'tipoAccion' => $nuevoEstado,
                        'fechaFinalAnterior' => $fechaFinalAnterior,
                        'fechaFinalNueva' => $fechaFinalNueva,
                        'idUsuario' => (int) $idUsuario,
                        'observacion' => $observacion !== '' ? $observacion : null,
                    ]);
                }

                SesionMateria::where('idHorarioMateria', $horario->id)
                    ->whereDate('fechaSesion', '>', $nuevaFecha->toDateString())
                    ->whereDoesntHave('asistencia')
                    ->when(Schema::hasTable('calificacionSesiones'), function ($q) {
                        $q->whereDoesntHave('calificacionSesiones');
                    })
                    ->delete();
            });

            $horario->refresh();

            $accionLabel = $nuevoEstado === EstadoHorarioMateria::INTERRUMPIDO
                ? 'interrumpido'
                : 'finalizado';

            return response()->json([
                'message' => "Horario {$accionLabel} correctamente",
                'data' => [
                    'id' => $horario->id,
                    'estado' => $horario->estado,
                    'fechaFinal' => $horario->fechaFinal,
                    'fechaFinalAnterior' => $fechaFinalAnterior,
                ],
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['message' => 'Datos inválidos', 'errors' => $e->errors()], 422);
        } catch (\Throwable $th) {
            Log::error('Error al cerrar horario anticipadamente', [
                'id' => $id,
                'estado' => $nuevoEstado,
                'error' => $th->getMessage(),
            ]);

            return response()->json([
                'message' => 'Ha ocurrido un error al actualizar el horario',
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    /**
     * Quita únicamente la fecha enviada (una ocurrencia).
     * No cambia el estado ni el rango de las demás fechas de la serie.
     */
    private function quitarFechaEspecifica(Request $request, int $id, string $tipoAccion): JsonResponse
    {
        try {
            $validated = $request->validate([
                'fechaFinal' => 'required|date',
                'observacion' => 'nullable|string|max:2000',
            ]);

            $horario = HorarioMateria::find($id);
            if (! $horario) {
                return response()->json(['message' => 'Horario no encontrado'], 404);
            }

            if (! in_array($horario->estado, self::ESTADOS_HORARIO_ACTIVOS, true)) {
                return response()->json([
                    'message' => 'Solo se pueden modificar horarios en estado PENDIENTE o ASIGNADO.',
                ], 422);
            }

            $bloqueo = TrimestreActualFichaService::abortSiGradoMateriaNoActual(
                (int) $horario->idGradoMateria,
                $horario->idFicha ? (int) $horario->idFicha : null
            );
            if ($bloqueo) {
                return $bloqueo;
            }

            $tz = config('app.timezone');
            $fechaObjetivo = Carbon::parse((string) $validated['fechaFinal'], $tz)->startOfDay();
            $fechaObjetivoStr = $fechaObjetivo->toDateString();
            $observacion = trim((string) ($validated['observacion'] ?? ''));

            $fechaInicial = $horario->fechaInicial
                ? Carbon::parse((string) $horario->fechaInicial, $tz)->startOfDay()
                : null;
            $fechaFinalActual = $horario->fechaFinal
                ? Carbon::parse((string) $horario->fechaFinal, $tz)->startOfDay()
                : null;

            if ($fechaInicial && $fechaObjetivo->lt($fechaInicial)) {
                return response()->json([
                    'message' => 'La fecha no puede ser anterior a la fecha inicial del horario.',
                ], 422);
            }

            if ($fechaFinalActual && $fechaObjetivo->gt($fechaFinalActual)) {
                return response()->json([
                    'message' => 'La fecha no puede ser mayor que la fecha final actual del horario.',
                ], 422);
            }

            if (! $this->fechaCoincideConDiaHorario($fechaObjetivo, $horario->idDia)) {
                return response()->json([
                    'message' => 'La fecha seleccionada no corresponde a una clase de este horario.',
                ], 422);
            }

            $idUsuario = auth()->id() ?? KeyUtil::user()?->id;
            if (! $idUsuario) {
                return response()->json(['message' => 'Sesión inválida'], 401);
            }

            $horariosAfectados = $this->horariosMismoSlotEnFecha($horario, $fechaObjetivoStr);
            if ($horariosAfectados->isEmpty()) {
                $horariosAfectados = collect([$horario]);
            }

            foreach ($horariosAfectados as $item) {
                if ($this->sesionFechaTieneDatosReales((int) $item->id, $fechaObjetivoStr)) {
                    return response()->json([
                        'message' => 'No se puede modificar esa fecha: la sesión tiene asistencia o calificaciones registradas.',
                    ], 422);
                }
            }

            $fechaFinalAnterior = $horario->fechaFinal
                ? Carbon::parse((string) $horario->fechaFinal, $tz)->toDateString()
                : null;

            DB::transaction(function () use (
                $horariosAfectados,
                $fechaObjetivoStr,
                $observacion,
                $idUsuario,
                $tz,
                $tipoAccion
            ) {
                foreach ($horariosAfectados as $item) {
                    $this->quitarOcurrenciaDeHorario(
                        $item,
                        $fechaObjetivoStr,
                        $observacion,
                        (int) $idUsuario,
                        $tz,
                        $tipoAccion
                    );
                }
            });

            $horarioActualizado = HorarioMateria::find($id);
            $accionLabel = $tipoAccion === EstadoHorarioMateria::FINALIZADO
                ? 'finalizado'
                : 'interrumpido';

            return response()->json([
                'message' => "Horario {$accionLabel} correctamente",
                'data' => [
                    'id' => $id,
                    'estado' => $horarioActualizado?->estado,
                    'fechaFinal' => $horarioActualizado?->fechaFinal,
                    'fechaFinalAnterior' => $fechaFinalAnterior,
                ],
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['message' => 'Datos inválidos', 'errors' => $e->errors()], 422);
        } catch (\Throwable $th) {
            Log::error('Error al quitar fecha de horario', [
                'id' => $id,
                'accion' => $tipoAccion,
                'error' => $th->getMessage(),
            ]);

            return response()->json([
                'message' => 'Ha ocurrido un error al actualizar el horario',
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    private function fechaCoincideConDiaHorario(Carbon $fecha, $idDia): bool
    {
        $carbonDay = (int) $fecha->dayOfWeek;
        $dbIdDia = $carbonDay === 0 ? 7 : $carbonDay;

        return (int) $idDia === $dbIdDia;
    }

    /**
     * Fechas reales de clase del horario (mismo día de la semana, rango inclusivo).
     *
     * @return array<int, string>
     */
    private function ocurrenciasDeHorario(HorarioMateria $horario, string $tz): array
    {
        return $this->ocurrenciasEnRango(
            $horario->fechaInicial ? (string) $horario->fechaInicial : null,
            $horario->fechaFinal ? (string) $horario->fechaFinal : null,
            $horario->idDia,
            $tz
        );
    }

    /**
     * @return array<int, string>
     */
    private function ocurrenciasEnRango(?string $fechaInicial, ?string $fechaFinal, $idDia, string $tz): array
    {
        if (! $fechaInicial || ! $idDia) {
            return [];
        }

        $start = Carbon::parse($fechaInicial, $tz)->startOfDay();
        $end = $fechaFinal
            ? Carbon::parse($fechaFinal, $tz)->startOfDay()
            : $start->copy();

        if ($end->lt($start)) {
            return [];
        }

        $fechas = [];
        $cursor = $start->copy();
        while ($cursor->lte($end)) {
            if ($this->fechaCoincideConDiaHorario($cursor, $idDia)) {
                $fechas[] = $cursor->toDateString();
            }
            $cursor->addDay();
        }

        return $fechas;
    }

    /**
     * Clones del mismo slot y de la misma serie (no otras programaciones).
     */
    private function horariosMismoSlotEnFecha(HorarioMateria $horario, string $fecha): Collection
    {
        return HorarioMateria::where('idFicha', $horario->idFicha)
            ->where('idGradoMateria', $horario->idGradoMateria)
            ->where('idDia', $horario->idDia)
            ->where('horaInicial', $horario->horaInicial)
            ->where('horaFinal', $horario->horaFinal)
            ->whereDate('fechaInicial', $horario->fechaInicial)
            ->whereDate('fechaFinal', $horario->fechaFinal)
            ->whereIn('estado', self::ESTADOS_HORARIO_ACTIVOS)
            ->whereDate('fechaInicial', '<=', $fecha)
            ->whereDate('fechaFinal', '>=', $fecha)
            ->get();
    }

    private function sesionFechaTieneDatosReales(int $idHorarioMateria, string $fecha): bool
    {
        $query = SesionMateria::where('idHorarioMateria', $idHorarioMateria)
            ->whereDate('fechaSesion', $fecha)
            ->withCount([
                'asistencia as asistencia_real_count' => function ($q) {
                    $q->where('asistio', true);
                },
            ]);

        if (Schema::hasTable('calificacionSesiones')) {
            $query->withCount('calificacionSesiones');
        }

        $sesion = $query->first();
        if (! $sesion) {
            return false;
        }

        if ((int) $sesion->asistencia_real_count > 0) {
            return true;
        }

        if (Schema::hasTable('calificacionSesiones') && (int) ($sesion->calificacion_sesiones_count ?? 0) > 0) {
            return true;
        }

        return false;
    }

    private function quitarOcurrenciaDeHorario(
        HorarioMateria $horario,
        string $fechaObjetivo,
        string $observacion,
        int $idUsuario,
        string $tz,
        string $tipoAccion = EstadoHorarioMateria::INTERRUMPIDO
    ): void {
        $ocurrencias = $this->ocurrenciasDeHorario($horario, $tz);
        if (! in_array($fechaObjetivo, $ocurrencias, true)) {
            return;
        }

        $antes = [];
        $despues = [];
        $vistoObjetivo = false;
        foreach ($ocurrencias as $fecha) {
            if ($fecha === $fechaObjetivo) {
                $vistoObjetivo = true;
                continue;
            }
            if (! $vistoObjetivo) {
                $antes[] = $fecha;
            } else {
                $despues[] = $fecha;
            }
        }

        $fechaFinalAnterior = $horario->fechaFinal
            ? Carbon::parse((string) $horario->fechaFinal, $tz)->toDateString()
            : null;

        $this->eliminarSesionDeFecha((int) $horario->id, $fechaObjetivo);

        if ($observacion !== '') {
            $marca = $tipoAccion === EstadoHorarioMateria::FINALIZADO
                ? '[FINALIZACIÓN]'
                : '[INTERRUPCIÓN]';
            $prev = trim((string) ($horario->observacion ?? ''));
            $horario->observacion = trim($prev . "\n" . $marca . ' ' . $observacion);
        }

        if (empty($antes) && empty($despues)) {
            $this->vaciarHorarioSinOcurrencias($horario);
            if ($horario->exists) {
                $this->registrarHistorialInterrupcion(
                    $horario,
                    $fechaFinalAnterior,
                    $fechaObjetivo,
                    $idUsuario,
                    $observacion,
                    $tipoAccion
                );
            }
            return;
        }

        $clon = null;
        $nuevoInicio = ! empty($antes) ? $antes[0] : (! empty($despues) ? $despues[0] : null);
        $nuevoFin = ! empty($antes) ? $antes[count($antes) - 1] : null;

        if (! empty($antes) && ! empty($despues)) {
            $clon = $horario->replicate();
            $clon->fechaInicial = $despues[0];
            $clon->fechaFinal = $despues[count($despues) - 1];
            $clon->save();

            SesionMateria::where('idHorarioMateria', $horario->id)
                ->whereDate('fechaSesion', '>', $fechaObjetivo)
                ->update(['idHorarioMateria' => $clon->id]);

            HorarioMateria::generarRmis($clon);

            $horario->fechaFinal = $antes[count($antes) - 1];
            $nuevoFin = $horario->fechaFinal;
        } elseif (! empty($antes)) {
            $horario->fechaFinal = $antes[count($antes) - 1];
            $nuevoFin = $horario->fechaFinal;
        } else {
            $horario->fechaInicial = $despues[0];
            $nuevoInicio = $horario->fechaInicial;
        }

        $horario->save();

        $this->ajustarAsignacionesTrasHueco($horario, $fechaObjetivo, $nuevoInicio, $nuevoFin, $clon);
        $this->registrarHistorialInterrupcion(
            $horario,
            $fechaFinalAnterior,
            $fechaObjetivo,
            $idUsuario,
            $observacion,
            $tipoAccion
        );
    }

    private function eliminarSesionDeFecha(int $idHorarioMateria, string $fecha): void
    {
        $sesiones = SesionMateria::where('idHorarioMateria', $idHorarioMateria)
            ->whereDate('fechaSesion', $fecha)
            ->get();

        foreach ($sesiones as $sesion) {
            $sesion->asistencia()->delete();
            if (Schema::hasTable('calificacionSesiones')) {
                $sesion->calificacionSesiones()->delete();
            }
            $sesion->delete();
        }
    }

    private function vaciarHorarioSinOcurrencias(HorarioMateria $horario): void
    {
        AsignacionSesion::where('idHorarioMateria', $horario->id)->delete();

        $horario->sesionMaterias()->each(function ($sesion) {
            $sesion->asistencia()->delete();
            if (Schema::hasTable('calificacionSesiones')) {
                $sesion->calificacionSesiones()->delete();
            }
            $sesion->delete();
        });

        $horario->detallesRmi()->where(function ($q) {
            $q->where('estado', 'PENDIENTE')
                ->whereNull('archivoPago')
                ->whereNull('urlInforme')
                ->whereNull('numeroPlanilla');
        })->delete();

        $totalRecordsForRap = HorarioMateria::where('idGradoMateria', $horario->idGradoMateria)->count();

        if ($totalRecordsForRap > 1) {
            $horario->delete();
            return;
        }

        $horario->update([
            'idDia' => null,
            'idInfraestructura' => null,
            'idContrato' => null,
            'fechaFinal' => null,
            'horaInicial' => null,
            'horaFinal' => null,
            'observacion' => $horario->observacion,
            'estado' => EstadoHorarioMateria::PENDIENTE,
        ]);
    }

    private function ajustarAsignacionesTrasHueco(
        HorarioMateria $horario,
        string $fechaEliminada,
        ?string $nuevoInicio,
        ?string $nuevoFin,
        ?HorarioMateria $clon
    ): void {
        $asignaciones = AsignacionSesion::where('idHorarioMateria', $horario->id)->get();

        foreach ($asignaciones as $asig) {
            $aIni = Carbon::parse((string) $asig->fechaInicio)->toDateString();
            $aFin = Carbon::parse((string) $asig->fechaFin)->toDateString();

            if ($clon && $aFin > $fechaEliminada) {
                $copyIni = $aIni > (string) $clon->fechaInicial ? $aIni : (string) $clon->fechaInicial;
                $copyFin = $aFin < (string) $clon->fechaFinal ? $aFin : (string) $clon->fechaFinal;
                if ($copyIni <= $copyFin) {
                    AsignacionSesion::create([
                        'idHorarioMateria' => $clon->id,
                        'idContrato' => $asig->idContrato,
                        'tipoAsignacion' => $asig->tipoAsignacion,
                        'fechaInicio' => $copyIni,
                        'fechaFin' => $copyFin,
                        'observacion' => $asig->observacion,
                    ]);
                }
            }

            if ($nuevoInicio && $aFin < $nuevoInicio) {
                $asig->delete();
                continue;
            }
            if ($nuevoFin && $aIni > $nuevoFin) {
                $asig->delete();
                continue;
            }

            $changed = false;
            if ($nuevoInicio && $aIni < $nuevoInicio) {
                $asig->fechaInicio = $nuevoInicio;
                $changed = true;
            }
            if ($nuevoFin && $aFin > $nuevoFin) {
                $asig->fechaFin = $nuevoFin;
                $changed = true;
            }
            if ($changed) {
                $asig->save();
            }
        }
    }

    private function registrarHistorialInterrupcion(
        HorarioMateria $horario,
        ?string $fechaFinalAnterior,
        string $fechaObjetivo,
        int $idUsuario,
        string $observacion,
        string $tipoAccion = EstadoHorarioMateria::INTERRUMPIDO
    ): void {
        if (! Schema::hasTable('historialHorarioMateria') || ! $horario->id) {
            return;
        }

        HistorialHorarioMateria::create([
            'idHorarioMateria' => $horario->id,
            'tipoAccion' => $tipoAccion,
            'fechaFinalAnterior' => $fechaFinalAnterior,
            'fechaFinalNueva' => $fechaObjetivo,
            'idUsuario' => $idUsuario,
            'observacion' => $observacion !== '' ? $observacion : null,
        ]);
    }

    private function validarSesionesSinDatosPosteriores(int $idHorarioMateria, Carbon $nuevaFecha): ?JsonResponse
    {
        $withCount = ['asistencia'];
        if (Schema::hasTable('calificacionSesiones')) {
            $withCount[] = 'calificacionSesiones';
        }

        $sesiones = SesionMateria::where('idHorarioMateria', $idHorarioMateria)
            ->whereDate('fechaSesion', '>', $nuevaFecha->toDateString())
            ->withCount($withCount)
            ->get();

        foreach ($sesiones as $sesion) {
            $tieneDatos = ((int) $sesion->asistencia_count) > 0;
            if (Schema::hasTable('calificacionSesiones')) {
                $tieneDatos = $tieneDatos || ((int) $sesion->calificacion_sesiones_count) > 0;
            }

            if ($tieneDatos) {
                return response()->json([
                    'message' => 'No se puede usar esa fecha: existen sesiones posteriores con asistencia o calificaciones registradas.',
                ], 422);
            }
        }

        return null;
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
