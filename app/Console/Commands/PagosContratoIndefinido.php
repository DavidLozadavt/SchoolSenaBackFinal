<?php

namespace App\Console\Commands;

use App\Models\Contract;
use App\Models\ContratoTransaccion;
use App\Models\Pago;
use App\Models\Status;
use Carbon\Carbon;
use Illuminate\Console\Command;

class PagosContratoIndefinido extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'pagos:indefinidos';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Crea pagos mensuales para el tipo de contrato indefinido';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */

    public function handle()
    {
        $contratosIndefinidos = Contract::where('idtipoContrato', 6)->get();

        foreach ($contratosIndefinidos as $contrato) {
            $asignacion = ContratoTransaccion::where('contrato_id', $contrato->id)->first();

            if (!$asignacion) {
                $this->info('No se encontró asignación de transacción para el contrato con ID: ' . $contrato->id);
                continue;
            }

            $transaccion = $asignacion->transaccion;

            if (!$transaccion) {
                $this->info('No se encontró transacción para el contrato con ID: ' . $contrato->id);
                continue;
            }

            $pagos = Pago::where('idTransaccion', $transaccion->id)
                ->whereYear('fechaPago', Carbon::now()->year)
                ->whereMonth('fechaPago', Carbon::now()->month)
                ->orderBy('fechaPago', 'asc') 
                ->get();

            if (!$pagos->isEmpty()) {
                $this->info('Ya existe al menos un pago para la transacción con ID: ' . $transaccion->id . ' en el mes actual.');
                continue;
            }

            $primerPago = Pago::where('idTransaccion', $transaccion->id)
                ->orderBy('fechaPago', 'asc') 
                ->first();

            if (!$primerPago) {
                $this->info('No se encontró un pago para la transacción con ID: ' . $transaccion->id);
                continue;
            }

            $fechaPago = Carbon::now();

            if ($fechaPago->month == 2) {
            
                $fechaPago->setDay(28);
            } else {
              
                $fechaPago->day(30);
            }

            $nuevoPago = new Pago();
            $nuevoPago->valor = $primerPago->valor;
            $nuevoPago->fechaPago = $fechaPago;
            $nuevoPago->idEstado = 4;
            $nuevoPago->idTransaccion = $transaccion->id;

            $nuevoPago->save();

            $transaccion->valor += $nuevoPago->valor;
            $transaccion->save();

            $this->info('Nuevo pago creado con ID: ' . $nuevoPago->id);

        }

        $this->info('Comando ejecutado correctamente.');
    }
}
