<?php

namespace App\Http\Controllers;

use App\Models\AsignacionFacturaTransaccion;
use App\Models\Caja;
use App\Models\DetalleFactura;
use App\Models\Factura;
use App\Models\MedioPago;
use App\Models\Pago;
use App\Models\PrestacionServicio;
use App\Models\Status;
use App\Models\TipoFactura;
use App\Models\TipoPago;
use App\Models\TipoTransaccion;
use App\Models\Transaccion;
use App\Models\User;
use App\Util\KeyUtil;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class CajaController extends Controller
{
    public function obtenerDatosCajaActual()
    {
        $caja = Caja::where('idEstado', 17)->latest()->first();
        if (!$caja) {
            return response()->json([
                'message' => 'No se encontró ninguna caja abierta.'
            ], 404);
        }
        return response()->json([
            'message' => 'Datos de la caja obtenidos exitosamente.',
            'caja' => $caja
        ], 200);
    }



    public function abrirCaja(Request $request, $idPuntoVenta)
    {

        $userId = KeyUtil::user()->id;

        $ultimaCaja = Caja::where('idUsuario', $userId)
            ->orderByDesc('fecha')
            ->first();

        if ($ultimaCaja && $ultimaCaja->idEstado == 17) {
            return response()->json([
                'message' => 'No puedes abrir una nueva caja. Ya tienes una caja abierta en otro punto de venta.'
            ], 403);
        }

        $caja = new Caja();
        $caja->fecha = Carbon::now();
        $caja->valorEfectivo = $request->input('valorEfectivo', 0);
        $caja->valorGasto = $request->input('valorGasto', 0);
        $caja->valorTransaccion = $request->input('valorTransaccion', 0);
        $caja->observacion = $request->observacion;
        $caja->exedente = $request->exedente;
        $caja->idUsuario = $userId;
        $caja->idEstado = 17;
        $caja->idPuntoDeVenta = $idPuntoVenta;


        $caja->save();

        return response()->json([
            'message' => 'Caja abierta exitosamente.',
            'caja' => $caja
        ], 201);
    }

    public function cerrarCaja(Request $request, $idPuntoVenta)
    {
        $caja = new Caja();
        $caja->fecha = Carbon::now();
        $caja->valorEfectivo = $request->valorCaja ?? 0;
        $caja->valorGasto = $request->valorGasto ?? 0;
        $caja->valorTransaccion = $request->valorTransaccion ?? 0;
        $caja->idUsuario = KeyUtil::user()->id;
        $caja->observacion = $request->observacion;
        $caja->exedente = $request->exedente;
        $caja->idEstado = 8;
        $caja->idPuntoDeVenta = $idPuntoVenta;
        $caja->save();
        return response()->json([
            'message' => 'Caja cerrada exitosamente.',
            'caja' => $caja
        ], 200);
    }



    public function verificarUsuarioCaja($idPuntoVenta)
    {
        $latestFecha = Caja::where('idPuntoDeVenta', $idPuntoVenta)
            ->max('fecha');

        $cajaAbierta = Caja::where('idPuntoDeVenta', $idPuntoVenta)
            ->where('fecha', $latestFecha)
            ->where('idEstado', 17)
            ->first();

        if ($cajaAbierta) {
            $esPropietario = $cajaAbierta->idUsuario === auth()->user()->id;
            return response()->json(['esPropietario' => $esPropietario, 'caja' => $cajaAbierta]);
        }

        return response()->json(['esPropietario' => false, 'caja' => null]);
    }


    public function getUsuariosPorPuntoDeVenta()
    {
        $idsUsuarios = DB::table('caja')->pluck('idUsuario')->unique();

        $usuarios = User::whereIn('id', $idsUsuarios)
            ->with('persona')
            ->get();

        return response()->json($usuarios);
    }




    public function getTransaccionesTranferencias($idCaja)
    {
        $transacciones = Transaccion::with(['pago' => function ($query) {
            $query->where('idMedioPago', 4);
        }])
            ->where('idCaja', $idCaja)
            ->where('idTipoTransaccion', 1)
            ->whereHas('pago', function ($query) {
                $query->where('idMedioPago', 4);
            })
            ->get();

        return response()->json($transacciones);
    }




    public function getTransaccionesEfectivo($idCaja)
    {
        $transacciones = Transaccion::with(['pago' => function ($query) {
            $query->where('idMedioPago', 1);
        }])
            ->where('idCaja', $idCaja)
            ->where('idTipoTransaccion', 1)
            ->whereHas('pago', function ($query) {
                $query->where('idMedioPago', 1);
            })
            ->get();



        return response()->json($transacciones);
    }



    public function getTransaccionesGastos($idCaja)
    {
        $transacciones = Transaccion::with('pago', 'facturas')
            ->where('idCaja', $idCaja)
            ->where('idTipoTransaccion', 9)
            ->get();



        return response()->json($transacciones);
    }

    public function storeGasto(Request $request)
    {
        $idUser = auth()->user()->id;

        $productos = json_decode($request->input('productos'), true);

        $total = collect($productos)->sum('valor');

        $factura = new Factura();
        $lastFactura = Factura::where('idTipoFactura', TipoFactura::COMPRA)
            ->orderBy('id', 'desc')
            ->first();

        if ($lastFactura) {
            $nextNumFactura = str_pad(intval($lastFactura->numeroFactura) + 1, 5, '0', STR_PAD_LEFT);
        } else {
            $nextNumFactura = '00001';
        }

        $factura->numeroFactura = $nextNumFactura;
        $factura->fecha = Carbon::now()->toDateString();

        $factura->valor = $total;
        $factura->valorMasIva = $total;
        $factura->valorIva = 0;
        $factura->fotoFactura = $this->storeFotoFactura($request, false);
        $factura->idCompany = KeyUtil::idCompany();
        $factura->idUser = $idUser;
        $factura->idTipoFactura = TipoFactura::COMPRA;
        $factura->save();


        foreach ($productos as $producto) {
            $detalle = new DetalleFactura();
            $detalle->detalle = $producto['detalle'];
            $detalle->valor = $producto['valor'];
            $detalle->idFactura = $factura->id;
            $detalle->save();
        }

        $transaccion = new Transaccion();
        $transaccion->fechaTransaccion = Carbon::now();
        $transaccion->hora = Carbon::now()->format('H:i');
        $transaccion->valor = $total;
        $transaccion->excedente = 0;
        $transaccion->idTipoPago = TipoPago::CONTADO;
        $transaccion->idTipoTransaccion = TipoTransaccion::GASTOS;
        $transaccion->idEstado = Status::ID_APROBADO;
        $transaccion->idCaja = $request->input('idCaja');
        $transaccion->save();



        $pago = new Pago();
        $pago->fechaPago =  Carbon::now()->toDateString();
        $pago->fechaReg =  Carbon::now()->toDateString();
        $pago->valor = $total;
        $pago->excedente = 0;
        $pago->idEstado = Status::ID_APROBADO;
        $pago->idTransaccion = $transaccion->id;
        $pago->idMedioPago = MedioPago::EFECTIVO;
        $pago->save();

        $asignacionFacturatransaccion = new AsignacionFacturaTransaccion();
        $asignacionFacturatransaccion->idFactura = $factura->id;
        $asignacionFacturatransaccion->idTransaccion = $transaccion->id;
        $asignacionFacturatransaccion->save();


        return response()->json(['message' => 'Factura y detalles registrados correctamente.']);
    }


    private function storeFotoFactura(Request $request, $default = true)
    {
        $rutaFactura = null;

        if ($default) {
            $rutaFactura = Factura::RUTA_FACTURA_DEFAULT;
        }
        if ($request->hasFile('rutaFacturaFile')) {
            $rutaFactura =
                '/storage/' .
                $request
                ->file('rutaFacturaFile')
                ->store(Factura::RUTA_FACTURA, ['disk' => 'public']);
        }
        return $rutaFactura;
    }




    public function getTransaccionCaja($idCaja)
    {
        $transacciones = Transaccion::with('pago', 'facturas')->where('idCaja', $idCaja)->get();

        if ($transacciones->isEmpty()) {
            return response()->json([
                'message' => 'No se encontró ninguna transacción para esta caja.'
            ], 404);
        }

        $sumaMedioPago1 = 0;
        $sumaMedioPago4 = 0;
        $sumaPagosTipoTransaccion9 = 0;
        $sumaPagoPropina = 0;

        foreach ($transacciones as $transaccion) {
            if ($transaccion->idTipoTransaccion == 1) {
                foreach ($transaccion->pago as $pago) {
                    if ($pago->idMedioPago == 1) {
                        $sumaMedioPago1 += $pago->valor;
                    } elseif ($pago->idMedioPago == 4) {
                        $sumaMedioPago4 += $pago->valor;
                    }
                }
            }

            if ($transaccion->idTipoTransaccion == 9) {
                foreach ($transaccion->pago as $pago) {
                    $sumaPagosTipoTransaccion9 += $pago->valor;
                }
            }


            foreach ($transaccion->facturas as $factura) {
                $prestaciones = PrestacionServicio::where('idFactura', $factura->id)->get();
                foreach ($prestaciones as $prestacion) {
                    $sumaPagoPropina += $prestacion->pagoPropina;
                }
            }
        }

        return response()->json([
            'transacciones' => $transacciones,
            'suma_medio_pago_1' => $sumaMedioPago1,
            'suma_medio_pago_4' => $sumaMedioPago4,
            'suma_pagos_tipo_transaccion_9' => $sumaPagosTipoTransaccion9,
            'suma_pago_propina' => $sumaPagoPropina,
        ]);
    }
}
