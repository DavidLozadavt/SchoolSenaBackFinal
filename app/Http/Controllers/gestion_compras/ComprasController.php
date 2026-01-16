<?php

namespace App\Http\Controllers\gestion_compras;

use App\Http\Controllers\Controller;
use App\Models\AgregarPagoCuenta;
use App\Models\AsignacionFacturaTransaccion;
use App\Models\AsignacionTransaccionPago;
use App\Models\Clase;
use App\Models\ClaseProducto;
use App\Models\Cuenta;
use App\Models\DetalleFactura;
use App\Models\Factura;
use App\Models\Grupo;
use App\Models\Pago;
use App\Models\Producto;
use App\Models\Status;
use App\Models\SubCuenta;
use App\Models\SubCuentaPropia;
use App\Models\Tercero;
use App\Models\TipoPago;
use App\Models\TipoProducto;
use App\Models\TipoTercero;
use App\Models\TipoTransaccion;
use App\Models\Transaccion;
use App\Models\TipoFactura;
use App\Models\HistorialPrecio;
use App\Models\Nomina\Sede;
use App\Models\Almacen;
use App\Models\DistribucionProducto;

use App\Util\KeyUtil;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Validator;

class ComprasController extends Controller
{
    public function claseProdcutos()
    {
        $claseProductos = ClaseProducto::all();
        return response()->json($claseProductos);
    }


    public function tipoProductos()
    {
        $tipoProductos = TipoProducto::all();
        return response()->json($tipoProductos);
    }


    public function getProveedorByNiT($nit)
    {
        $proveedor = Tercero::where('identificacion', $nit)->first();

        if (!$proveedor) {
            return response()->json(['error' => 'Proveedor no encontrado. Por favor, asegúrate de que el nit sea correcto o crea uno nuevo.'], 404);
        }

        return response()->json($proveedor);
    }


    public function storeProveedor(Request $request)
    {

        DB::beginTransaction();

        try {
            $proveedor = new Tercero();
            $proveedor->nombre = $request->input('nombre');
            $proveedor->identificacion = $request->input('identificacion');
            $proveedor->email = $request->input('email');
            $proveedor->direccion = $request->input('direccion');
            $proveedor->telefono = $request->input('telefono');
            $proveedor->digitoVerficacion = $request->input('digitoVerficacion');
            $proveedor->responsableIva = empty($request->input('responsableIva')) ? 0 : $request->input('responsableIva');
            $proveedor->retenciones = empty($request->input('retenciones')) ? 0 : $request->input('retenciones');
            $proveedor->idCompany = Session::get('company_id');
            $proveedor->idTipoTercero = TipoTercero::PROVEEDOR;
            $proveedor->save();

            DB::commit();

            return response()->json($proveedor, 201);
        } catch (\Exception $e) {

            DB::rollBack();

            return response()->json(['error' => 'Error interno del servidor.'], 500);
        }
    }


    public function storeProducto(Request $request)
    {
        DB::beginTransaction();

        try {
            $productos = $request->input('productos');
            $productosCreados = [];

            foreach ($productos as $index => $productoData) {
                $producto = isset($productoData['idProducto']) ? Producto::find($productoData['idProducto']) : null;

                if (!$producto) {
                    $producto = new Producto();
                    $producto->caracteristicas = $productoData['caracteristicas'];
                    $producto->idTipoProducto = $productoData['idTipoProducto'];
                    $producto->modelo = $productoData['modelo'];
                    $producto->serial = $productoData['serial'];
                    $producto->save();
                }

                $producto->load('tipoProducto');

                $detalleFactura = new DetalleFactura();
                $detalleFactura->idProducto = $producto->id;
                $detalleFactura->idFactura = $request->input('idFactura');
                $detalleFactura->valor = $productoData['valor'];
                $detalleFactura->save();

                $productosCreados[] = [
                    'producto' => $producto,
                    'detalleFactura' => $detalleFactura
                ];
            }

            DB::commit();
            return response()->json(['productosCreados' => $productosCreados], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Error interno del servidor.'], 500);
        }
    }








    public function storeFactura(Request $request)
    {

        DB::beginTransaction();

        try {

            $idUser = auth()->user()->id;

            $factura = new Factura();
            $factura->numeroFactura = $request->input('numeroFactura');
            $factura->fecha = $request->input('fecha');
            $factura->valorIva = $request->input('valorIva');
            $factura->valor = $request->input('valor');
            $factura->valorMasIva = $request->input('valorMasIva');
            $factura->idTercero = $request->input('idTercero');
            $factura->idCompany = KeyUtil::idCompany();
            $factura->idTipoFactura = TipoFactura::COMPRA;
            $factura->idUser = $idUser;
            $factura->save();

            DB::commit();

            return response()->json($factura, 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json(['error' => 'Error interno del servidor.'], 500);
        }
    }



    public function storeFormPagoFactura(Request $request)
    {
        $idFactura = $request->input('idFactura');
        $idTipoPago = $request->input('idTipoPago');

        DB::beginTransaction();

        try {
            $factura = Factura::find($idFactura);

            $factura->fotoFactura = $this->storeFotoFactura($request);
            $factura->reciboPagoFactura = $this->storeComprobanteFactura($request);


            if (!$factura) {
                return response()->json(['error' => 'Factura no encontrada.'], 404);
            }
            if ($idTipoPago == TipoPago::CONTADO) {
                $result = $this->storeOpcionContado($request);
                $transaccion = $result['transaccion'];
                $pagos = $result['pagos'];
            } elseif ($idTipoPago == TipoPago::CREDITO) {
                $result = $this->storoOpcionCredito($request);
                $transaccion = $result['transaccion'];
                $pagos = $result['pagos'];
            }

            $factura->save();


            $asignacionFacturatransaccion = new   AsignacionFacturaTransaccion();
            $asignacionFacturatransaccion->idFactura = $idFactura;
            $asignacionFacturatransaccion->idTransaccion = $transaccion->id;
            $asignacionFacturatransaccion->save();

            DB::commit();

            return response()->json(['factura' => $factura, 'transaccion' => $transaccion, 'pagos' => $pagos], 200);
        } catch (\Exception $e) {

            DB::rollBack();
            return response()->json(['error' => 'Error interno del servidor.'], 500);
        }
    }



    public function storeOpcionContado(Request $request)
    {
        $valoresProductos = json_decode($request->input('valoresProductos'), true);
        $idTercero = $request->input('idTercero');
        $ivaSi = $request->input('valorIva');

        $transaccion = new Transaccion();
        $transaccion->fechaTransaccion = $request->input('fecha');
        $transaccion->hora = Carbon::now()->format('H:i');
        $transaccion->valor = $request->input('valor');
        $transaccion->excedente = 0;
        $transaccion->idTipoPago = TipoPago::CONTADO;
        $transaccion->idEstado = Status::ID_APROBADO;
        $transaccion->save();

        $pagos = [];

        foreach ($valoresProductos as $key => $producto) {
            $valorProducto = $producto['valor'];
            $idSubcuentaPropia = $producto['idSubcuentaPropia'];

            if ($ivaSi == 0.00) {
                $nuevoValorProducto = $valorProducto;
            } else {
                $nuevoValorProducto = $valorProducto * 1.19;
            }

            $pago = new Pago();
            $pago->idMedioPago = $request->input('idMedioPago');
            $pago->fechaPago = $request->input('fecha');
            $pago->fechaReg = $request->input('fecha');
            $pago->valor = $nuevoValorProducto;
            $pago->excedente = 0;
            $pago->idTransaccion = $transaccion->id;
            $pago->rutaComprobante = $this->storeComprobante($request);
            $pago->idEstado = Status::ID_APROBADO;
            $pago->save();

            $pagoCuenta = new AgregarPagoCuenta();
            $pagoCuenta->idSubcuentaPropia = $idSubcuentaPropia;
            $pagoCuenta->naturaleza = AgregarPagoCuenta::DEBITO;
            $pagoCuenta->idTercero = $idTercero;
            $pagoCuenta->idPago = $pago->id;
            $pagoCuenta->save();


            $pagoCuentaBancaria = new AgregarPagoCuenta();
            $pagoCuentaBancaria->idSubcuentaPropia = SubCuentaPropia::BANCOS_NACIONALES;
            $pagoCuentaBancaria->naturaleza = AgregarPagoCuenta::CREDITO;
            $pagoCuentaBancaria->idPago = $pago->id;
            $pagoCuentaBancaria->save();

            $pagos[] = $pago;
        }

        return ['transaccion' => $transaccion, 'pagos' => $pagos];
    }




    private function storoOpcionCredito($request)
    {
        $opcionAbono = $request->input('opcionAbono');
        $valorAbono = $request->input('valorAbono');
        $ivaSi = $request->input('valorIva');
        $idTercero = $request->input('idTercero');
        $valoresProductos = json_decode($request->input('valoresProductos'), true);

        $applyIVA = $ivaSi != 0;


        $valoresProductosConIVA = array_map(function ($producto) use ($applyIVA) {
            $valor = $producto['valor'];
            if ($applyIVA) {
                $valor *= 1.19;
            }
            return [
                'valor' => $valor,
                'idSubcuentaPropia' => $producto['idSubcuentaPropia']
            ];
        }, $valoresProductos);

        $transaccion = new Transaccion();
        $transaccion->fechaTransaccion = $request->input('fecha');
        $transaccion->hora = Carbon::now()->format('H:i');
        $transaccion->valor = $request->input('valor');
        $transaccion->excedente = 0;
        $transaccion->idTipoPago = TipoPago::CREDITO;
        $transaccion->idEstado = Status::ID_PENDIENTE;
        $transaccion->save();

        $pagos = [];

        foreach ($valoresProductosConIVA as $producto) {
            $valorProducto = $producto['valor'];
            $idSubcuentaPropia = $producto['idSubcuentaPropia'];

            if ($opcionAbono === 'si') {
                $excedentePago = 0;
                $valorPago = min($valorAbono, $valorProducto);

                if ($valorPago < $valorProducto) {
                    $excedentePago = $valorProducto - $valorPago;
                }

                $pago = new Pago();
                $pago->idMedioPago = $request->input('idMedioPago');
                $pago->fechaPago = $request->input('fecha');
                $pago->fechaReg = $request->input('fecha');
                $pago->valor = $valorPago;
                $pago->excedente = $excedentePago;
                $pago->idTransaccion = $transaccion->id;
                $pago->rutaComprobante = $this->storeComprobante($request);
                $pago->idEstado = $valorPago >= $valorProducto ? Status::ID_APROBADO : Status::ID_PENDIENTE;
                $pago->save();

                $valorAbono -= $valorPago;



                $pagoCuenta = new AgregarPagoCuenta();
                $pagoCuenta->idSubcuentaPropia = $idSubcuentaPropia;
                $pagoCuenta->naturaleza = AgregarPagoCuenta::DEBITO;
                $pagoCuenta->idTercero = $idTercero;
                $pagoCuenta->idPago = $pago->id;
                $pagoCuenta->save();



                $pagoCuentaBancaria = new AgregarPagoCuenta();
                $pagoCuentaBancaria->idSubcuentaPropia = SubCuentaPropia::BANCOS_NACIONALES;
                $pagoCuentaBancaria->naturaleza = AgregarPagoCuenta::CREDITO;
                $pagoCuentaBancaria->idPago = $pago->id;
                $pagoCuentaBancaria->save();



                if ($excedentePago > 0) {
                    $pagoCuentaProveedor = new AgregarPagoCuenta();
                    $pagoCuentaProveedor->idSubcuentaPropia = SubCuentaPropia::PROVEEDORES_NACIONALES;
                    $pagoCuentaProveedor->naturaleza = AgregarPagoCuenta::CREDITO;
                    $pagoCuentaProveedor->idPago = $pago->id;
                    $pagoCuentaProveedor->save();
                }

                $pagos[] = $pago;
            } elseif ($opcionAbono === 'no') {


                $pago = new Pago();
                $pago->idMedioPago = $request->input('idMedioPago');
                $pago->fechaPago = $request->input('fecha');
                $pago->fechaReg = $request->input('fecha');
                $pago->valor = 0;
                $pago->excedente = $valorProducto;
                $pago->idTransaccion = $transaccion->id;
                $pago->rutaComprobante = $this->storeComprobante($request);
                $pago->idEstado = Status::ID_PENDIENTE;
                $pago->save();


                $pagoCuenta = new AgregarPagoCuenta();
                $pagoCuenta->idSubcuentaPropia = $idSubcuentaPropia;
                $pagoCuenta->naturaleza = AgregarPagoCuenta::DEBITO;
                $pagoCuenta->idTercero = $idTercero;
                $pagoCuenta->idPago = $pago->id;
                $pagoCuenta->save();

                $pagoCuentaProveedor = new AgregarPagoCuenta();
                $pagoCuentaProveedor->idSubcuentaPropia = SubCuentaPropia::PROVEEDORES_NACIONALES;
                $pagoCuentaProveedor->naturaleza = AgregarPagoCuenta::CREDITO;
                $pagoCuentaProveedor->idPago = $pago->id;
                $pagoCuentaProveedor->save();

                $pagos[] = $pago;
            }
        }

        return ['transaccion' => $transaccion, 'pagos' => $pagos];
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



    private function storeComprobanteFactura(Request $request, $default = true)
    {
        $rutaFactura = null;

        if ($default) {
            $rutaFactura = Factura::RUTA_FACTURA_DEFAULT;
        }
        if ($request->hasFile('rutaComprobanteFile')) {
            $rutaFactura =
                '/storage/' .
                $request
                ->file('rutaComprobanteFile')
                ->store(Factura::RUTA_FACTURA_RECIBO_PAGO, ['disk' => 'public']);
        }
        return $rutaFactura;
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


    public function clases()
    {
        $clases = Clase::all();

        return response()->json($clases);
    }


    public function getGrupos($id)
    {
        $grupos = Grupo::where('clase_id', $id)->get();

        return response()->json($grupos);
    }


    public function getCuentas($id)
    {
        $cuenta = Cuenta::where('grupo_id', $id)->get();

        return response()->json($cuenta);
    }


    public function getSubCuentas($id)
    {
        $subCuentas = SubCuenta::where('cuenta_id', $id)->get();

        return response()->json($subCuentas);
    }


    public function storeSubCuentaPropia(Request $request)
    {
        try {
            DB::beginTransaction();

            $subcuenta_id = $request->input('subcuenta_id');

            $cuenta_id = $request->input('cuentas');
            $nombreSubcuentaPropia = $request->input('nombreSubcuentaPropia');
            $codigoBase = $request->input('codigo');

            $codigosExistentes = SubCuentaPropia::where('codigo', 'like', "{$codigoBase}%")

                ->pluck('codigo')
                ->map(function ($codigo) use ($codigoBase) {
                    return (int) substr($codigo, strlen($codigoBase));
                })
                ->toArray();

            $siguienteNumero = empty($codigosExistentes) ? 1 : (max($codigosExistentes) + 1);

            $codigoConcatenado = $codigoBase . str_pad($siguienteNumero, 2, '0', STR_PAD_LEFT);

            $subCuenta = new SubCuentaPropia();
            $subCuenta->subcuenta_id = $subcuenta_id;
            $subCuenta->cuenta_id = $cuenta_id;

            $subCuenta->nombreSubcuentaPropia = $nombreSubcuentaPropia;
            $subCuenta->codigo = $codigoConcatenado;
            $subCuenta->save();

            DB::commit();

            return response()->json($subCuenta);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }


    public function getSubCuentasPropias()
    {

        $subCuentasPropias = SubCuentaPropia::with('subCuenta.cuenta.grupo.clase')->get();
        return response()->json($subCuentasPropias);
    }


    public function deleteSubcuentaPropia($id)
    {
        try {
            $subCuentasPropia = SubCuentaPropia::findOrFail($id);
            $subCuentasPropia->delete();

            return response()->json(null, 204);
        } catch (QueryException $e) {
            if ($e->getCode() == "23000") {
                return response()->json([
                    'message' => 'No se puede eliminar porque tiene una asignación asociada.'
                ], 400);
            }

            throw $e;
        }
    }


    public function updateSubCuentaPropia(Request $request, $id)
    {
        try {
            DB::beginTransaction();

            $subCuenta = SubCuentaPropia::findOrFail($id);

            $subCuenta->nombreSubcuentaPropia = $request->input('nombreSubcuentaPropia');
            $subCuenta->save();

            DB::commit();

            return response()->json($subCuenta);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }




    public function getSubCuentaPropiasByCode(Request $request)
    {
        $codigo = $request->input('codigo');

        $codigoSubstring = substr($codigo, 0, 6);

        $subCuentas = SubCuentaPropia::where('codigo', 'LIKE', $codigoSubstring . '%')->get();

        return response()->json($subCuentas);
    }


    public function getFacturas()
    {
        $facturas = Factura::with('transacciones.pago', 'tercero')
            ->orderBy('created_at', 'desc')
            ->get();


        $facturas->each(function ($factura) {
            $valorTotal = 0;
            $excedenteTotal = 0;

            foreach ($factura->transacciones as $transaccion) {

                foreach ($transaccion->pago as $pago) {
                    $valorTotal += $pago->valor;
                    $excedenteTotal += $pago->excedente;
                }
            }

            $factura->valor_total = $valorTotal;
            $factura->excedente_total = $excedenteTotal;
        });

        return response()->json($facturas);
    }


    public function storePagoFactura(Request $request)
    {
        $valorAbono = $request->input('valorAbono');
        $idFactura = $request->input('idFactura');


        $factura = Factura::with('transacciones.pago', 'tercero')->find($idFactura);



        $transaccion = $factura->transacciones->first();

        foreach ($transaccion->pago as $pago) {

            if ($pago->excedente > 0 && $valorAbono > 0) {

                $restarExcedente = min($pago->excedente, $valorAbono);


                $pago->excedente -= $restarExcedente;
                $pago->valor += $restarExcedente;
                $pago->fechaPago = Carbon::now()->format('Y-m-d');
                $pago->fechaReg = Carbon::now()->format('Y-m-d');

                $valorAbono -= $restarExcedente;


                if ($pago->excedente === 0.00) {
                    $pago->idEstado = Status::ID_APROBADO;
                }

                $existingPagoCuenta = AgregarPagoCuenta::where('idPago', $pago->id)
                    ->where('idSubcuentaPropia', SubCuentaPropia::BANCOS_NACIONALES)
                    ->exists();


                if (!$existingPagoCuenta && $pago->valor != 0.00) {
                    $pagoCuentaBancaria = new AgregarPagoCuenta();
                    $pagoCuentaBancaria->idSubcuentaPropia = SubCuentaPropia::BANCOS_NACIONALES;
                    $pagoCuentaBancaria->naturaleza = AgregarPagoCuenta::CREDITO;
                    $pagoCuentaBancaria->idTercero = 14;
                    $pagoCuentaBancaria->idPago = $pago->id;
                    $pagoCuentaBancaria->save();
                }


                $pago->save();



                if ($valorAbono === 0) {
                    break;
                }
            }
        }

        $pagoAbono = new AsignacionTransaccionPago();
        $pagoAbono->valor = $request->input('valorAbono');
        $pagoAbono->fecha = $pago->fechaPago;
        $pagoAbono->idTransaccion = $pago->idTransaccion;
        $pagoAbono->urlComprobante = $this->storeComprobanteAbono($request);
        $pagoAbono->save();


        return response()->json([
            'factura' => $factura
        ]);
    }

    private function storeComprobanteAbono(Request $request, $default = true)
    {
        $rutaComprobante = null;

        if ($default) {
            $rutaComprobante = AsignacionTransaccionPago::RUTA_ABONO_COMPROBANTE;
        }
        if ($request->hasFile('rutaComprobanteFile')) {
            $rutaComprobante =
                '/storage/' .
                $request
                ->file('rutaComprobanteFile')

                ->store(AsignacionTransaccionPago::RUTA_ABONO_COMPROBANTE, ['disk' => 'public']);
        }
        return $rutaComprobante;
    }

    public function storeProductoInventario(Request $request)
    {
        DB::beginTransaction();

        try {
            $idUser = auth()->user()->id;
            $productos = $request->input('productos');
            $productosCreados = [];

            foreach ($productos as $index => $productoData) {
                $producto = isset($productoData['idProducto']) ? Producto::find($productoData['idProducto']) : null;

                if (!$producto) {
                    $producto = new Producto();
                    $producto->caracteristicas = $productoData['caracteristicas'];
                    $producto->idTipoProducto = $productoData['idTipoProducto'];
                    if (!empty($productoData['modelo']) && $productoData['modelo'] !== 'undefined') {
                        $producto->modelo = $productoData['modelo'];
                    } else {
                        $producto->modelo = null;
                    }

                    if (!empty($productoData['serial']) && $productoData['serial'] !== 'undefined') {
                        $producto->serial = $productoData['serial'];
                    } else {
                        $producto->serial = null;
                    }

                    $producto->idMedida = $productoData['idMedida'];
                    $producto->idCategoria = $productoData['idCategoria'];
                    $producto->idMarca = $productoData['idMarca'];
                    $producto->estado = 'DISPONIBLE';
                    $producto->cantidad = $productoData['cantidad'];
                    if ($request->hasFile("productos.{$index}.imagen")) {
                        $archivo = $request->file("productos.{$index}.imagen");
                        $producto->urlProducto = $this->storeUrlProducto($archivo);
                    } else {
                        $producto->urlProducto = Producto::RUTA_PRODUCTO_DEFAULT;
                    }
                    $producto->save();
                    $producto->codigoProducto = $producto->id . '_' . $producto->serial;
                    $producto->save();


                    $historialPrecio = new HistorialPrecio();
                    $historialPrecio->valorCompra = $productoData['valor'];
                    $historialPrecio->idProducto = $producto->id;
                    $historialPrecio->idUser = $idUser;
                    $historialPrecio->idCompany = KeyUtil::idCompany();
                    $historialPrecio->fechaActualizacion = Carbon::now()->toDateString();

                    $historialPrecio->save();
                } else {


                    $ultimoHistorial = HistorialPrecio::where('idProducto', $producto->id)
                        ->where('idCompany', KeyUtil::idCompany())
                        ->latest('fechaActualizacion')
                        ->first();


                    $historialPrecio = new HistorialPrecio();
                    $historialPrecio->valorCompra = $productoData['valor'];
                    $historialPrecio->idProducto = $producto->id;
                    $historialPrecio->idUser = $idUser;
                    $historialPrecio->idCompany = KeyUtil::idCompany();
                    $historialPrecio->fechaActualizacion = Carbon::now()->toDateString();
                    $historialPrecio->ValorVenta = $ultimoHistorial?->ValorVenta ?? null;

                    $historialPrecio->save();


                    $producto->cantidad += $productoData['cantidad'];
                    $producto->save();
                }



                $idCompany = KeyUtil::idCompany();


                $sede = Sede::where('nombre', 'Sede Principal')
                    ->first();

                if (!$sede) {
                    $sede = new Sede();
                    $sede->nombre = 'Sede Principal';
                    $sede->idEmpresa = $idCompany;
                    $sede->urlImagen = '';
                    $sede->save();
                }


                $almacen = Almacen::where('nombreAlmacen', 'Almacén Principal')
                    ->where('idSede', $sede->id)
                    ->first();

                if (!$almacen) {
                    $almacen = new Almacen();
                    $almacen->nombreAlmacen = 'Almacén Principal';
                    $almacen->direccion = 'Cambiar';
                    $almacen->estado = 'ACTIVO';
                    $almacen->idSede = $sede->id;
                    $almacen->descripcion = 'Almacén Principal';
                    $almacen->save();
                }




                $distribucion = new DistribucionProducto();
                $distribucion->idAlmacenDestino = $almacen->id;
                $distribucion->idAlmacenOrigen = $almacen->id;
                $distribucion->idProducto = $producto->id;
                $distribucion->estado = 'ACEPTADO';
                $distribucion->fechaTraslado = date("Y-m-d H:i:s");
                $distribucion->observacion = "";
                $distribucion->idCompany = KeyUtil::idCompany();
                $distribucion->cantidad = $productoData['cantidad'];
                $distribucion->save();

                $producto->load('tipoProducto.claseProducto');

                $detallesProductos = [];
                $detalleFactura = new DetalleFactura();
                $detalleFactura->idProducto = $producto->id;
                $detalleFactura->idFactura = $request->input('idFactura');
                $detalleFactura->valor = $productoData['valor'];
                $detalleFactura->save();
                $detallesProductos[] = [
                    'detalleFactura' => $detalleFactura
                ];

                $productosCreados[] = [
                    'producto' => $producto,
                    'detallesProductos' => $detallesProductos
                ];
            }

            DB::commit();

        return response()->json(['productosCreados' => $productosCreados], 201);
        } catch (\Exception $e) {
            dd($e);
            DB::rollBack();
        return response()->json(['error' => 'Error interno del servidor.'], 500);
        }
    }
    public function getProductsForSelect()
    {
        $productos = Producto::with('medida', 'tipoProducto.claseProducto', 'marca', 'categoria')
            ->whereDoesntHave('tipoProducto', function ($query) {
                $query->where('nombreTipoProducto', 'MENU');
            })
            ->get();

        return response()->json($productos);
    }
    private function storeUrlProducto($archivo)
    {
        if ($archivo) {
            return '/storage/' . $archivo->store(Producto::RUTA_PRODUCTO, ['disk' => 'public']);
        }
        return Producto::RUTA_PRODUCTO_DEFAULT;
    }
}
