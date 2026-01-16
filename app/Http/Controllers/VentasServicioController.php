<?php

namespace App\Http\Controllers;

use App\Enums\StatusCartType;
use App\Models\ArticuloServicio;
use App\Models\AsignacionCarritoProducto;
use App\Models\DetalleServicio;
use App\Models\PrestacionServicio;
use App\Models\Producto;
use App\Models\Servicio;
use App\Models\ShoppingCart;
use App\Models\Tercero;
use App\Models\TipoTercero;
use App\Util\KeyUtil;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class VentasServicioController extends Controller
{


    public function storeShoppingCartService(Request $request)
    {
        $idTercero = $request->input('idTercero');
        $idShoppingCart = $request->input('idShoppingCart');

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
                ['identificacion' => '222222222222'],
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

            $asignaciones = AsignacionCarritoProducto::where('idShoppingCart', $shoppingCartId)->get();

            foreach ($asignaciones as $asignacionItem) {
                $idDetalle = $asignacionItem->idDetalleServicio;

                $asignacionItem->delete();

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
            }


            $shoppingCart = ShoppingCart::find($shoppingCartId);

            if ($shoppingCart) {
                $shoppingCart->delete();
            }

            DB::commit();

            return response()->json([
                'message' => 'Carrito y todas sus asignaciones eliminados correctamente',
                'code' => 2002
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => 'Error al eliminar: ' . $e->getMessage()
            ], 500);
        }
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
}
