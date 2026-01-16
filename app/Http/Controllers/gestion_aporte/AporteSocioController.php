<?php

namespace App\Http\Controllers\gestion_aporte;

use App\Http\Controllers\Controller;
use App\Models\AporteSocio;
use App\Models\AsignacionDetalleAporte;
use App\Models\DetalleFactura;
use App\Models\MedioPago;
use App\Models\Pago;
use App\Models\Producto;
use App\Models\Status;
use App\Models\TipoPago;
use App\Models\TipoTransaccion;
use App\Models\Transaccion;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AporteSocioController extends Controller
{



    public function storeAporte(Request $request)
    {
        DB::beginTransaction();

        try {
            $tipoAporte = $request->input('tipoAporte'); 
            $idTercero = $request->input('idTercero');
            $valor = $request->input('valor');
            $fecha = $request->input('fecha');
            $numCuotas = $request->input('numCuotas');
            $claseAporte = $request->input('claseAporte');
            $tipoPago = $request->input('tipoPago');

         
            $transaccion = new Transaccion();
            $transaccion->fechaTransaccion = Carbon::now()->toDateString();
            $transaccion->hora = Carbon::now()->format('H:i');
            $transaccion->valor = $valor;
            $transaccion->idTipoTransaccion = TipoTransaccion::APORTE;
            $transaccion->idTipoPago = ($tipoAporte === 'DINERO') ? TipoPago::CONTADO : TipoPago::ESPECIE;
            $transaccion->idEstado = Status::ID_ACTIVE;
            $transaccion->save();

         
            if ($tipoAporte === 'ESPECIE' && $request->has('productos')) {
                foreach ($request->input('productos') as $index => $productoData) {
                    $producto = new Producto();
                    $producto->serial = $productoData['serial'] ?? null;
                    $producto->modelo = $productoData['modelo'] ?? null;
                    $producto->caracteristicas = $productoData['caracteristicas'] ?? null;
                    $producto->idTipoProducto = $productoData['idTipoProducto'] ?? null;

                    if ($request->hasFile("productos.{$index}.archivo")) {
                        $archivo = $request->file("productos.{$index}.archivo");
                        $producto->urlAdicional = $this->storeArchivo($archivo, Producto::RUTA_PRODUCTO);
                    } else {
                        $producto->urlAdicional = Producto::RUTA_PRODUCTO_DEFAULT;
                    }

                    $producto->save();

                    $detalleAporte = new AsignacionDetalleAporte();
                    $detalleAporte->idTransaccion = $transaccion->id;
                    $detalleAporte->idProducto = $producto->id;
                    $detalleAporte->valorProducto = $productoData['valor'] ?? 0;
                    $detalleAporte->save();
                }
            }

         
            $pago = new Pago();
            if ($tipoAporte === 'DINERO' && $request->hasFile('rutaComprobanteFile')) {
                $pago->rutaComprobante = $this->storeComprobante($request, Pago::RUTA_COMPROBANTE);
            } else {
                $pago->rutaComprobante = Pago::RUTA_COMPROBANTE_DEFAULT;
            }

            $pago->fechaPago = $fecha;
            $pago->valor = $valor;
            $pago->idTransaccion = $transaccion->id;
            $pago->idEstado = Status::ID_APROBADO;
            $pago->idMedioPago = MedioPago::ESPECIE;
            $pago->fechaReg = $fecha;
            $pago->save();

          
            $aporteSocio = new AporteSocio();
            $aporteSocio->idSocio = $idTercero;
            $aporteSocio->idTransaccion = $pago->idTransaccion;
            $aporteSocio->fecha = Carbon::now()->toDateString();
            $aporteSocio->numCuotas = $numCuotas;
            $aporteSocio->tipoAporte = $tipoAporte;
            $aporteSocio->claseAporte = $claseAporte;
            $aporteSocio->tipoPago = $tipoPago;
            $aporteSocio->documentoAdicional = $this->storeDocumentoAdicional(
                $request
            );
            $aporteSocio->save();

            DB::commit();

            return response()->json([
                'aporteSocio' => $aporteSocio,
                'productos' => $request->input('productos', [])
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }


    private function storeDocumentoAdicional(Request $request, $default = true)
    {
        $rutaDocumento = null;

        if ($default) {
            $rutaDocumento = AporteSocio::RUTA_DOCUMENTO_DEFAULT;
        }
        if ($request->hasFile('rutaDocumentoFile')) {
            $rutaDocumento =
                '/storage/' .
                $request
                ->file('rutaDocumentoFile')
                ->store(AporteSocio::RUTA_DOCUMENTO, ['disk' => 'public']);
        }
        return $rutaDocumento;
    }


    private function storeComprobante(Request $request, $default = true)
    {
        $rutaComprobante = null;

        if ($default) {
            $rutaComprobante = Pago::RUTA_COMPROBANTE_DEFAULT;
        }
        if ($request->hasFile('rutaComprobanteFile')) {
            $rutaComprobante =
                '/storage/' .
                $request
                ->file('rutaComprobanteFile')
                ->store(Pago::RUTA_COMPROBANTE, ['disk' => 'public']);
        }
        return $rutaComprobante;
    }
}
