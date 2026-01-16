<?php

namespace App\Http\Controllers\gestion_aporte;

use App\Http\Controllers\Controller;
use App\Models\AsignacionFacturaTransaccion;
use App\Models\AsignacionPropietario;
use App\Models\DetalleFactura;
use App\Models\Factura;
use App\Models\Pago;
use App\Models\Status;
use App\Models\Tercero;
use App\Models\TipoFactura;
use App\Models\TipoTransaccion;
use App\Models\Transaccion;
use Carbon\Carbon;
use Illuminate\Http\Request;

class PolizasController extends Controller
{

    public function getAsociadosAdmin()
    {
        $asignaciones = AsignacionPropietario::where('estado', 'ACTIVO')
            ->where('administrador', 'Si')
            ->whereHas('afiliacion', function ($query) {
                $query->where('estado', 'ACTIVO');
            })
            ->with(['propietario', 'afiliacion', 'afiliacion.tipoAfiliacion', 'vehiculo'])
            ->get()
            ->map(function ($asignacion) {
                if ($asignacion->propietario) {
                    $tercero = Tercero::where('identificacion', $asignacion->propietario->identificacion)->first();
                    $asignacion->tercero = $tercero;
                }
                return $asignacion;
            });

        return response()->json($asignaciones);
    }


    public function storeCuentaCobrarPagoPoliza(Request $request)
    {
        $afiliaciones = $request->afiliaciones;
        $valorPoliza = $request->valor;

        $resultados = [];

        foreach ($afiliaciones as $afiliacionData) {
            $idTercero = $afiliacionData['idTercero'];
            $placa = $afiliacionData['placa'] ?? 'Sin placa';
            $tipoAfiliacion = $afiliacionData['tipoAfiliacion'] ?? '';

            $tercero = Tercero::find($idTercero);

            if (!$tercero) {
                $resultados[] = [
                    'idTercero' => $idTercero,
                    'error' => 'Tercero no encontrado'
                ];
                continue;
            }

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
            $factura->valor = $valorPoliza;
            $factura->fecha = Carbon::now();
            $factura->valorIva = 0;
            $factura->valorMasIva = $valorPoliza;
            $factura->idTercero = $tercero->id;
            $factura->idTipoFactura = TipoFactura::VENTA;
            $factura->save();

            $detalleFactura = new DetalleFactura();
            $detalleFactura->idFactura = $factura->id;
            $detalleFactura->detalle = "Concepto por pago de póliza - Vehículo: {$placa} - Tipo: {$tipoAfiliacion}";
            $detalleFactura->valor = $valorPoliza;
            $detalleFactura->save();

            $transaccion = new Transaccion();
            $transaccion->valor = $valorPoliza;
            $transaccion->hora = Carbon::now()->format('H:i');
            $transaccion->fechaTransaccion = Carbon::now();
            $transaccion->tipoCartera = 'CXC';
            $transaccion->idTipoTransaccion = TipoTransaccion::VENTA;
            $transaccion->save();

            $asignacionFacturatransaccion = new AsignacionFacturaTransaccion();
            $asignacionFacturatransaccion->idFactura = $factura->id;
            $asignacionFacturatransaccion->idTransaccion = $transaccion->id;
            $asignacionFacturatransaccion->save();

            $pago = new Pago();
            $pago->fechaPago = Carbon::now();
            $pago->fechaReg = Carbon::now();
            $pago->valor = $valorPoliza;
            $pago->excedente = 0;
            $pago->idEstado = Status::ID_PENDIENTE;
            $pago->idTransaccion = $transaccion->id;
            $pago->save();

            $resultados[] = [
                'idTercero' => $idTercero,
                'terceroNombre' => $tercero->nombre,
                'factura' => $factura->numeroFactura,
                'transaccion' => $transaccion->id,
                'pago' => $pago->id,
                'success' => true
            ];
        }

        return response()->json([
            'message' => 'Cuentas por cobrar generadas exitosamente',
            'resultados' => $resultados
        ], 201);
    }
}
