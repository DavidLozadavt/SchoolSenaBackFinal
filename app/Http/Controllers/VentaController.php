<?php

namespace App\Http\Controllers;

use App\Jobs\GeneratePdfAndSendEmail;
use App\Mail\MailBillService;
use App\Models\AgregarPagoCuenta;
use App\Models\Almacen;
use App\Models\AsignacionFacturaTransaccion;
use App\Models\Caja;
use App\Models\Company;
use App\Models\DetalleFactura;
use App\Models\DetalleProducto;
use App\Models\DistribucionProducto;
use App\Models\Factura;
use App\Models\HistorialPrecio;
use App\Models\Nomina\Sede as NominaSede;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\Transaccion;
use App\Models\Pago;
use App\Models\Producto;
use App\Models\PuntoVenta;
use App\Models\Servicio;
use App\Models\ShoppingCart;
use App\Models\Status;
use App\Models\SubCuentaPropia;
use App\Models\TipoFactura;
use App\Models\TipoProducto;
use App\Models\TipoTransaccion;
use App\Models\AhorroTercero;
use App\Models\Tercero;
use App\Models\TipoTercero;
use App\Util\KeyUtil;
use Barryvdh\DomPDF\Facade\Pdf;


class ventaController extends Controller
{
    public function createTransaccionAndPagoContado(Request $request)
    {

        try {

            DB::beginTransaction();

            $idUser = auth()->user()->id;
            $company = Company::findOrFail(1);
            $idShoppingCart = $request->input('idShoppingCart');
            $propina = $request->input("pagos.0.propina") ?? $request->input('propina');

            $descuentoAsociado = $company->descuentoAsociado;

            $shoppingCart = ShoppingCart::where('id', $idShoppingCart)
                ->with('asignaciones.detalleServicio.servicio', 'asignaciones.producto')
                ->first();

            $total = 0;
            foreach ($shoppingCart->asignaciones as $asignacion) {
                $cantidad = $asignacion->cantidad ?? 1;
                $total += $asignacion->valorUnitario * $cantidad;
            }


            $valorIva = 0;
            $valorMasIva = $total;


            if ($shoppingCart->iva === 'SI') {
                $valorIva = $total * 0.19;
                $valorMasIva = $total + $valorIva;
            }

            $totalSinIva = round($total, 2);
            $valorIva = round($valorIva, 2);
            $valorMasIva = round($valorMasIva, 2);



            $primerPagoFecha = $request->input("pagos.0.fecha") ?? $request->input('fecha');
            $primerPagoTipoPago = $request->input("pagos.0.idTipoPago") ?? $request->input('idTipoPago');

            $transaccion = new Transaccion();
            $transaccion->fechaTransaccion = $primerPagoFecha;
            $transaccion->hora = Carbon::now()->format('H:i');
            $transaccion->valor = $valorMasIva;
            $transaccion->idEstado = Status::ID_APROBADO;
            $transaccion->idTipoTransaccion = TipoTransaccion::VENTA;
            $transaccion->idTipoPago = $primerPagoTipoPago;
            $transaccion->excedente = 0;
            $transaccion->idCaja = $request->input('idCaja');

            if ($transaccion->idTipoTransaccion == TipoTransaccion::VENTA) {
                $lastTransaccion = Transaccion::where('idTipoTransaccion', TipoTransaccion::VENTA)
                    ->orderBy('id', 'desc')
                    ->first();
                $nextNumFactura = $lastTransaccion ? str_pad(intval($lastTransaccion->numFacturaInicial) + 1, 5, '0', STR_PAD_LEFT) : '00001';
                $transaccion->numFacturaInicial = $nextNumFactura;
            }

            $transaccion->save();

            if ($request->has("pagos")) {
                foreach ($request->input("pagos") as $index => $pagoData) {
                    $pago = new Pago();
                    $pago->fechaPago = $pagoData["fecha"] ?? $primerPagoFecha;
                    $pago->fechaReg = $pagoData["fecha"] ?? $primerPagoFecha;
                    $pago->valor = $pagoData["valor"] ?? 0;
                    $pago->excedente = 0;
                    $pago->idEstado = Status::ID_APROBADO;
                    $pago->idTransaccion = $transaccion->id;
                    $pago->idMedioPago = $pagoData["idMedioPago"] ?? null;
                    $pago->entidadBancaria = $pagoData["entidadBancaria"] ?? null;
                    $pago->save();


                    $fileKey = "pagos.$index.rutaComprobante";
                    if ($request->hasFile($fileKey)) {
                        $pago->guardarComprobante($request->file($fileKey));
                    }
                }
            }


            $pagosEfectivo = 0;
            $pagosTransferencia = 0;

            if ($request->has("pagos")) {
                foreach ($request->input("pagos") as $index => $pagoData) {
                    $idMedioPago = $pagoData["idMedioPago"] ?? null;
                    $valorPago = $pagoData["valor"] ?? 0;

                    if ($idMedioPago == 1) {
                        $pagosEfectivo += $valorPago;
                    } else {
                        $pagosTransferencia += $valorPago;
                    }
                }
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
            $factura->fecha = Carbon::now();
            $factura->valor = $totalSinIva;
            $factura->idTercero = $shoppingCart->idTercero;
            $factura->idCompany = KeyUtil::idCompany();
            $factura->valorMasIva = $valorMasIva;
            $factura->valorIva = $valorIva;
            $factura->idUser = $idUser;
            $factura->idTipoFactura = TipoFactura::VENTA;
            $factura->save();


            foreach ($shoppingCart->asignaciones as $asignacion) {
                $detalleFactura = new DetalleFactura();
                $detalleFactura->idFactura = $factura->id;

                $valorUnitario = $asignacion->valorUnitario;
                $cantidad = $asignacion->cantidad;


                if ($asignacion->producto) {
                    $detalleFactura->idProducto = $asignacion->producto->id;
                }

                if ($asignacion->detalleServicio && $asignacion->detalleServicio->servicio) {
                    $detalleFactura->idServicio = $asignacion->detalleServicio->servicio->id;
                }


                if ($shoppingCart->iva === 'SI') {
                    $valorIvaItem = $valorUnitario * 0.19;
                    $valorUnitarioConIva = round($valorUnitario + $valorIvaItem, 2);
                } else {
                    $valorUnitarioConIva = round($valorUnitario, 2);
                }

                $detalleFactura->valor = $valorUnitarioConIva;
                $detalleFactura->cantidad = $cantidad;
                $detalleFactura->save();
            }

            $asignacionFacturatransaccion = new   AsignacionFacturaTransaccion();
            $asignacionFacturatransaccion->idFactura = $factura->id;
            $asignacionFacturatransaccion->idTransaccion = $transaccion->id;
            $asignacionFacturatransaccion->save();

            if ($shoppingCart) {
                foreach ($shoppingCart->asignaciones as $asignacion) {
                    $producto = $asignacion->producto;
                    $idProducto = $asignacion->idProducto;
                    $cantidadRestante = $asignacion->cantidad;

                    if ($producto && $producto->tipoProducto && $producto->tipoProducto->nombreTipoProducto === 'MENU') {
                        continue;
                    }

                    $distribuciones = DistribucionProducto::where('idProducto', $idProducto)
                        ->where('estado', 'ACEPTADO')
                        ->orderBy('cantidad', 'desc')
                        ->get();

                    foreach ($distribuciones as $distribucion) {
                        if ($cantidadRestante <= 0) {
                            break;
                        }

                        if ($distribucion->cantidad >= $cantidadRestante) {
                            $distribucion->cantidad -= $cantidadRestante;
                            $cantidadRestante = 0;
                        } else {
                            $cantidadRestante -= $distribucion->cantidad;
                            $distribucion->cantidad = 0;
                        }

                        if ($distribucion->cantidad == 0) {
                            $distribucion->estado = 'AGOTADO';
                        }

                        $distribucion->save();
                    }
                }
            }



            if ($shoppingCart) {
                foreach ($shoppingCart->asignaciones as $asignacion) {
                    if ($asignacion->detalleServicio && $asignacion->detalleServicio->prestacionServicio) {
                        $asignacion->detalleServicio->prestacionServicio->estado = 'FINALIZADO';

                        $asignacion->detalleServicio->prestacionServicio->finServicio = Carbon::now()->format('Y-m-d H:i:s');
                        $inicioServicio = Carbon::parse($asignacion->detalleServicio->prestacionServicio->inicioServicio);
                        $finServicio = Carbon::parse($asignacion->detalleServicio->prestacionServicio->finServicio);
                        $asignacion->detalleServicio->prestacionServicio->totalMinutos = $inicioServicio->diffInMinutes($finServicio);

                        $asignacion->detalleServicio->prestacionServicio->pagoPropina = $propina;
                        $asignacion->detalleServicio->prestacionServicio->idFactura = $factura->id;
                        $asignacion->detalleServicio->prestacionServicio->save();
                    }
                }
            }

            $items = $shoppingCart->asignaciones->map(function ($asignacion) use ($shoppingCart) {
                return [
                    'nombre' => $asignacion->producto ? $asignacion->producto->caracteristicas : ($asignacion->detalleServicio ? $asignacion->detalleServicio->servicio->nombre : 'N/A'),
                    'cantidad' => $asignacion->cantidad,
                    'valor_unitario' => $asignacion->valorUnitario,
                    'iva' => $shoppingCart->iva,
                ];
            })->toArray();

            $company = Company::findOrFail($factura->idCompany);
            $caja = Caja::with('puntoVenta.sede', 'usuario.persona')->findOrFail($transaccion->idCaja);
            $tercero = $factura->tercero;

            // Verificar si el tercero es asociado y crear ahorro
            $terceroConTipos = Tercero::with('tipos')->find($shoppingCart->idTercero);
            if ($terceroConTipos && $terceroConTipos->tipos) {
                $tiposIds = $terceroConTipos->tipos->pluck('id')->toArray();

                // Verificar si tiene tipo PERSONA_NATURAL_ASOCIADO o PERSONA_JURIDICA_ASOCIADO
                if (
                    in_array(TipoTercero::PERSONA_NATURAL_ASOCIADO, $tiposIds) ||
                    in_array(TipoTercero::PERSONA_JURIDICA_ASOCIADO, $tiposIds)
                ) {

                    // Calcular el descuento sobre el total sin IVA
                    $valorDescuento = ($totalSinIva * $descuentoAsociado) / 100;

                    // Crear registro en AhorroTercero
                    $ahorroTercero = new AhorroTercero();
                    $ahorroTercero->idTercero = $shoppingCart->idTercero;
                    $ahorroTercero->valor = $valorDescuento;
                    $ahorroTercero->save();
                }
            }

            // Actualizar estado del carrito antes de commit
            if ($shoppingCart) {
                $shoppingCart->estado = 'PAGO';
                $shoppingCart->save();
            }

            // Hacer commit de la transacción ANTES de generar el PDF
            DB::commit();

            // Preparar datos para el PDF
            $data = [
                'company' => $company,
                'items' => $items,
                'factura' => $factura,
                'transaccion' => $transaccion,
                'tercero' => $tercero,
                'caja' => $caja,
                'pagosEfectivo' => $pagosEfectivo,
                'pagosTransferencia' => $pagosTransferencia,
            ];

            // Generar y devolver el PDF
            $pdf = PDF::loadView('pos.recibo-venta-productos', $data)
                ->setPaper([0, 0, 226, 400]);

            return response($pdf->output(), 200)
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', 'inline; filename="recibo-venta-productos.pdf"');
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json(['success' => false, 'message' => 'Ocurrió un error al crear la transacción y el pago', 'error' => $e->getMessage()], 500);
        }
    }


    public function createTransaccionAndPagoCredito(Request $request)
    {

        try {

            DB::beginTransaction();
            $transaccion = new Transaccion();
            $transaccion->fechaTransaccion = $request->input('fecha');
            $transaccion->hora = Carbon::now()->format('H:i');
            $transaccion->valor = $request->input('valorMasIva');
            $transaccion->idEstado = Status::ID_PENDIENTE;
            $transaccion->idTipoTransaccion = TipoTransaccion::VENTA;
            $transaccion->idTipoPago = $request->input('idTipoPago');
            $aporte = $request->input('aporte', 0);
            $transaccion->excedente = $request->input('valorMasIva') - $aporte;
            $transaccion->idCaja = $request->input('idCaja');


            if ($transaccion->idTipoTransaccion == TipoTransaccion::VENTA) {
                $lastTransaccion = Transaccion::where('idTipoTransaccion', TipoTransaccion::VENTA)
                    ->orderBy('id', 'desc')
                    ->first();
                $nextNumFactura = $lastTransaccion ? str_pad(intval($lastTransaccion->numFacturaInicial) + 1, 5, '0', STR_PAD_LEFT) : '00001';
                $transaccion->numFacturaInicial = $nextNumFactura;
            }

            $transaccion->save();

            if ($request->input('aporte') > 0) {
                $pago = new Pago();
                $pago->fechaPago = $request->input('fecha');
                $pago->fechaReg = $request->input('fecha');
                $pago->valor = $request->input('valorMasIva');
                $pago->excedente = $transaccion->excedente;
                $pago->idEstado =  Status::ID_PENDIENTE;
                $pago->idTransaccion = $transaccion->id;
                $pago->idMedioPago = $request->input('idMedioPago');
                $pago->saveComprobantePago($request);
                $pago->save();
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

            $factura->fecha = Carbon::now();
            $factura->valor = $request->input('valor');
            $factura->idTercero = $request->input('idTercero');
            $factura->valorIva = $request->input('valorIva');
            $factura->idTercero = $request->input('idTercero');
            $factura->valorMasIva = $request->input('valorMasIva');
            $factura->idCompany = KeyUtil::idCompany();
            $factura->idTipoFactura = TipoFactura::VENTA;
            $factura->save();



            $ivaActivo = $request->input('ivaActivo');
            $productosSeleccionados = json_decode($request->input('productosSeleccionados'), true);

            foreach ($productosSeleccionados as $producto) {
                $detalleFactura = new DetalleFactura();
                $detalleFactura->idProducto = $producto['idProducto'];
                $detalleFactura->idFactura = $factura->id;


                $valorUnitario = $producto['subtotal'] / $producto['cantidad'];

                if ($ivaActivo === true) {
                    $valorIva = $valorUnitario * 0.19;
                    $nuevoValorConIva = $valorUnitario + $valorIva;


                    $pagoIva = new AgregarPagoCuenta();
                    $pagoIva->idSubcuentaPropia = SubCuentaPropia::IVA;
                    $pagoIva->naturaleza = AgregarPagoCuenta::CREDITO;
                    $pagoIva->idPago = $pago->id;
                    $pagoIva->save();
                } else {
                    $valorIva = 0;
                    $nuevoValorConIva = $valorUnitario;
                }

                $detalleFactura->valor = $nuevoValorConIva;
                $detalleFactura->save();
            }


            $asignacionFacturatransaccion = new AsignacionFacturaTransaccion();
            $asignacionFacturatransaccion->idFactura = $factura->id;
            $asignacionFacturatransaccion->idTransaccion = $transaccion->id;
            $asignacionFacturatransaccion->save();


            $productosSeleccionadosTransformados = collect($productosSeleccionados)->map(function ($producto) use ($ivaActivo) {
                $valorUnitario = $producto['cantidad'] > 0 ? $producto['subtotal'] / $producto['cantidad'] : 0;
                $valorTotal = $producto['subtotal'];

                if ($ivaActivo === 'true') {
                    $valorIvaTotal = $valorUnitario * 0.19 * $producto['cantidad'];
                    $valorConIvaTotal = $valorTotal + $valorIvaTotal;
                } else {
                    $valorIvaTotal = 0;
                    $valorConIvaTotal = $valorTotal;
                }

                return [
                    'codigo' => $producto['idProducto'],
                    'cantidad' => $producto['cantidad'],
                    'caracteristicas' => $producto['caracteristicas'],
                    'subtotal' => $producto['subtotal'],
                    'iva' => $producto['iva'],
                    'valorUnitario' => $valorUnitario,
                    'valorTotal' => $valorTotal,
                    'valorIvaTotal' => $valorIvaTotal,
                    'valorConIvaTotal' => $valorConIvaTotal,
                ];
            });




            $distribuciones = DistribucionProducto::where('idProducto', $producto['idProducto'])
                ->where('estado', 'activo')
                ->orderBy('cantidad', 'desc')
                ->get();

            $cantidadRestante = $producto['cantidad'];

            foreach ($distribuciones as $distribucion) {
                if ($cantidadRestante <= 0) {
                    break;
                }

                if ($distribucion->cantidad >= $cantidadRestante) {

                    $distribucion->cantidad -= $cantidadRestante;
                    $cantidadRestante = 0;
                } else {
                    $cantidadRestante -= $distribucion->cantidad;
                    $distribucion->cantidad = 0;
                }

                if ($distribucion->cantidad == 0) {
                    $distribucion->estado = 'AGOTADO';
                }

                $distribucion->save();
            }



            $company = Company::findOrFail($factura->idCompany);
            $caja = Caja::with('puntoVenta.sede', 'usuario.persona')->findOrFail($transaccion->idCaja);
            $tercero = $factura->tercero;


            GeneratePdfAndSendEmail::dispatch($company, $productosSeleccionadosTransformados, $factura, $pago, $transaccion, $tercero, $caja);



            if ($pago->idMedioPago == 1) {
                $pagoCuentaCaja = new AgregarPagoCuenta();
                $pagoCuentaCaja->idSubcuentaPropia = SubCuentaPropia::CAJA;
                $pagoCuentaCaja->naturaleza = AgregarPagoCuenta::DEBITO;
                $pagoCuentaCaja->idPago = $pago->id;
                $pagoCuentaCaja->save();
            } else {
                $pagoCuentaBancaria = new AgregarPagoCuenta();
                $pagoCuentaBancaria->idSubcuentaPropia = SubCuentaPropia::BANCOS_NACIONALES;
                $pagoCuentaBancaria->naturaleza = AgregarPagoCuenta::DEBITO;
                $pagoCuentaBancaria->idPago = $pago->id;
                $pagoCuentaBancaria->save();
            }

            $pagoCuenta = new AgregarPagoCuenta();
            $pagoCuenta->idSubcuentaPropia = SubCuentaPropia::INGRESOS;
            $pagoCuenta->naturaleza = AgregarPagoCuenta::CREDITO;
            $pagoCuenta->idPago = $pago->id;
            $pagoCuenta->save();


            DB::commit();

            return response()->json(['success' => true, 'message' => 'Transacción y pago creados exitosamente']);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json(['success' => false, 'message' => 'Ocurrió un error al crear la transacción y el pago a crédito', 'error' => $e->getMessage()], 500);
        }
    }





    public function getProductosPuntosVenta($id)
    {
        $puntoVenta = PuntoVenta::where('id', $id)->first();
        if (!$puntoVenta) {
            return response()->json(['message' => 'Punto de venta no encontrado'], 404);
        }


        $almacenes = Almacen::where('idSede', $puntoVenta->idSede)->pluck('id')->toArray();
        if (empty($almacenes)) {
            return response()->json([]);
        }


        $distribuciones = DistribucionProducto::with('producto.ultimoHistorialPrecio')
            ->whereIn('idAlmacenDestino', $almacenes)
            ->where('estado', 'ACEPTADO')
            ->whereHas('producto', function ($query) {
                $query->whereIn('estado', ['DISPONIBLE', 'PENDIENTE', 'GARANTIA', 'DEVOLUCION'])
                    ->whereHas('tipoProducto', function ($subQuery) {
                        $subQuery->where('nombreTipoProducto', '!=', 'MENU');
                    });
            })
            ->get()
            ->groupBy(function ($item) {
                return $item->idProducto . '-' . $item->idAlmacenDestino . '-' . $item->idAlmacenOrigen;
            })
            ->map(function ($groupedItems) {
                $firstItem = $groupedItems->first();
                $firstItem->cantidad = $groupedItems->sum('cantidad');
                return $firstItem;
            })
            ->filter(function ($item) {
                return $item->cantidad > 0;
            })
            ->values();



        return response()->json($distribuciones);
    }





    public function getStockMinimoPuntoVenta($id)
    {

        $company = Company::findOrFail(1);

        $stockMinimo = $company->stockMinimo;


        $puntoVenta = PuntoVenta::where('id', $id)->first();
        if (!$puntoVenta) {
            return response()->json(['message' => 'Punto de venta no encontrado'], 404);
        }


        $almacenes = Almacen::where('idSede', $puntoVenta->idSede)->pluck('id')->toArray();
        if (empty($almacenes)) {
            return response()->json([]);
        }


        $distribuciones = DistribucionProducto::with('producto.ultimoHistorialPrecio')
            ->whereIn('idAlmacenDestino', $almacenes)
            ->where('estado', 'ACEPTADO')
            ->whereHas('producto', function ($query) {
                $query->whereIn('estado', ['DISPONIBLE', 'PENDIENTE', 'GARANTIA', 'DEVOLUCION'])
                    ->whereHas('tipoProducto', function ($subQuery) {
                        $subQuery->where('nombreTipoProducto', '!=', 'MENU');
                    });
            })
            ->get()
            ->groupBy(function ($item) {
                return $item->idProducto . '-' . $item->idAlmacenDestino . '-' . $item->idAlmacenOrigen;
            })
            ->map(function ($groupedItems) {
                $firstItem = $groupedItems->first();
                $firstItem->cantidad = $groupedItems->sum('cantidad');
                return $firstItem;
            })
            ->filter(function ($item) use ($stockMinimo) {
                return $item->cantidad > 0 && $item->cantidad <= $stockMinimo;
            })
            ->values();



        return response()->json($distribuciones);
    }



    public function getProductosPuntosVentaMenu($id)
    {
        $puntoVenta = PuntoVenta::where('id', $id)->first();
        if (!$puntoVenta) {
            return response()->json(['message' => 'Punto de venta no encontrado'], 404);
        }

        $almacenes = Almacen::where('idSede', $puntoVenta->idSede)->pluck('id')->toArray();
        if (empty($almacenes)) {
            return response()->json([]);
        }

        $distribuciones = DistribucionProducto::with('producto.ultimoHistorialPrecio')
            ->whereIn('idAlmacenDestino', $almacenes)
            ->where('estado', 'ACEPTADO')
            ->whereHas('producto', function ($query) {
                $query->whereIn('estado', ['DISPONIBLE', 'NO DISPONIBLE', 'PENDIENTE', 'GARANTIA', 'DEVOLUCION'])
                    ->whereHas('tipoProducto', function ($subQuery) {
                        $subQuery->where('nombreTipoProducto', 'MENU');
                    });
            })
            ->get()
            ->groupBy(function ($item) {
                return $item->idProducto . '-' . $item->idAlmacenDestino . '-' . $item->idAlmacenOrigen;
            })
            ->map(function ($groupedItems) {
                $firstItem = $groupedItems->first();
                $firstItem->cantidad = $groupedItems->sum('cantidad');
                return $firstItem;
            })
            ->filter(function ($item) {
                return $item->cantidad > 0;
            })
            ->values();

        return response()->json($distribuciones);
    }





    public function changeStatusPendiente($id)
    {
        $detalleProducto = DetalleProducto::findOrFail($id);
        $detalleProducto->estado = 'PENDIENTE';
        $detalleProducto->save();
        return response()->json($detalleProducto);
    }


    public function changeStatusDisponible($id)
    {
        $detalleProducto = DetalleProducto::findOrFail($id);
        $detalleProducto->estado = 'DISPONIBLE';
        $detalleProducto->save();
        return response()->json($detalleProducto);
    }






    public function getAllServicios()
    {
        $idCompany = KeyUtil::idCompany();
        $servicios = Servicio::where('idCompany', $idCompany)->get();

        return response()->json($servicios);
    }


    public function validateExistenceProducto($id)
    {
        $shoppingCart = ShoppingCart::where('id', $id)
            ->with('asignaciones.detalleServicio.servicio', 'asignaciones.producto')
            ->first();

        if (!$shoppingCart) {
            return response()->json(['message' => 'Carrito no encontrado'], 404);
        }

        $productosFaltantes = [];

        foreach ($shoppingCart->asignaciones as $asignacion) {
            $idProducto = $asignacion->idProducto;
            $cantidadRequerida = $asignacion->cantidad;

            $cantidadDisponible = DistribucionProducto::where('idProducto', $idProducto)
                ->where('estado', 'ACEPTADO')
                ->sum('cantidad');

            $producto = Producto::find($idProducto);
            $nombreProducto = $producto ? $producto->caracteristicas : 'Desconocido';

            if ($cantidadDisponible < $cantidadRequerida) {
                $productosFaltantes[] = [
                    'idProducto' => $idProducto,
                    'nombreProducto' => $nombreProducto,
                    'cantidadRequerida' => $cantidadRequerida,
                    'cantidadDisponible' => $cantidadDisponible,
                    'mensaje' => "Solo hay $cantidadDisponible disponibles para el producto '$nombreProducto'",
                ];
            }
        }

        if (!empty($productosFaltantes)) {
            return response()->json([
                'message' => 'Algunos productos no tienen suficiente stock',
                'productos_faltantes' => $productosFaltantes
            ], 400);
        }

        return response()->json(['message' => 'Todos los productos tienen stock suficiente'], 200);
    }





    public function getProductosMenu(Request $request)
    {
        $perPage = $request->input('per_page', 10);
        $searchTerm = $request->input('search', '');
        $idCompany = KeyUtil::idCompany();

        $productos = Producto::with([
            'medida',
            'tipoProducto',
            'detalleFactura',
            'ultimoHistorialPrecio',
            'distribuciones',
            'categoria'
        ])
            ->whereHas('distribuciones', function ($query) use ($idCompany) {
                $query->where('idCompany', $idCompany);
            })
            ->whereHas('tipoProducto', function ($query) {
                $query->where('nombreTipoProducto', 'MENU');
            })
            ->where('caracteristicas', 'LIKE', "%{$searchTerm}%")
            ->paginate($perPage);

        return response()->json($productos);
    }


    public function storeProductoMenu(Request $request)
    {
        $idCompany = KeyUtil::idCompany();
        $idUser = auth()->user()->id;

        $sede = NominaSede::where('nombreSede', 'Sede Principal')
            ->where('idCompany', $idCompany)
            ->first();

        $almacen = Almacen::where('nombreAlmacen', 'Almacén Principal')
            ->where('idSede', $sede->id)
            ->first();

        $tipoProducto = TipoProducto::where('nombreTipoProducto', 'MENU')
            ->where('idCompany', $idCompany)
            ->first();

        $producto = new Producto();
        $producto->caracteristicas = $request->input('nombreProducto');
        $producto->valorVenta = $request->input('valorVenta');
        $producto->idMedida = $request->input('medida');
        $producto->cantidad = 1;
        $producto->estado = $request->input('estado');
        $producto->idCategoria = $request->input('categoria');
        $producto->idTipoProducto = $tipoProducto->id;

        if ($request->hasFile('file')) {
            $archivo = $request->file('file');
            $producto->urlProducto = $this->storeUrlProducto($archivo);
        } else {
            $producto->urlProducto = Producto::RUTA_PRODUCTO_DEFAULT;
        }

        $producto->save();


        $distribucion = new DistribucionProducto();
        $distribucion->idAlmacenDestino = $almacen->id;
        $distribucion->idAlmacenOrigen = $almacen->id;
        $distribucion->idProducto = $producto->id;
        $distribucion->estado = 'ACEPTADO';
        $distribucion->fechaTraslado = date("Y-m-d H:i:s");
        $distribucion->observacion = "";
        $distribucion->idCompany = KeyUtil::idCompany();
        $distribucion->cantidad = 1;
        $distribucion->save();

        $historialPrecio = new HistorialPrecio();
        $historialPrecio->valorCompra = $producto->valorVenta;
        $historialPrecio->ValorVenta = $producto->valorVenta;
        $historialPrecio->idProducto = $producto->id;
        $historialPrecio->idUser = $idUser;
        $historialPrecio->idCompany = KeyUtil::idCompany();
        $historialPrecio->fechaActualizacion = Carbon::now()->toDateString();

        $historialPrecio->save();


        return response()->json([
            'message' => 'Producto creado exitosamente',
            'producto' => $producto
        ]);
    }


    public function updateProductoMenu(Request $request, $id)
    {
        $idCompany = KeyUtil::idCompany();
        $idUser = auth()->user()->id;

        $producto = Producto::findOrFail($id);

        $nuevoValorVenta = $request->input('valorVenta');
        $valorVentaAnterior = $producto->valorVenta;

        $producto->caracteristicas = $request->input('nombreProducto');
        $producto->valorVenta = $nuevoValorVenta;
        $producto->idMedida = $request->input('medida');
        $producto->estado = $request->input('estado');
        $producto->idCategoria = $request->input('categoria');

        if ($request->hasFile('file')) {
            $archivo = $request->file('file');
            $producto->urlProducto = $this->storeUrlProducto($archivo);
        }

        $producto->save();


        if ($nuevoValorVenta != $valorVentaAnterior) {
            $historialPrecio = new HistorialPrecio();
            $historialPrecio->valorCompra = $nuevoValorVenta;
            $historialPrecio->ValorVenta = $nuevoValorVenta;
            $historialPrecio->idProducto = $producto->id;
            $historialPrecio->idUser = $idUser;
            $historialPrecio->idCompany = $idCompany;
            $historialPrecio->fechaActualizacion = Carbon::now()->toDateString();
            $historialPrecio->save();
        }

        return response()->json([
            'message' => 'Producto actualizado exitosamente',
            'producto' => $producto
        ]);
    }


    private function storeUrlProducto($archivo)
    {
        if ($archivo) {
            return '/storage/' . $archivo->store(Producto::RUTA_PRODUCTO, ['disk' => 'public']);
        }
        return Producto::RUTA_PRODUCTO_DEFAULT;
    }



    public function getAhorrosTerceros()
    {
        $ahorrosTerceros = AhorroTercero::with('tercero')->get();
        return response()->json($ahorrosTerceros);
    }
}
