<?php

namespace App\Console\Commands;

use App\Models\AsignacionFacturaTransaccion;
use App\Models\AsignacionPropietario;
use App\Models\Company;
use App\Models\DetalleFactura;
use App\Models\Factura;
use App\Models\Pago;
use App\Models\Status;
use App\Models\Tercero;
use App\Models\TipoFactura;
use App\Models\TipoTransaccion;
use App\Models\Transaccion;
use Carbon\Carbon;
use Illuminate\Console\Command;

class StorePagoAdministracion extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'store:pago-administracion';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {

        $company = Company::findOrFail(1);

        $diaCorte = $company->diaCorteAdministracion;
        $diaActual = Carbon::now()->day;

        if ($diaActual != $diaCorte) {
            $this->info("Hoy no es día de corte. Día de corte: {$diaCorte}, Día actual: {$diaActual}");
            return Command::SUCCESS;
        }

        $this->info("Procesando pagos de administración para el día de corte: {$diaCorte}");

        $propietarios = AsignacionPropietario::with('afiliacion', 'propietario', 'vehiculo')
            ->where('administrador', 'Si')
            ->get();

        $valorAdministracion = $company->valorAdministracion;

        foreach ($propietarios as $asignacion) {
            $identificacion = $asignacion->propietario->identificacion;

            $tercero = Tercero::where('identificacion', $identificacion)->first();



            $factura = new Factura();

            $lastFactura = Factura::where('idTipoFactura', TipoFactura::VENTA)
                ->orderBy('id', 'desc')
                ->first();

            if ($lastFactura) {
                $nextNumFactura = str_pad(intval($lastFactura->numeroFactura) + 1, 5, '0', STR_PAD_LEFT);
            } else {
                $nextNumFactura = '00001';
            }
            $factura->numeroFactura = $nextNumFactura;

            $factura->valor = $valorAdministracion;
            $factura->fecha = Carbon::now();
            $factura->valorIva = 0;
            $factura->valorMasIva = $valorAdministracion;
            $factura->idTercero = $tercero->id;
            $factura->idTipoFactura = TipoFactura::VENTA;
            $factura->save();


            $detalleFactura = new DetalleFactura();
            $detalleFactura->idFactura = $factura->id;
            $placa = $asignacion->vehiculo->placa ?? 'Sin placa';
            $detalleFactura->detalle = "Concepto por pago de administración - Vehículo: {$placa}";
            $detalleFactura->valor = $company->valorAfiliacion;
            $detalleFactura->save();



            $transaccion = new Transaccion();
            $transaccion->valor = $valorAdministracion;
            $transaccion->hora = Carbon::now()->format('H:i');
            $transaccion->fechaTransaccion = Carbon::now();
            $transaccion->tipoCartera = 'CXC';
            $transaccion->idTipoTransaccion = TipoTransaccion::AFILIACION; //preguntar que tipo es
            $transaccion->save();

            $asignacionFacturatransaccion = new AsignacionFacturaTransaccion();
            $asignacionFacturatransaccion->idFactura = $factura->id;
            $asignacionFacturatransaccion->idTransaccion = $transaccion->id;
            $asignacionFacturatransaccion->save();

            $pago = new Pago();
            $pago->fechaPago = Carbon::now();
            $pago->fechaReg = Carbon::now();
            $pago->valor = $valorAdministracion;
            $pago->excedente = $transaccion->excedente;
            $pago->idEstado = Status::ID_PENDIENTE;
            $pago->idTransaccion = $transaccion->id;
            $pago->save();

            if ($tercero) {
                $this->info("Propietario: {$asignacion->propietario->nombre1} {$asignacion->propietario->apellido1}");
                $this->info("Identificación: {$identificacion}");
                $this->info("Tercero encontrado - ID: {$tercero->id}, Nombre: {$tercero->nombre}");
                $this->info("---");
            } else {
                $this->warn("No se encontró tercero para la identificación: {$identificacion}");
            }
        }
    }
}
