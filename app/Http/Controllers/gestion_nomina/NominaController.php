<?php

namespace App\Http\Controllers\gestion_nomina;

use App\Enums\PayrollConfigurationStatus;
use App\Http\Controllers\Controller;
use App\Models\Contract;
use App\Models\LiquidacionNomina;
use App\Models\Nomina\ConfiguracionNomina;
use App\Models\Nomina\Nomina;
use App\Models\Nomina\TarifaArl;
use App\Traits\PayrollOperations;
use App\Util\KeyUtil;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class NominaController extends Controller
{
    use PayrollOperations;

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request): JsonResponse
    {
        $nominas = Nomina::query()
            ->with(['contrato.persona.usuario.activationCompanyUsers'])->whereHas('contrato', function ($query) {
                $query->where('idempresa', KeyUtil::idCompany());
            })->orderBy('id', 'desc')
            ->get();
        return response()->json($nominas, 200);
    }

    public function getMyPayrolls(): JsonResponse
    {
        $myPayrolls = Nomina::query()
            ->with(['contrato.persona.usuario.activationCompanyUsers'])->whereHas('contrato', function ($query) {
                $query->where('idempresa', KeyUtil::idCompany())
                    ->where('idContrato', KeyUtil::lastContractActive()->id);
            })->orderBy('id', 'desc')
            ->get();
        return response()->json($myPayrolls);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request): JsonResponse
    {
        $nomina = Nomina::create($request->all());
        return response()->json($nomina);
    }

    public function calculateNomina(Request $request): JsonResponse
    {
        $idContract   = $request->idContract;
        $idCostCenter = $request->idCentroCosto;

        $contract = Contract::with(['salario.rol'])->findOrFail($idContract);
        $payrollconfiguration = ConfiguracionNomina::where('idEmpresa', KeyUtil::idCompany())->first();

        $salario = $contract->salario;

        $payroll = Nomina::latest('id')
            ->where('idContrato', $idContract)
            ->first();

        $arlRates = TarifaArl::all()->keyBy('nivel');
        $porcentajeCotizacion = $arlRates[$contract->salario->rol->riesgo]->porcentajeCotizacion;

        $valHorasBase   = $this->getBaseHourValue(
            intval($salario->valor),
            intval($payrollconfiguration->numHorasMes)
        );

        $payrollPayment = $this->getPayrollPayment(
            intval($salario->valor),
            intval($payrollconfiguration->diaPago)
        );

        $valorHorasExtras = $this->multiplyValues(
            $valHorasBase,
            floatval($payroll->horasExtras ?? 0),
            $payrollconfiguration->valorHorasExD
        );

        $subTotal = $this->getSubTotal(
            $payrollPayment,
            intval($payroll->comisiones ?? 0),
            intval($valorHorasExtras ?? 0)
        );

        $auxTransport = $this->getTransportationAssistance(
            floatval($payrollconfiguration->smlv),
            floatval($salario->valor),
            intval($payrollconfiguration->numAuxTrans),
            $payrollconfiguration->comparacionAuxTrans,
            floatval($payrollconfiguration->valAuxTransporte),
            intval($payrollconfiguration->diaPago)
        );

        $devengado = $this->sumValues($subTotal, $auxTransport);

        $healthValue = $this->calculatePercentageValue(
            floatval($subTotal),
            floatval($payrollconfiguration->porcentajeSalud)
        );

        $pension = $this->calculatePercentageValue(
            floatval($subTotal),
            floatval($payrollconfiguration->porcentajePension)
        );

        $fsp = $this->getValueFspOrContributionsFsp(
            floatval($payrollconfiguration->smlv),
            floatval($salario->valor),
            floatval($payrollconfiguration->porcentajeFsp),
            $payrollconfiguration->comparacionFsp,
            floatval($payrollconfiguration->numAuxFsp),
            floatval($subTotal),
        );

        $netoPagado = $this->calculateDifferenceAfterSum(
            floatval($devengado),
            floatval($healthValue),
            floatval($pension)
        );

        $aportesSalud = $this->calculateWeightedSum(
            floatval($payrollPayment),
            floatval($payroll->comisiones ?? 0),
            floatval($valorHorasExtras ?? 0),
            floatval($payrollconfiguration->porcentajeAporteSalud)
        );

        $aportesPension = $this->calculateWeightedSum(
            floatval($payrollPayment),
            floatval($payroll->comisiones ?? 0),
            floatval($valorHorasExtras ?? 0),
            floatval($payrollconfiguration->porcentajeAportePension)
        );

        $aportesFsp = $this->getValueFspOrContributionsFsp(
            floatval($payrollconfiguration->smlv),
            floatval($salario->valor),
            floatval($payrollconfiguration->porcentajeAporteFsp),
            $payrollconfiguration->comparacionFsp,
            floatval($payrollconfiguration->numAuxFsp),
            floatval($subTotal),
        );

        $totalDectoSegSocial = $this->sumValues($aportesSalud, $aportesPension, $aportesFsp);

        $cesantias = $this->multiplyValues($devengado, $payrollconfiguration->porcentajeCesantias / 100);

        $intCes = $this->multiplyValues($cesantias, $payrollconfiguration->porcentajeIntCesantias / 100);

        $prima = $this->multiplyValues($devengado, $payrollconfiguration->porcentajePrima / 100);

        $vacaciones = $this->multiplyValues($salario->valor, $payrollconfiguration->porcentajeVacaciones / 100);

        $cajaCompensacion = $this->calculateSumAndMultiply(
            $payrollPayment,
            $payroll->comisiones ?? 0,
            $valorHorasExtras ?? 0,
            $payrollconfiguration->porcentajeCajaComp / 100
        );

        $icbf = $this->calculateSumAndMultiply(
            $payrollPayment,
            $payroll->comisiones ?? 0,
            $valorHorasExtras ?? 0,
            $payrollconfiguration->porcentajeIcbf / 100
        );

        $sena = $this->calculateSumAndMultiply(
            $payrollPayment,
            $payroll->comisiones ?? 0,
            $valorHorasExtras ?? 0,
            $payrollconfiguration->porcentajeSena / 100
        );

        $aportFiscales = $this->sumValues($cajaCompensacion, $icbf, $sena);

        $arl = $this->calculateSumAndMultiply(
            $payrollPayment,
            floatval($payroll->comisiones ?? 0),
            floatval($valorHorasExtras ?? 0),
            $porcentajeCotizacion / 100
        );

        $totalApropiaciones = $this->sumValues(
            $cesantias,
            $intCes,
            $prima,
            $vacaciones,
            $aportFiscales,
            $arl
        );

        $valorTotalPorTrabajador = $this->sumValues(
            $devengado,
            $totalDectoSegSocial,
            $totalApropiaciones,
        );

        $newPayroll = Nomina::create([
            'idContrato'        => $idContract,
            'valHorasBase'      => $valHorasBase,
            'pagoNominaParcial' => $payrollPayment,
            'valorHorasExtras'  => $valorHorasExtras,
            'subTotal'          => $subTotal,
            'auxTransporte'     => $auxTransport,
            'devengado'         => $devengado,
            'salud'             => $healthValue,
            'pension'           => $pension,
            'netoPagado'        => $netoPagado,
            'fsp'               => $fsp,
            'aportesSalud'      => $aportesSalud,
            'aportesPension'    => $aportesPension,
            'aportesFsp'        => $aportesFsp,
            'totalDectoSegSocial' => $totalDectoSegSocial,
            'cesantias'           => $cesantias,
            'intCes'              => $intCes,
            'prima'               => $prima,
            'vacaciones'          => $vacaciones,
            'cajaCompensacion'    => $cajaCompensacion,
            'icbf'                => $icbf,
            'sena'                => $sena,
            'aportFiscales'       => $aportFiscales,
            'arl'                 => $arl,
            'totalApropiaciones'  => $totalApropiaciones,
            'valorTotalPorTrabajador' => $valorTotalPorTrabajador,
            'idCentroCosto'       => $idCostCenter,
        ]);

        return response()->json([
            'valHorasBase'      => $valHorasBase,
            'pagoNominaParcial' => $payrollPayment,
            'valorHorasExtras'  => $valorHorasExtras,
            'subTotal'          => $subTotal,
            'auxTransporte'     => $auxTransport,
            'devengado'         => $devengado,
            'salud'             => $healthValue,
            'pension'           => $pension,
            'fsp'               => $fsp,
            'netoPagado'        => $netoPagado,
            'aportesSalud'      => $aportesSalud,
            'aportesPension'    => $aportesPension,
            'aportesFsp'        => $aportesFsp,
            'totalDectoSegSocial' => $totalDectoSegSocial,
            'cesantias'           => $cesantias,
            'intCes'              => $intCes,
            'prima'               => $prima,
            'vacaciones'          => $vacaciones,
            'cajaCompensacion'    => $cajaCompensacion,
            'icbf'                => $icbf,
            'sena'                => $sena,
            'aportFiscales'       => $aportFiscales,
            'arl'                 => $arl,
            'totalApropiaciones'  => $totalApropiaciones,
            'valorTotalPorTrabajador' => $valorTotalPorTrabajador,
            'nomina'              => $newPayroll,
        ]);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Nomina  $nomina
     * @return \Illuminate\Http\Response
     */
    public function show(string $id): JsonResponse
    {
        $nomina = Nomina::findOrFail($id);
        return response()->json($nomina);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Nomina  $nomina
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $nomina = Nomina::findOrFail($id);
        $nomina->update($request->all());
        return response()->json($nomina, 200);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Nomina  $nomina
     * @return \Illuminate\Http\Response
     */
    public function destroy(string $id): JsonResponse
    {
        $nomina = Nomina::findOrFail($id);
        $nomina->delete();
        return response()->json(null, 204);
    }

    public function getNominaByContract(string $idContract): JsonResponse
    {
        $nominas = Nomina::where('idContrato', $idContract)->get();
        return response()->json($nominas, 200);
    }



    public function ejecutarNomina(Request $request): JsonResponse
    {
        try {

            $tipoLiquidacion = 'total';

            $resultados = DB::select('CALL ejecutarNomina(?)', [$tipoLiquidacion]);

            return response()->json([
                'success' => true,
                'data' => $resultados
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al ejecutar la nómina',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    public function getLiquidacionesNomina(Request $request): JsonResponse
    {
        $liquidacionNomina = LiquidacionNomina::orderBy('id', 'desc')
            ->get()
            ->map(function ($liq) {

                $totalDevengado = DB::table('nominas')
                    ->where('idLiquidacionNomina', $liq->id)
                    ->sum('devengado');


                $liq->total_devengado = $totalDevengado;

                return $liq;
            });

        return response()->json($liquidacionNomina);
    }



    public function getNominaByLiquidacion($id)
    {
        $nominas = Nomina::query()
            ->with([
                'contrato.persona.usuario.activationCompanyUsers',
                'contrato.pension',
                'contrato.arl',
                'contrato.salud',
                'contrato.cajaCompensacion',
                'contrato.cesantias',
                'contrato.tipoCotizante',
                'contrato.subTipoCotizante',
                'contrato.persona.tipoIdentificacion',
                'contrato.persona.ciudadUbicacion',
                'contrato.novedades',
                'contrato.pension',
                'contrato.arl',
                'contrato.salud',
                'contrato.saludMovilidad',
                'contrato.pensionMovilidad',
                'contrato.cajaCompensacion',
                'contrato.cesantias',
                'contrato.horasExtra'


                => function ($q) {
                    $q->where('estado', 'PENDIENTE');
                },

                'contrato.vacaciones' => function ($q) {
                    $q->whereHas('solicitud', function ($query) {
                        $query->where('estado', 'PENDIENTE');
                    })->with(['solicitud' => function ($query) {
                        $query->where('estado', 'PENDIENTE');
                    }]);
                },

                'contrato.solicitudIncLicPersonas' => function ($q) {
                    $q->where('estado', 'PENDIENTE');
                },
            ])
            ->whereHas('contrato', function ($query) {
                $query->where('idempresa', KeyUtil::idCompany());
            })
            ->where('idLiquidacionNomina', $id)
            ->orderBy('id', 'desc')
            ->get();

        return response()->json($nominas, 200);
    }




    public function getNovedadesByContrato($idContrato)
    {
        $contrato = Contract::query()
            ->with([

                'horasExtra' => function ($q) {
                    $q->where('estado', 'PENDIENTE');
                },


                'solicitudIncLicPersonas' => function ($q) {
                    $q->where('estado', 'PENDIENTE');
                },
            ])
            ->where('id', $idContrato)
            ->first();

        if (!$contrato) {
            return response()->json(['message' => 'Contrato no encontrado'], 404);
        }

        return response()->json([
            'horas_extra' => $contrato->horasExtra,
            'vacaciones' => $contrato->vacaciones,
            'solicitud_inc_lic_personas' => $contrato->solicitudIncLicPersonas,

        ], 200);
    }





    public function getNovedadesAprobadasByContrato($idContrato)
    {
        $contrato = Contract::query()
            ->with([

                'horasExtra' => function ($q) {
                    $q->where('estado', 'LIQUIDADO');
                },

                'novedades' => function ($q) {
                    $q->where('estado', 'LIQUIDADO');
                },


                'solicitudIncLicPersonas' => function ($q) {
                    $q->where('estado', 'LIQUIDADO');
                },
            ])
            ->where('id', $idContrato)
            ->first();

        if (!$contrato) {
            return response()->json(['message' => 'Contrato no encontrado'], 404);
        }

        return response()->json([
            'horas_extra' => $contrato->horasExtra,
            'vacaciones' => $contrato->vacaciones,
            'solicitud_inc_lic_personas' => $contrato->solicitudIncLicPersonas,
            'novedades' => $contrato->novedades,

        ], 200);
    }






    public function ejecutarNominaIndividual(Request $request)
    {
        try {
            $tipoLiquidacion = 'total';
            $idContrato = $request->input('idContrato');
            $idL = $request->input('idL');


            if (!$idContrato || !$idL) {
                return response()->json([
                    'success' => false,
                    'message' => 'Faltan parámetros: idContrato o idLiquidacion'
                ], 400);
            }

            $resultados = DB::transaction(function () use ($tipoLiquidacion, $idContrato, $idL) {

                DB::table('nominas')
                    ->where('idLiquidacionNomina', $idL)
                    ->where('idContrato', $idContrato)
                    ->delete();


                $resultado = DB::select('CALL ejecutarNominaIndividual(?, ?, ?)', [
                    $tipoLiquidacion,
                    $idContrato,
                    $idL
                ]);


                return $resultado;
            });

            return response()->json([
                'success' => true,
                'message' => 'Nómina ejecutada correctamente',
                'data' => $resultados
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al ejecutar la nómina',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    public function deleteAllLiquidaciones()
    {
        Nomina::query()->delete();
        LiquidacionNomina::query()->delete();
    }
}
