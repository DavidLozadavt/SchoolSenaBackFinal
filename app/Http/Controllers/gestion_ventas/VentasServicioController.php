<?php

namespace App\Http\Controllers\gestion_ventas;

use App\Enums\StatusCartType;
use App\Http\Controllers\Controller;
use App\Jobs\GenerateAndSendPdf;
use App\Models\AgregarPagoCuenta;
use App\Models\Almacen;
use App\Models\ArticuloServicio;
use App\Models\AsignacionCarritoProducto;
use App\Models\AsignacionFacturaTransaccion;
use App\Models\Caja;
use App\Models\Company;
use App\Models\DetalleFactura;
use App\Models\DetalleProducto;
use App\Models\DetalleServicio;
use App\Models\DistribucionProducto;
use App\Models\Factura;
use App\Models\HistorialPrecio;
use App\Models\MultimediaArticulos;
use App\Models\Pago;
use App\Models\PrestacionServicio;
use App\Models\Producto;
use App\Models\PuntoVenta;
use App\Models\ResponsableServicio;
use App\Models\Servicio;
use App\Models\ShoppingCart;
use App\Models\Status;
use App\Models\SubCuentaPropia;
use App\Models\Tercero;
use App\Models\TipoFactura;
use App\Models\TipoProducto;
use App\Models\TipoTercero;
use App\Models\TipoTransaccion;
use App\Models\Transaccion;
use App\Util\KeyUtil;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;


class VentasServicioController extends Controller
{

    public function getServiciosAsignados($id)
    {
        $idCompany = KeyUtil::idCompany();
        $idUser = auth()->user()->id;
        $hoy = Carbon::now()->toDateString();

        $servicioAsignados = PrestacionServicio::with([
            'detalles.servicio',
            'detalles.asignacionesCarrito.shoppingCart.tercero',
            'factura.transacciones.pago',
            'responsable.persona',
            'escenario',
            'articuloServicio'
        ])
            ->whereHas('detalles.asignacionesCarrito.shoppingCart', function ($query) use ($idCompany) {
                $query->where('idCompany', $idCompany);
            })
            ->where(function ($query) use ($idUser, $hoy) {
                $query->where(function ($q) use ($idUser) {
                    $q->whereHas('factura', function ($subq) use ($idUser) {
                        $subq->where('idUser', $idUser);
                    });
                })
                    ->orWhereDoesntHave('factura');
            })
            ->where(function ($query) use ($hoy) {
                $query->where('estado', '!=', 'FINALIZADO')
                    ->orWhere(function ($q) use ($hoy) {
                        $q->where('estado', 'FINALIZADO')
                            ->whereDate('finServicio', $hoy);
                    });
            })
            ->orderByRaw("FIELD(estado, 'CANCELADO', 'FINALIZADO') ASC")
            ->orderBy('inicioServicio', 'desc')
            ->get();

        return response()->json($servicioAsignados);
    }







    public function storeServicios(Request $request)
    {
        try {
            DB::beginTransaction();



            $servicios = $request->input('servicios');
            $valorTotal = $request->input('valorTotal'); //total sin iva
            $totalMasIva = $request->input('totalMasIva'); //iva mas valor total
            $totalIva = $request->input('totalIva');  //valor del iva
            $codigo = $request->input('codigo');
            $idTipoArticulo = $request->input('idTipoArticulo');


            $transaccion = new Transaccion();
            $transaccion->fechaTransaccion = Carbon::now();
            $transaccion->hora = Carbon::now()->format('H:i');
            $transaccion->valor = $totalMasIva;
            $transaccion->idEstado = Status::ID_PENDIENTE;
            $transaccion->idTipoTransaccion = TipoTransaccion::VENTA;
            $transaccion->excedente =  $totalMasIva;


            if ($transaccion->idTipoTransaccion == TipoTransaccion::VENTA) {
                $lastTransaccion = Transaccion::where('idTipoTransaccion', TipoTransaccion::VENTA)
                    ->orderBy('id', 'desc')
                    ->first();
                $nextNumFactura = $lastTransaccion ? str_pad(intval($lastTransaccion->numFacturaInicial) + 1, 5, '0', STR_PAD_LEFT) : '00001';
                $transaccion->numFacturaInicial = $nextNumFactura;
            }

            $transaccion->save();

            $pago = new Pago();
            $pago->fechaPago = Carbon::now();
            $pago->valor = $totalMasIva;
            $pago->idEstado = Status::ID_PENDIENTE;
            $pago->excedente =  $totalMasIva;
            $pago->idTransaccion = $transaccion->id;
            $pago->idMedioPago = $request->input('idMedioPago');
            $pago->saveComprobantePago($request);
            $pago->save();


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
            $factura->valorIva = $totalIva;
            $factura->valor = $valorTotal;
            $factura->valorMasIva = $totalMasIva;
            $factura->idTercero = $request->input('idTercero');
            $factura->idCompany = KeyUtil::idCompany();
            $factura->idTipoFactura = TipoFactura::VENTA;


            $factura->save();

            $factura->fecha = Carbon::now();
            $factura->valorIva = $totalIva;
            $factura->valor = $valorTotal;
            $factura->valorMasIva = $totalMasIva;
            $factura->idTercero = $request->input('idTercero');
            $factura->idCompany =  KeyUtil::idCompany();
            $factura->idTipoFactura = TipoFactura::VENTA;

            $factura->save();



            $asignacionFacturatransaccion = new   AsignacionFacturaTransaccion();
            $asignacionFacturatransaccion->idFactura = $factura->id;
            $asignacionFacturatransaccion->idTransaccion = $transaccion->id;
            $asignacionFacturatransaccion->save();


            $articuloServicio = ArticuloServicio::where('codigo', $codigo)
                ->where('idTipoArticulo', $idTipoArticulo)
                ->first();


            if (!$articuloServicio) {
                $articuloServicio = new ArticuloServicio();
                $articuloServicio->codigo = $codigo;
                $articuloServicio->idTipoArticulo = $idTipoArticulo;
                $articuloServicio->save();
            }


            $prestacionServicio = new PrestacionServicio();
            $prestacionServicio->estado = 'PENDIENTE';
            $prestacionServicio->inicioServicio = now()->format('Y-m-d H:i:s');
            $prestacionServicio->idFactura = $factura->id;
            $prestacionServicio->valorTotalServicios =  $totalMasIva;
            $prestacionServicio->save();


            if (is_array($servicios)) {
                foreach ($servicios as $servicio) {



                    $detalleServicio = new DetalleServicio();
                    $detalleServicio->idServicio = $servicio['id'];
                    $detalleServicio->valor = $servicio['valor'];
                    $detalleServicio->valorIvaServicio = $servicio['valorIvaServicio'];
                    $detalleServicio->idPrestacionServicio = $prestacionServicio->id;
                    $detalleServicio->save();
                }
            }

            DB::commit();
            return response()->json(['success' => 'Servicios guardados exitosamente.'], 200);
        } catch (\Exception $e) {

            DB::rollBack();
            return response()->json(['error' => 'Hubo un error al guardar los servicios.'], 500);
        }
    }





    public function storePagoServicio(Request $request)
    {
        $opcionPago = $request->input('opcionPago');
        $idCompany = KeyUtil::idCompany();
        $idPago = $request->input('idPago');
        $idTransaccion = $request->input('idTransaccion');
        $idPrestacionServicio = $request->input('idPrestacionServicio');
        $idCaja = $request->input('idCaja');

        $prestacionServicio = PrestacionServicio::with(['detalles'])->findOrFail($idPrestacionServicio);
        $company = Company::findOrFail($idCompany);

        if ($opcionPago === 'continuar') {
            $prestacionServicio->estado = 'TRAMITE';
        } elseif ($opcionPago === 'terminar') {
            $prestacionServicio->estado = 'FINALIZADO';
            $prestacionServicio->finServicio = Carbon::now()->format('Y-m-d H:i:s');
            $inicioServicio = Carbon::parse($prestacionServicio->inicioServicio);
            $finServicio = Carbon::parse($prestacionServicio->finServicio);
            $prestacionServicio->totalMinutos = $inicioServicio->diffInMinutes($finServicio);
        }

        $prestacionServicio->save();


        $responsable = ResponsableServicio::findOrFail($prestacionServicio->idResponsable);
        $ivaResponsable = $responsable->porcentajeGanancia;

        foreach ($prestacionServicio->detalles as $detalleServicio) {
            $detalleServicio->valorPorcentajeGanancia = $detalleServicio->valor * ($ivaResponsable / 100);
            $detalleServicio->save();
        }

        $factura = $prestacionServicio->factura;
        $tercero = $factura->tercero;

        $transaccion = Transaccion::findOrFail($idTransaccion);
        $transaccion->idTipoPago = $request->input('idTipoPago');
        $transaccion->idCaja = $idCaja;
        $excedenteActual = $transaccion->excedente;
        $aporte = $request->input('aporte');

        if ($transaccion->idTipoPago == '2') {
            $transaccion->excedente = 0;
            $transaccion->idEstado = Status::ID_APROBADO;

            // $this->storeCuentaContado($idPago, $idSubcuentaPropia, $tercero->id);

        } else {
            $transaccion->excedente = $excedenteActual - $aporte;
            if ($transaccion->excedente == 0) {
                $transaccion->idEstado = Status::ID_APROBADO;
            } else {
                $transaccion->idEstado = Status::ID_PENDIENTE;
            }
        }

        $transaccion->save();

        $pago = Pago::findOrFail($idPago);
        $pago->fechaReg = Carbon::now();
        $pago->idMedioPago = $request->input('idMedioPago');
        $pago->excedente = $transaccion->excedente;
        $pago->saveComprobantePago($request);

        if ($transaccion->idEstado == Status::ID_PENDIENTE) {
            $pago->idEstado = Status::ID_PENDIENTE;
        } else {
            $pago->idEstado = Status::ID_APROBADO;
        }

        $pago->save();

        $caja = Caja::with('puntoVenta.sede', 'usuario.persona')->findOrFail($idCaja);



        GenerateAndSendPdf::dispatch($company, $prestacionServicio, $factura, $pago, $transaccion, $tercero, $caja);

        return response()->json(['message' => 'Pago del servicio exitoso', 'prestacionServicio' => $prestacionServicio]);
    }





    public function asignarResponsable(Request $request)
    {
        $idPrestacionServicio = $request->input('idPrestacionServicio');
        $idResponsable = $request->input('idResponsable');


        $existePendiente = PrestacionServicio::where('idResponsable', $idResponsable)
            ->where('estado', 'PENDIENTE')
            ->where('id', '!=', $idPrestacionServicio)
            ->exists();

        if ($idResponsable && $existePendiente) {
            return response()->json([
                'message' => 'Este responsable ya está asignado a otra prestación de servicio.'
            ], 422);
        }

        $prestacionServicio = PrestacionServicio::findOrFail($idPrestacionServicio);
        $prestacionServicio->idResponsable = $idResponsable ?: null;
        $prestacionServicio->save();

        return response()->json($prestacionServicio);
    }



    public function observacionServicio(Request $request)
    {

        $idPrestacionServicio = $request->input('idPrestacionServicio');
        $observacion = $request->input('observacion');

        $prestacionServicio = PrestacionServicio::findOrFail($idPrestacionServicio);
        $prestacionServicio->diagnostico = $observacion;
        $prestacionServicio->save();

        return response()->json($prestacionServicio);
    }



    public function finalizarServicio($id)
    {

        $prestacionServicio = PrestacionServicio::findOrFail($id);

        $prestacionServicio->finServicio = Carbon::now()->format('Y-m-d H:i:s');
        $prestacionServicio->estado = 'FINALIZADO';
        $inicio = Carbon::parse($prestacionServicio->inicioServicio);
        $fin = Carbon::parse($prestacionServicio->finServicio);
        $prestacionServicio->totalMinutos = $inicio->diffInMinutes($fin);

        $prestacionServicio->save();

        return response()->json($prestacionServicio);
    }


    public function cancelarServicio($id)
    {

        $prestacionServicio = PrestacionServicio::findOrFail($id);

        $prestacionServicio->finServicio = Carbon::now()->format('Y-m-d H:i:s');
        $prestacionServicio->estado = 'CANCELADO';
        $inicio = Carbon::parse($prestacionServicio->inicioServicio);
        $fin = Carbon::parse($prestacionServicio->finServicio);
        $prestacionServicio->totalMinutos = $inicio->diffInMinutes($fin);

        $prestacionServicio->save();

        $factura = $prestacionServicio->factura;

        if ($factura) {

            foreach ($factura->transacciones as $transaccion) {
                $transaccion->idEstado = Status::ID_CANCELADO;
                $transaccion->save();


                foreach ($transaccion->pago as $pago) {
                    $pago->idEstado = Status::ID_CANCELADO;
                    $pago->save();
                }
            }
        }


        return response()->json($prestacionServicio);
    }



    public function getProductosByTipoProducto(Request $request)
    {
        $perPage = $request->input('per_page', 10);
        $searchTerm = $request->input('search', '');
        $idCompany = KeyUtil::idCompany();

        $productos = Producto::with([
            'medida',
            'tipoProducto',
            'ultimoHistorialPrecio',
            'distribuciones'
        ])
            ->whereHas('distribuciones', function ($query) use ($idCompany) {
                $query->where('idCompany', $idCompany);
            })
            ->whereHas('tipoProducto', function ($query) {
                $query->where('nombreTipoProducto', '!=', 'MENU');
            })
            ->where('caracteristicas', 'LIKE', "%{$searchTerm}%")
            ->paginate($perPage);
       
        /** @var \Illuminate\Pagination\LengthAwarePaginator $productos */
        $productos->getCollection()->transform(function ($producto) use ($idCompany) {
            $producto->totalDistribuido = $producto->distribuciones
                ->where('estado', 'ACEPTADO')
                ->where('idCompany', $idCompany)
                ->sum(function ($item) {
                    return (float) $item->cantidad;
                });

            return $producto;
        });

        return response()->json($productos);
    }











    public function updateValorVentaProducto(Request $request)
    {
        $idProducto = $request->input('idProducto');
        $valorVenta = $request->input('valorVenta');
        $porcentajeUtilidad = $request->input('porcentajeUtilidad');
        $valorCompra = $request->input('valorCompra');
        $nombreProducto = $request->input('nombreProducto');
        $cantidad = $request->input('cantidad');
        $estado = $request->input('estado');
        $archivo = $request->file('imagen');
        $idUser = auth()->user()->id;

        $producto = Producto::find($idProducto);

        if (!$producto) {
            return response()->json(['error' => 'Producto no encontrado'], 404);
        }

        if ($nombreProducto) {
            $producto->caracteristicas = $nombreProducto;
        }

        if ($estado) {
            $producto->publicacion = $estado;
        }

        if ($cantidad) {

            $almacen = Almacen::where('nombreAlmacen', 'Almacén Principal')
                ->whereHas('sede', function ($query) {
                    $query->where('nombreSede', 'Sede Principal')
                        ->where('idCompany', KeyUtil::idCompany());
                })
                ->first();




            $distribucion = new DistribucionProducto();
            $distribucion->idAlmacenDestino = $almacen->id;
            $distribucion->idAlmacenOrigen = $almacen->id;
            $distribucion->idProducto = $producto->id;
            $distribucion->estado = 'ACEPTADO';
            $distribucion->fechaTraslado = date("Y-m-d H:i:s");
            $distribucion->observacion = "";
            $distribucion->idCompany = KeyUtil::idCompany();
            $distribucion->cantidad = $cantidad;
            $distribucion->save();
        }



        if ($archivo) {
            $nuevaRuta = $this->storeUrlProducto($archivo);
            $producto->urlProducto = $nuevaRuta;
        }

        $producto->valorVenta = $valorVenta;
        $producto->save();

        $historialPrecio = new HistorialPrecio();
        $historialPrecio->valorCompra = $valorCompra;
        $historialPrecio->valorVenta = $valorVenta;
        $historialPrecio->porcentajeUtilidad = $porcentajeUtilidad;
        $historialPrecio->idProducto = $idProducto;
        $historialPrecio->idUser = $idUser;
        $historialPrecio->idCompany = KeyUtil::idCompany();
        $historialPrecio->fechaActualizacion = Carbon::now()->toDateString();
        $historialPrecio->save();

        return response()->json(['message' => 'Producto actualizado correctamente']);
    }


    private function storeUrlProducto($archivo)
    {
        if ($archivo) {
            return '/storage/' . $archivo->store(Producto::RUTA_PRODUCTO, ['disk' => 'public']);
        }
        return Producto::RUTA_PRODUCTO_DEFAULT;
    }







    private function storeCuentaContado($idPago, $idSubcuentaPropia, $idTercero)
    {
        $pagoCuentaBancaria = new AgregarPagoCuenta();
        $pagoCuentaBancaria->idSubcuentaPropia = SubCuentaPropia::BANCOS_NACIONALES;
        $pagoCuentaBancaria->naturaleza = AgregarPagoCuenta::CREDITO;
        $pagoCuentaBancaria->idPago = $idPago;
        $pagoCuentaBancaria->save();

        $pagoCuentaProveedor = new AgregarPagoCuenta();
        $pagoCuentaProveedor->idSubcuentaPropia = SubCuentaPropia::PROVEEDORES_NACIONALES;
        $pagoCuentaProveedor->naturaleza = AgregarPagoCuenta::CREDITO;
        $pagoCuentaBancaria->idPago = $idPago;
        $pagoCuentaProveedor->save();
    }



    private function storeCuentaCredito($idPago, $idSubcuentaPropia, $idTercero)
    {
        $pagoCuentaBancaria = new AgregarPagoCuenta();
        $pagoCuentaBancaria->idSubcuentaPropia = SubCuentaPropia::BANCOS_NACIONALES; //debe ser configurables
        $pagoCuentaBancaria->naturaleza = AgregarPagoCuenta::DEBITO;
        $pagoCuentaBancaria->idPago = $idPago;
        $pagoCuentaBancaria->idTercero = $idTercero;
        $pagoCuentaBancaria->save();

        $pagoCuentaProveedor = new AgregarPagoCuenta();
        $pagoCuentaProveedor->idSubcuentaPropia = 5;  //debe ser configurables
        $pagoCuentaProveedor->naturaleza = AgregarPagoCuenta::CREDITO;
        $pagoCuentaBancaria->idPago = $idPago;
        $pagoCuentaBancaria->idTercero = $idTercero;
        $pagoCuentaProveedor->save();
    }


    // public function storeShoppingCartProducto(Request $request)
    // {
    //     $producto = Producto::where('id', $request->idProducto)->with('ultimoHistorialPrecio')->first();

    //     if (!$producto) {
    //         return response()->json(['error' => 'Producto no encontrado'], 404);
    //     }

    //     $ivaActivo = $request->input('ivaActivo');
    //     $idShoppingCart = $request->input('idShoppingCart');
    //     $idTercero = $request->input('idTercero');

    //     $valorVenta = $producto->ultimoHistorialPrecio->ValorVenta ?? 0;


    //     if (!$idShoppingCart) {
    //         $carritoExistente = ShoppingCart::where('idTercero', $idTercero)
    //             ->where('estado', StatusCartType::PENDIENTE)
    //             ->whereDate('created_at', now()->toDateString())
    //             ->first();

    //         if ($carritoExistente) {
    //             $shoppingCart = $carritoExistente;
    //         }
    //     }

    //     if ($idShoppingCart) {
    //         $shoppingCart = ShoppingCart::find($idShoppingCart);
    //     }


    //     if (!isset($shoppingCart) || !$shoppingCart) {
    //         $shoppingCart = ShoppingCart::create([
    //             'estado' => StatusCartType::PENDIENTE,
    //             'origen' => 'PUNTO POS',
    //             'idTercero' => $idTercero,
    //             'idCompany' => KeyUtil::idCompany(),
    //         ]);
    //     }

    //     $asignacion = new AsignacionCarritoProducto();
    //     $asignacion->idShoppingCart = $shoppingCart->id;
    //     $asignacion->idProducto = $producto->id;
    //     $asignacion->cantidad = 1;
    //     $asignacion->valorUnitario = $valorVenta;
    //     $asignacion->save();

    //     return response()->json([
    //         'shoppingCart' => $shoppingCart,
    //         'producto' => $producto,
    //         'asignacion' => $asignacion
    //     ]);
    // }

    public function storeShoppingCartProducto(Request $request)
    {
        $producto = Producto::where('id', $request->idProducto)
            ->with('ultimoHistorialPrecio')
            ->first();

        if (!$producto) {
            return response()->json(['error' => 'Producto no encontrado'], 404);
        }

        $ivaActivo = $request->input('ivaActivo');
        $idShoppingCart = $request->input('idShoppingCart');
        $idTercero = $request->input('idTercero');

        if (!$idTercero) {
            $tercero = Tercero::firstOrCreate(
                ['identificacion' => '2222222222'],
                [
                    'nombre' => 'CONSUMIDOR FINAL',
                    'email' => 'consumidorfinal@gmail.com',
                    'telefono' => null,
                    'idCompany' => KeyUtil::idCompany(),
                    'idTipoTercero' => TipoTercero::CLIENTE
                ]
            );
            $idTercero = $tercero->id;
        }

        $valorVenta = $producto->ultimoHistorialPrecio->ValorVenta ?? 0;

        if (!$idShoppingCart) {
            $carritoExistente = ShoppingCart::where('idTercero', $idTercero)
                ->where('estado', StatusCartType::PENDIENTE)
                ->whereDate('created_at', now()->toDateString())
                ->first();

            if ($carritoExistente) {
                $shoppingCart = $carritoExistente;
            }
        }

        if ($idShoppingCart) {
            $shoppingCart = ShoppingCart::find($idShoppingCart);

            if (!$request->input('idTercero') && $shoppingCart) {
                $idTercero = $shoppingCart->idTercero;
            }
        }

        if (!isset($shoppingCart) || !$shoppingCart) {
            $shoppingCart = ShoppingCart::create([
                'estado' => StatusCartType::PENDIENTE,
                'origen' => 'PUNTO POS',
                'idTercero' => $idTercero,
                'idCompany' => KeyUtil::idCompany(),
            ]);
        }

        $asignacion = new AsignacionCarritoProducto();
        $asignacion->idShoppingCart = $shoppingCart->id;
        $asignacion->idProducto = $producto->id;
        $asignacion->cantidad = 1;
        $asignacion->valorUnitario = $valorVenta;
        $asignacion->save();

        return response()->json([
            'shoppingCart' => $shoppingCart,
            'producto' => $producto,
            'asignacion' => $asignacion
        ]);
    }




    public function storeShoppingCartService(Request $request)
    {
        $idTercero = $request->input('idTercero');
        $idShoppingCart = $request->input('idShoppingCart');

        if (!$idTercero) {
            $tercero = Tercero::firstOrCreate(
                ['identificacion' => '2222222222'],
                [
                    'nombre' => 'CONSUMIDOR FINAL',
                    'email' => 'consumidorfinal@gmail.com',
                    'telefono' => null,
                    'idCompany' => KeyUtil::idCompany(),
                    'idTipoTercero' => TipoTercero::CLIENTE
                ]
            );
            $idTercero = $tercero->id;
        }

        $codigo = $request->input('codigo');
        $idTipoArticulo = $request->input('idTipoArticulo');
        $observacionArticulo = $request->input('observacionArticulo');

        if (!$idShoppingCart) {
            $carritoExistente = ShoppingCart::where('idTercero', $idTercero)
                ->where('estado', StatusCartType::PENDIENTE)
                ->whereDate('created_at', now()->toDateString())
                ->first();

            if ($carritoExistente) {
                return response()->json(['error' => 'Ya existe un carrito pendiente para este cliente hoy'], 400);
            }
        }

        if ($codigo && $idTipoArticulo) {
            $articuloServicio = ArticuloServicio::where('codigo', $codigo)
                ->where('idTipoArticulo', $idTipoArticulo)
                ->first();

            if ($articuloServicio) {
                if ($observacionArticulo && $articuloServicio->observacion !== $observacionArticulo) {
                    $articuloServicio->observacion = $observacionArticulo;
                    $articuloServicio->save();
                }
            } else {
                $articuloServicio = new ArticuloServicio();
                $articuloServicio->codigo = $codigo;
                $articuloServicio->observacion = $observacionArticulo;
                $articuloServicio->idTipoArticulo = $idTipoArticulo;
                $articuloServicio->save();
            }
        }

        $idPrestacionServicio = $request->input('idPrestacionServicio');

        if ($idPrestacionServicio) {
            $prestacionServicio = PrestacionServicio::find($idPrestacionServicio);

            if (!$prestacionServicio) {
                return response()->json(['error' => 'Prestación de servicio no encontrada'], 404);
            }
        } else {
            $prestacionServicio = new PrestacionServicio();
            $prestacionServicio->estado = 'PENDIENTE';
            $prestacionServicio->inicioServicio = now()->format('Y-m-d H:i:s');
            $prestacionServicio->valorTotalServicios = 0;
            $prestacionServicio->idArticuloServicio = $articuloServicio->id ?? null;
            $prestacionServicio->save();
        }

        $servicio = Servicio::find($request->idServicio);

        if (!$servicio) {
            return response()->json(['error' => 'Servicio no encontrado'], 404);
        }

        $ivaActivo = $request->input('ivaActivo');
        $valorIvaServicio = $ivaActivo ? $servicio->valor * 0.19 : 0;

        $detalleServicio = new DetalleServicio();
        $detalleServicio->idServicio = $servicio->id;
        $detalleServicio->valor = $servicio->valor;
        $detalleServicio->valorIvaServicio = $valorIvaServicio;
        $detalleServicio->idPrestacionServicio = $prestacionServicio->id;
        $detalleServicio->save();

        if ($idShoppingCart) {
            $shoppingCart = ShoppingCart::find($idShoppingCart);
            if (!$shoppingCart) {
                return response()->json(['error' => 'Carrito no encontrado'], 404);
            }

            if (!$request->input('idTercero')) {
                $idTercero = $shoppingCart->idTercero;
            }
        } else {
            $shoppingCart = ShoppingCart::create([
                'estado' => StatusCartType::PENDIENTE,
                'origen' => 'PUNTO POS',
                'idTercero' => $idTercero,
                'idCompany' => KeyUtil::idCompany(),
            ]);
        }

        $asignacion = new AsignacionCarritoProducto();
        $asignacion->idShoppingCart = $shoppingCart->id;
        $asignacion->idDetalleServicio = $detalleServicio->id;
        $asignacion->valorUnitario = $detalleServicio->valor + $detalleServicio->valorIvaServicio;
        $asignacion->save();

        $prestacionServicio->valorTotalServicios += $asignacion->valorUnitario;
        $prestacionServicio->save();

        return response()->json([
            'shoppingCart' => $shoppingCart,
            'servicio' => $servicio,
            'detalle' => $detalleServicio,
            'asignacion' => $asignacion,
            'prestacionServicio' => $prestacionServicio
        ]);
    }







    public function deleteShoppingCartService(Request $request)
    {
        DB::beginTransaction();

        try {
            $idAsignacion = $request->input('idAsignacionCarritoProducto');

            $asignacion = AsignacionCarritoProducto::find($idAsignacion);

            if (!$asignacion) {
                return response()->json(['error' => 'Asignación no encontrada'], 404);
            }

            $shoppingCartId = $asignacion->idShoppingCart;

            $idDetalle = $asignacion->idDetalleServicio;
            $asignacion->delete();

            if (!is_null($idDetalle)) {
                $detalleServicio = DetalleServicio::find($idDetalle);
                if ($detalleServicio) {
                    $idPrestacion = $detalleServicio->idPrestacionServicio;
                    $detalleServicio->delete();

                    if (!is_null($idPrestacion)) {
                        $otrosDetalles = DetalleServicio::where('idPrestacionServicio', $idPrestacion)->count();
                        if ($otrosDetalles === 0) {
                            $prestacionServicio = PrestacionServicio::find($idPrestacion);
                            if ($prestacionServicio) {
                                $prestacionServicio->delete();
                            }
                        }
                    }
                }
            }


            $asignacionesRestantes = AsignacionCarritoProducto::where('idShoppingCart', $shoppingCartId)->count();
            if ($asignacionesRestantes === 0) {
                $shoppingCart = ShoppingCart::find($shoppingCartId);
                if ($shoppingCart) {
                    $shoppingCart->delete();
                }
            }

            DB::commit();

            return response()->json([
                'message' => 'Asignación eliminada correctamente',
                'code' => 2002
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => 'Error al eliminar: ' . $e->getMessage()
            ], 500);
        }
    }






    public function getShoppingCartService(Request $request)
    {
        $idShoppingCart = $request->input('idShoppingCart');
        $idPuntoVenta = $request->input('idPuntoVenta');

        $puntoVenta = PuntoVenta::where('id', $idPuntoVenta)->first();

        if (!$puntoVenta) {
            return response()->json(['message' => 'Punto de venta no encontrado'], 404);
        }

        $almacenes = Almacen::where('idSede', $puntoVenta->idSede)->pluck('id')->toArray();

        if (empty($almacenes)) {
            return response()->json([]);
        }

        $shoppingCart = ShoppingCart::with('tercero')->find($idShoppingCart);

        if (!$shoppingCart) {
            return response()->json(['error' => 'ShoppingCart no encontrada'], 404);
        }

        $items = AsignacionCarritoProducto::where('idShoppingCart', $idShoppingCart)
            ->with([
                'detalleServicio.servicio',
                'producto' => function ($query) use ($almacenes) {
                    $query->with([
                        'ultimoHistorialPrecio',
                        'distribuciones' => function ($queryDistribuciones) use ($almacenes) {
                            $queryDistribuciones->whereIn('idAlmacenDestino', $almacenes)
                                ->where('estado', 'ACEPTADO');
                        }
                    ]);
                }
            ])
            ->get();

        $shoppingCart->items = $items;

        return response()->json($shoppingCart);
    }





    public function getShoppingCartProductsPos()
    {
        $hoy = Carbon::today();

        $shoppingCarts = ShoppingCart::with(['asignaciones.producto', 'tercero'])
            ->where('estado', 'PENDIENTE')
            ->where('origen', 'PUNTO POS')
            ->where('idCompany', KeyUtil::idCompany())
            ->whereDate('created_at', $hoy)
            ->whereHas('asignaciones', function ($query) {
                $query->whereNotNull('idProducto');
            })
            ->get();

        $filteredCarts = $shoppingCarts->filter(function ($cart) {
            foreach ($cart->asignaciones as $asignacion) {
                if (is_null($asignacion->idProducto) || !is_null($asignacion->idDetalleServicio)) {
                    return false;
                }
            }
            return true;
        })->values();

        if ($filteredCarts->isEmpty()) {
            return response()->json(['error' => 'No hay carritos de compras con productos asignados'], 404);
        }

        return response()->json($filteredCarts);
    }


    public function storeIvaShopping(Request $request, $id)
    {
        $shoppingCart = ShoppingCart::find($id);

        if (!$shoppingCart) {
            return response()->json(['error' => 'Carrito no encontrado'], 404);
        }
        $shoppingCart->iva = $request->iva;
        $shoppingCart->save();
    }


    public function countShoppingCartProductsPos()
    {
        $hoy = Carbon::today();

        $shoppingCarts = ShoppingCart::with(['asignaciones.producto', 'tercero'])
            ->where('estado', 'PENDIENTE')
            ->where('origen', 'PUNTO POS')
            ->where('idCompany', KeyUtil::idCompany())
            ->whereDate('created_at', $hoy)
            ->whereHas('asignaciones', function ($query) {
                $query->whereNotNull('idProducto');
            })
            ->get();

        $filteredCount = $shoppingCarts->filter(function ($cart) {
            foreach ($cart->asignaciones as $asignacion) {
                if (is_null($asignacion->idProducto) || !is_null($asignacion->idDetalleServicio)) {
                    return false;
                }
            }
            return true;
        })->count();

        return response()->json($filteredCount);
    }


    public function getArticuloService($id)
    {
        $articuloServicio = ArticuloServicio::where('id', $id)
            ->first();

        if (!$articuloServicio) {
            return response()->json(['error' => 'Artículo de servicio no encontrado'], 404);
        }

        return response()->json($articuloServicio);
    }


    public function getMultimediaArticuloServicio($id)
    {
        $multimediaArticulos = MultimediaArticulos::where('idArticuloServicio', $id)

            ->get();

        if (!$multimediaArticulos) {
            return response()->json(['error' => 'Multimedia de articulo servicio no encontrado'], 404);
        }

        return response()->json($multimediaArticulos);
    }



    public function storeMultimediaArticuloServicio(Request $request)
    {
        if ($request->hasFile('file')) {
            $image = $request->file('file');

            if ($image->isValid()) {
                $path = $image->store('articulo_servicio', ['disk' => 'public']);

                MultimediaArticulos::create([
                    'idArticuloServicio' => $request->idArticuloServicio,
                    'observacion'        => $request->observacion,
                    'url'                => Storage::url($path),
                ]);
            }
        }
    }


    public function deleteMultimediaArticuloServicio($id)
    {
        $multimedia = MultimediaArticulos::findOrFail($id);
        $multimedia->delete();

        return response()->json([], 204);
    }
}
