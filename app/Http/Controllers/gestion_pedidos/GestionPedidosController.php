<?php

namespace App\Http\Controllers\gestion_pedidos;

use App\Enums\StatusCartType;
use App\Enums\StatusType;
use App\Http\Controllers\Controller;
use App\Models\Almacen;
use App\Models\AsignacionCarritoProducto;
use App\Models\DistribucionProducto;
use App\Models\Producto;
use App\Models\ShoppingCart;
use App\Util\KeyUtil;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class GestionPedidosController extends Controller
{
    public function getPedidos(Request $request)
    {
        $perPage = $request->input('per_page', 10);
        $searchTerm = $request->input('search', '');
        $idCompany = KeyUtil::idCompany();

        $shopping = ShoppingCart::where('idCompany', $idCompany)
            ->whereIn('estado', ['PAGO', 'ENVIADO', 'FINALIZADO', 'RECHAZADO', 'GARANTIA', 'ENVIO CLIENTE', 'ENVIO GRATIS'])
            ->with([
                'tercero',
                'asignaciones.producto.marca',
                'asignaciones.producto.medida',
                'user.persona',
                'asignaciones.producto.ultimoHistorialPrecio',
                'asignaciones.producto.distribuciones'
            ])
            ->when($searchTerm, function ($query) use ($searchTerm) {
                $query->where('id', $searchTerm)
                    ->orWhereHas('user.persona', function ($q) use ($searchTerm) {
                        $q->where('nombre1', 'like', "%$searchTerm%");
                    });
            })
            ->orderByRaw("FIELD(estado, 'PAGO', 'FINALIZADO', 'ENVIADO', 'ENVIO CLIENTE', 'ENVIO GRATIS', 'RECHAZO', 'GARANTIA'), created_at DESC")
            ->paginate($perPage);

        $shopping->getCollection()->transform(function ($cart) {
            foreach ($cart->asignaciones as $asignacion) {
                $producto = $asignacion->producto;

                if ($producto && isset($producto->distribuciones)) {
                    $cantidad = collect($producto->distribuciones)
                        ->where('estado', 'ACEPTADO')
                        ->sum('cantidad');

                    $producto->setAttribute('cantidadDistribucionesAceptadas', $cantidad);
                } else {
                    $producto?->setAttribute('cantidadDistribucionesAceptadas', 0);
                }
            }
            return $cart;
        });


        return response()->json($shopping);
    }



    public function updatePedido(Request $request, $id)
    {
        $shopping = ShoppingCart::with('asignaciones.producto.tipoProducto')->findOrFail($id);
        $shopping->estado = $request->estado;

        foreach ($shopping->asignaciones as $asignacion) {
            $producto = $asignacion->producto;

            if ($producto && $producto->tipoProducto && $producto->tipoProducto->nombreTipoProducto === 'MENU') {
                continue;
            }

            $idProducto = $asignacion->idProducto;
            $cantidadCarrito = (int) $asignacion->cantidad;

            $distribucion = DistribucionProducto::where('idProducto', $idProducto)
                ->where('estado', 'ACEPTADO')
                ->where('cantidad', '>', 0)
                ->first();

            if (!$distribucion) {
                return response()->json([
                    'message' => "No hay distribución disponible para el producto ID $idProducto."
                ], 400);
            }

            if ($distribucion->cantidad < $cantidadCarrito) {
                return response()->json([
                    'message' => "La distribución del producto ID $idProducto no tiene suficiente cantidad."
                ], 400);
            }

            $distribucion->cantidad -= $cantidadCarrito;

            if ($distribucion->cantidad === 0) {
                $distribucion->estado = 'AGOTADO';
            }

            $distribucion->save();
        }

        $shopping->save();

        return response()->json([
            'message' => 'Pedido actualizado y distribuciones ajustadas correctamente.'
        ]);
    }



    public function getPedidosPendientes(Request $request)
    {
        $perPage = $request->input('per_page', 10);
        $searchTerm = $request->input('search', '');
        $idCompany = KeyUtil::idCompany();

        $shopping = ShoppingCart::where('idCompany', $idCompany)
            ->whereIn('estado', ['PENDIENTE'])
            ->whereHas('asignaciones.producto')
            ->with([
                'tercero',
                'asignaciones.producto.marca',
                'asignaciones.producto.medida',
                'user.persona',
                'asignaciones.producto.ultimoHistorialPrecio',
                'asignaciones.producto.distribuciones'
            ])
            ->when($searchTerm, function ($query) use ($searchTerm) {
                $query->where('id', $searchTerm)
                    ->orWhereHas('user.persona', function ($q) use ($searchTerm) {
                        $q->where('nombre1', 'like', "%$searchTerm%");
                    });
            })
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        $shopping->getCollection()->transform(function ($cart) {
            foreach ($cart->asignaciones as $asignacion) {
                if ($asignacion->producto) {
                    $cantidad = isset($asignacion->producto->distribuciones)
                        ? collect($asignacion->producto->distribuciones)
                        ->where('estado', 'ACEPTADO')
                        ->sum('cantidad')
                        : 0;

                    $asignacion->producto->setAttribute('cantidadDistribucionesAceptadas', $cantidad);
                }
            }
            return $cart;
        });

        return response()->json($shopping);
    }
}