<?php

namespace App\Jobs;

use App\Models\Company;
use App\Models\Contract;
use App\Models\Nomina\Nomina;
use Illuminate\Bus\Queueable;
use App\Models\Nomina\TarifaArl;
use App\Traits\PayrollOperations;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use App\Models\Nomina\ConfiguracionNomina;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\Log;

class CreateMonthlyPayrollsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, PayrollOperations;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(): void
    {
        $this->calculatePayrolls();
    }

    /**
     * Create payroll for the month
     * @return void
     */
    public function calculatePayrolls(): void
    {
        $companies = Company::all()->keyBy('id');
        $currentDay = Carbon::now()->day;
        $currentHour = Carbon::now()->hour;

        foreach ($companies as $key => $company) {

            $payrollconfiguration = ConfiguracionNomina::where('idEmpresa', $company->id)->first();

            $paymentDay = intval($payrollconfiguration->diaPago);
            if ($currentDay !== $paymentDay) {
                continue;
            }

            if (!($currentHour >= 10 && $currentHour < 12)) {
                continue;
            }            

            $contracts = Contract::with([
                'salario.rol',
                'nominas'
            ])
                ->whereDoesntHave('nominas', function ($query) {
                    $query->whereMonth('created_at', Carbon::now()->month)
                        ->whereYear('created_at', Carbon::now()->year);
                })
                ->where('idEstado', 1)
                ->where('idempresa', $company->id)
                ->get();

            foreach ($contracts as $key => $contract) {

                $salario = $contract->salario;

                $payroll = Nomina::latest('id')
                    ->where('idContrato', $contract->id)
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

                Nomina::create([
                    'idContrato'        => $contract->id,
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
                ]);
            }
        }
    }
}
