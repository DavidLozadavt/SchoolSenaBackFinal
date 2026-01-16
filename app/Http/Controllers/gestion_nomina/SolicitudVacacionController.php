<?php

namespace App\Http\Controllers\gestion_nomina;

use App\Enums\StatusVacaciones;
use App\Enums\TypePaymentMethodContract;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Contract;
use App\Models\Nomina\Nomina;
use App\Models\Nomina\ObservacionSolicitudVacacion;
use App\Models\Nomina\SolicitudVacacion;
use App\Models\Nomina\Vacacion;
use App\Util\KeyUtil;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SolicitudVacacionController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request): JsonResponse
    {
        $idContrato = $request->idContrato ?? null;
        $user = KeyUtil::user();
        $contract = Contract::where('idEstado', 1)
            ->where('idpersona', $user->idpersona)
            ->latest()
            ->first();

        $solicitudVacacion = SolicitudVacacion::with(['vacaciones', 'observaciones'])
            ->whereHas('vacaciones', function ($query) use ($idContrato, $contract) {
                $query->where('idContrato', $idContrato ?? $contract->id);
            })->orderBy('id', 'desc')
            ->get();

        return response()->json($solicitudVacacion);
    }

    public function getSolicitudesBySupervisor(Request $request): JsonResponse
    {
        $search           = $request->search;

        $fechaSolicitud   = $request->fechaSolicitud;
        $fechaLiquidacion = $request->fechaLiquidacion;
        $fechaEjecucion   = $request->fechaEjecucion;
        $estado           = $request->estado;
        $periodos         = $request->periodos;
        $numDias          = $request->numDias;
        $valor            = $request->valor;
        $fechaFinal       = $request->fechaFinal;

        $totalData        = $request->totalData;

        $requestVacacionts = SolicitudVacacion::filterAdvanceSolicitudVacacion(
            $search,
            $fechaSolicitud,
            $fechaLiquidacion,
            $fechaEjecucion,
            $estado,
            $periodos,
            $numDias,
            $valor,
            $fechaFinal,
        )->with(['vacaciones.contrato.persona.usuario'])
            ->orderBy('id', 'desc')
            ->paginate(25 ?? $totalData);

        return response()->json([
            'total'       => $requestVacacionts->total(),
            'solicitudes' => $requestVacacionts->items()
        ]);
    }

    public function createSolicitudVacacionBySupervisor(Request $request): JsonResponse
    {
        $periodos     = $request->periodos;
        $fechaInicial = $request->fechaInicial;
        $comentario   = $request->comentario;
        $idContrato   = $request->idContrato;

        $numDias = count($periodos) * 15;
        $fechaFinal = Carbon::parse($fechaInicial)->addDays($numDias);

        $contrato = Contract::with(['salario'])->findOrFail($idContrato);

        if (!$contrato) {
            return response()->json(['error' => 'No se encontró un contrato activo para esta persona.'], 404);
        }

        // $valueVacaciones = 0;
        $valueVacaciones = $this->calculateValueNormalVacaciones($contrato, $numDias);
        // if ($contrato->formaPago == TypePaymentMethodContract::NORMAL) {
        //     $valueVacaciones = $this->calculateValueNormalVacaciones($contrato, $numDias);
        // } else if ($contrato->formaPago == TypePaymentMethodContract::COMISIONES) {
        //     $valueVacaciones = $this->calculateValueComisionesVacaciones($contrato, $numDias);
        // } else {
        //     $valueVacaciones = $this->calculateIntegralSalaryVacaciones($contrato, $numDias);
        // }

        $invalidPeriods = Vacacion::whereIn('id', $periodos)
            ->whereNotNull('idSolicitud')
            ->exists();

        if ($invalidPeriods) {
            return response()->json([
                'message' => 'Algunos de los períodos seleccionados no se pueden asignar porque ya tienen una solicitud asociada.',
            ], 400);
        }

        $vacaciones = Vacacion::where('idContrato', $contrato->id)
            ->where('estado', StatusVacaciones::PENDIENTE)
            ->whereIn('id', $periodos)
            ->whereNull('idSolicitud')
            ->get();


        if ($vacaciones->isEmpty()) {
            return response()->json(['error' => 'No se encontraron vacaciones pendientes para los periodos seleccionados.'], 404);
        }

        $periodosArray = $vacaciones->pluck('periodo')->toArray();

        $solicitud = $this->createSolicitudData(
            $fechaInicial,
            $fechaFinal,
            $periodosArray,
            $numDias,
            $comentario,
            $vacaciones,
            KeyUtil::user(),
            $valueVacaciones,
            StatusVacaciones::ACEPTADO,
            KeyUtil::lastContractActive()->id,
        );

        if ($solicitud) {
            return response()->json([
                'message'   => 'Solicitud creada con éxito.',
                'solicitud' => $solicitud,
            ], 201);
        } else {
            return response()->json([
                'message' => 'Error al crear la solicitud.',
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request): JsonResponse
    {
        $solicitudVacacion = SolicitudVacacion::create($request->all());
        return response()->json($solicitudVacacion, 201);
    }

    /**
     * Create vacations request by worker
     * @param \Illuminate\Http\Request $request
     * @return JsonResponse|mixed
     */
    public function createSolicitudVacacion(Request $request): JsonResponse
    {
        $periodos     = $request->periodos;
        $fechaInicial = $request->fechaInicial;
        $comentario   = $request->comentario;
        $user      = KeyUtil::user();
        $idPersona = $user->idpersona;

        $contrato = Contract::with(['salario'])
            ->where('idpersona', $idPersona)
            ->where('idEstado', 1)
            ->latest()
            ->first();

        if (!$contrato) {
            return response()->json(['error' => 'No se encontró un contrato activo para esta persona.'], 404);
        }

        $fechaInicioContrato = Carbon::parse($contrato->fechaContratacion);
        $fechaFinContrato = $contrato->fechaFinalContrato
            ? Carbon::parse($contrato->fechaFinalContrato)
            : Carbon::now();

        $mesesTrabajados = $fechaInicioContrato->diffInMonths($fechaFinContrato);
        $numDias = (15 / 12) * $mesesTrabajados;

        $fechaFinal = Carbon::parse($fechaInicial)->addDays($numDias);


          $valueVacaciones = $this->calculateValueNormalVacaciones($contrato, $numDias);

        // if ($contrato->formaPago == TypePaymentMethodContract::NORMAL) {
        //     $valueVacaciones = $this->calculateValueNormalVacaciones($contrato, $numDias);
        // } else if ($contrato->formaPago == TypePaymentMethodContract::COMISIONES) {
        //     $valueVacaciones = $this->calculateValueComisionesVacaciones($contrato, $numDias);
        // } else {
        //     $valueVacaciones = $this->calculateIntegralSalaryVacaciones($contrato, $numDias);
        // }

        $invalidPeriods = Vacacion::whereIn('id', $periodos)
            ->whereNotNull('idSolicitud')
            ->exists();

        if ($invalidPeriods) {
            return response()->json([
                'message' => 'Algunos de los períodos seleccionados no se pueden asignar porque ya tienen una solicitud asociada.',
            ], 400);
        }

        $vacaciones = Vacacion::where('idContrato', $contrato->id)
            ->where('estado', StatusVacaciones::PENDIENTE)
            ->whereIn('id', $periodos)
            ->whereNull('idSolicitud')
            ->get();

        if ($vacaciones->isEmpty()) {
            return response()->json(['error' => 'No se encontraron vacaciones pendientes para los periodos seleccionados.'], 404);
        }

        $periodosArray = $vacaciones->pluck('periodo')->toArray();

        $solicitud = $this->createSolicitudData(
            $fechaInicial,
            $fechaFinal,
            $periodosArray,
            $numDias,
            $comentario,
            $vacaciones,
            $user,
            $valueVacaciones,
        );

        if ($solicitud) {
            return response()->json([
                'message'   => 'Solicitud creada con éxito.',
                'solicitud' => $solicitud,
            ], 201);
        } else {
            return response()->json([
                'message' => 'Error al crear la solicitud.',
            ], 500);
        }
    }

    /**
     * Create data vacations request
     * @param mixed $fechaInicial
     * @param mixed $fechaFinal
     * @param mixed $periodosArray
     * @param mixed $numDias
     * @param mixed $comentario
     * @param mixed $vacaciones
     * @param mixed $user
     * @param Contract $contrato
     * @param float|int $countPeriodos
     * @return void
     */
    private function createSolicitudData(
        $fechaInicial,
        $fechaFinal,
        $periodosArray,
        $numDias,
        $comentario,
        $vacaciones,
        $user = null,
        $valorVacaciones,
        $state = null,
        $idContratoSupervisor = null
    ): null|SolicitudVacacion {
        $solicitud = null;

        DB::transaction(function () use (
            $fechaInicial,
            $fechaFinal,
            $periodosArray,
            $numDias,
            $comentario,
            $vacaciones,
            $user,
            $valorVacaciones,
            &$solicitud,
            $state,
            $idContratoSupervisor,
        ) {


            $solicitud = SolicitudVacacion::create([
                'fechaSolicitud' => now(),
                'fechaEjecucion' => $fechaInicial,
                'periodos'       => json_encode($periodosArray),
                'numDias'        => $numDias,
                'estado'         => $state ?? StatusVacaciones::PENDIENTE,
                'valor'          => $valorVacaciones,
                'fechaFinal'     => $fechaFinal,
                'idContratoSupervisor' => $idContratoSupervisor ?? null,
            ]);

            foreach ($vacaciones as $vacacion) {
                $vacacion->update(['idSolicitud' => $solicitud->id]);
            }

            ObservacionSolicitudVacacion::create([
                'fecha'       => now(),
                'observacion' => $comentario,
                'idSolicitud' => $solicitud->id,
                'idUsuario'   => $user->id ?? KeyUtil::user()->id,
            ]);
        });

        return $solicitud ? $solicitud->load(['observaciones', 'vacaciones']) : null;
    }

    /**
     * Update request vacations by supervisor
     * @param \Illuminate\Http\Request $request
     * @param string $id
     * @return JsonResponse|mixed
     */
    public function updateRequestVacation(Request $request, string $id): JsonResponse
    {
        $solicitudVacacion = SolicitudVacacion::findOrFail($id);

        $user = KeyUtil::user();

        $solicitudVacacion->update([
            'estado' => $request->estado,
        ]);

        ObservacionSolicitudVacacion::create([
            'fecha'       => now(),
            'observacion' => $request->comentario,
            'idSolicitud' => $id,
            'idUsuario'   => $user->id,
        ]);

        return response()->json($solicitudVacacion->load('observaciones'), 200);
    }

    /**
     * Display the specified resource.
     *
     * @param  SolicitudVacacion  $solicitudVacacion
     * @return \Illuminate\Http\Response
     */
    public function show(string $id): JsonResponse
    {
        $solicitudVacacion = SolicitudVacacion::findOrFail($id);
        return response()->json($solicitudVacacion);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  SolicitudVacacion  $solicitudVacacion
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $solicitudVacacion = SolicitudVacacion::findOrFail($id);
        $solicitudVacacion->update($request->all());
        return response()->json($solicitudVacacion);
    }

    public function updatePeriodosByWorker(Request $request, string $id): JsonResponse
    {
        $periodos     = $request->periodos;
        $fechaInicial = $request->fechaInicial;
        $comentario   = $request->comentario;

        $user      = KeyUtil::user();
        $idPersona = $user->idpersona;

        $numDias    = count($periodos) * 15;
        $fechaFinal = Carbon::parse($fechaInicial)->addDays($numDias);

        $solicitudVacacion = SolicitudVacacion::findOrFail($id);

        $invalidPeriods = Vacacion::whereIn('id', $periodos)
            ->where(function ($query) use ($id) {
                $query->where('estado', '!=', StatusVacaciones::PENDIENTE)
                    ->orWhere('idSolicitud', '<>', $id);
            })
            ->exists();

        if ($invalidPeriods) {
            return response()->json([
                'message' => 'Algunos de los períodos seleccionados no se pueden actualizar porque ya tienen un estado diferente a PENDIENTE o están asociados a otra solicitud.',
            ], 400);
        }

        $solicitudVacacion = $this->updateSolicitudData(
            $id,
            $periodos,
            $fechaInicial,
            $fechaFinal,
            $numDias,
            $comentario,
            $user,
            $solicitudVacacion,

        );

        if ($solicitudVacacion) {
            return response()->json([
                'message'   => 'Solicitud actualizada con éxito.',
                'solicitud' => $solicitudVacacion,
            ], 201);
        } else {
            return response()->json([
                'message' => 'Error al actualizar la solicitud.',
            ], 500);
        }
    }

    private function updateSolicitudData(
        $id,
        $periodos,
        $fechaInicial,
        $fechaFinal,
        $numDias,
        $comentario,
        $user,
        $solicitudVacacion,
        // $countPeriodos,
    ): null|SolicitudVacacion {
        if (!$solicitudVacacion) {
            return null; // Validación inicial para evitar errores si no se encuentra el objeto
        }

        DB::transaction(function () use (
            $id,
            $periodos,
            $fechaInicial,
            $fechaFinal,
            $numDias,
            $comentario,
            $user,
            &$solicitudVacacion
        ) {
            // Liberar todas las vacaciones asociadas previamente a la solicitud
            Vacacion::where('idSolicitud', $id)->update(['idSolicitud' => null]);

            // Asociar las vacaciones seleccionadas a la solicitud
            Vacacion::whereIn('id', $periodos)->update([
                'idSolicitud' => $id,
                'estado'      => StatusVacaciones::PENDIENTE,
            ]);

            // Obtener los periodos en formato array para actualizarlos en la solicitud
            $periodosArray = Vacacion::whereIn('id', $periodos)->pluck('periodo')->toArray();

            // Actualizar la solicitud de vacaciones
            $solicitudVacacion->update([
                'fechaEjecucion' => $fechaInicial,
                'periodos'       => json_encode($periodosArray), // Aseguramos que los periodos sean guardados como JSON
                'numDias'        => $numDias,
                'estado'         => StatusVacaciones::PENDIENTE,
                'valor'          => 100000,
                'fechaFinal'     => $fechaFinal,
            ]);

            // Crear una observación para el cambio de estado
            ObservacionSolicitudVacacion::create([
                'fecha'       => now(),
                'observacion' => $comentario,
                'idSolicitud' => $id,
                'idUsuario'   => $user->id,
            ]);
        });

        // Retornar la solicitud con sus relaciones cargadas
        return $solicitudVacacion->load(['observaciones', 'vacaciones']);
    }


    private function calculateValueNormalVacaciones(Contract $contrato, float|int $countPeriodos): int|float
    {

        // $nomina = Nomina::where('idContrato', $contrato->id)->latest()->first();
//es por los dias segun el contrato si es 6 meses 7.5
        $salarioBase = $contrato['salario']['valor'];

        $valorVacaciones = round(($salarioBase / 30) * $countPeriodos);
        return $valorVacaciones;
    }

    private function calculateValueComisionesVacaciones(Contract $contrato, float|int $countPeriodos): int|float
    {
        $salarioBase = $contrato['salario']['valor'];

        $fechaLimite = now()->subMonths(12);

        $nominas = Nomina::where('idContrato', $contrato->id)
            ->where('created_at', '>=', $fechaLimite)
            ->get();

        $totalValorComisionesNominas = 0;
        foreach ($nominas as $nomina) {
            $totalValorComisionesNominas += $nomina->comisiones;
        }

        $totalValorVacaciones = $salarioBase + $totalValorComisionesNominas;

        return $totalValorVacaciones;
    }

    private function calculateIntegralSalaryVacaciones(Contract $contrato, float|int $countPeriodos): float
    {
        $salarioBase = $contrato['salario']['valor'];
        $fechaLimite = now()->subMonths(12);

        $nominas = Nomina::where('idContrato', $contrato->id)
            ->where('created_at', '>=', $fechaLimite)
            ->get();

        $totalComisiones = 0;
        $totalHorasExtras = 0;
        $totalSalarios = $salarioBase * 12;
        $count = 0;

        foreach ($nominas as $nomina) {
            $totalComisiones += $nomina->comisiones;
            $totalHorasExtras += $nomina->horas_extras;
            $count++;
        }

        $promedioSalarios = $count > 0 ? $totalSalarios / $count : 0;

        $salarioIntegral = $salarioBase + $totalComisiones + $totalHorasExtras + $promedioSalarios;

        return $salarioIntegral;
    }


    /**
     * Remove the specified resource from storage.
     *
     * @param SolicitudVacacion  $solicitudVacacion
     * @return \Illuminate\Http\Response
     */
    public function destroy(string $id): JsonResponse
    {
        $solicitudVacacion = SolicitudVacacion::findOrFail($id);
        $solicitudVacacion->delete();
        return response()->json(null, 204);
    }
}
