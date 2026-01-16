<?php

namespace App\Http\Controllers\gestion_almacen;

use App\Http\Controllers\Controller;
use App\Models\Almacen;
use App\Models\DetalleFactura;
use App\Models\DetalleProducto;
use App\Models\DistribucionProducto;
use App\Models\EstadoSolicitud;
use App\Models\Notificacion;
use App\Models\Producto;
use App\Models\Status;
use App\Util\KeyUtil;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;

class AlmacenController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $almacenes = Almacen::withCount('distribucionProductos')
            ->whereHas('sede', function ($query) {
            })
            ->get();

        $response = $almacenes->map(function ($almacen) {
            return [
                'id' => $almacen->id,
                'nombreAlmacen' => $almacen->nombreAlmacen,
                'direccion' => $almacen->direccion,
                'estado' => $almacen->estado,
                'descripcion' => $almacen->descripcion,
                'idSede' => $almacen->idSede,
                'cantidadProductos' => $almacen->distribucion_productos_count
            ];
        });

        return response()->json($response);
    }



    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        DB::beginTransaction();

        try {
            $almacen = new Almacen();
            $almacen->nombreAlmacen = $request->input('nombreAlmacen');
            $almacen->direccion = $request->input('direccion');
            $almacen->idSede = $request->input('idSede');
            $almacen->descripcion = $request->input('descripcion');
            $almacen->estado = 'ACTIVO';
            $almacen->save();

            DB::commit();

            return response()->json($almacen, 201);
        } catch (\Exception $e) {

            DB::rollBack();

            return response()->json(['error' => 'Error interno del servidor.'], 500);
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        DB::beginTransaction();

        try {

            $almacen = Almacen::findOrFail($id);

            $almacen->nombreAlmacen = $request->input('nombreAlmacen');
            $almacen->direccion = $request->input('direccion');
            $almacen->idSede = $request->input('idSede');
            $almacen->descripcion = $request->input('descripcion');

            $almacen->save();

            DB::commit();


            return response()->json($almacen, 200);
        } catch (\Exception $e) {

            DB::rollBack();

            return response()->json(['error' => 'Error interno del servidor.'], 500);
        }
    }


    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        DB::beginTransaction();

        try {
            $almacen = Almacen::findOrFail($id);

            if ($almacen->nombreAlmacen === 'Almacén Principal') {
                return response()->json([
                    'message' => 'No se puede eliminar el Almacén Principal.'
                ], 403);
            }

            $almacen->delete();
            DB::commit();

            return response()->json(['message' => 'Almacén eliminado correctamente.'], 200);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json(['error' => 'Error interno del servidor.'], 500);
        }
    }



    public function getProductosAlmacen($id)
    {
        $distribuciones = DistribucionProducto::with('producto.medida', 'producto.marca')
            ->where('idAlmacenDestino', $id)
            ->where('estado', 'ACEPTADO')
            ->whereHas('producto', function ($query) {
                $query->whereIn('estado', ['DISPONIBLE', 'PENDIENTE', 'GARANTIA', 'DEVOLUCION'])
                    ->whereDoesntHave('tipoProducto', function ($q) {
                        $q->where('nombreTipoProducto', 'MENU');
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
            ->values();

        return response()->json($distribuciones);
    }




    public function sendProductosAlmacen(Request $request)
    {
        $idAlmacenDestino = $request->input('idAlmacen');
        $idAlmacenOrigen = $request->input('idAlmacenOrigen');
        $productos = $request->input('productos');
        $idResponsableOrigen = auth()->user()->id;

        foreach ($productos as $producto) {
            $productoCantidad = (int) $producto['cantidad'];
            $idProducto = $producto['idProducto'];

            // Sumar todas las cantidades del producto en el almacén de origen
            $cantidadTotalOrigen = DistribucionProducto::where('idProducto', $idProducto)
                ->where('idAlmacenDestino', $idAlmacenOrigen)
                ->sum('cantidad');

            if ($cantidadTotalOrigen < $productoCantidad) {
                return response()->json([
                    'message' => 'No hay suficiente stock en el almacén de origen para el producto con ID ' . $idProducto
                ], 400);
            }

            // Restar la cantidad solicitada de las distribuciones existentes en orden de antigüedad
            $cantidadRestante = $productoCantidad;
            $distribuciones = DistribucionProducto::where('idProducto', $idProducto)
                ->where('idAlmacenDestino', $idAlmacenOrigen)
                ->orderBy('fechaTraslado', 'asc') // Primero los más antiguos
                ->get();

            foreach ($distribuciones as $distribucion) {
                if ($cantidadRestante <= 0) break;

                if ($distribucion->cantidad >= $cantidadRestante) {
                    $distribucion->cantidad -= $cantidadRestante;
                    $distribucion->save();
                    $cantidadRestante = 0;
                } else {
                    $cantidadRestante -= $distribucion->cantidad;
                    $distribucion->cantidad = 0;
                    $distribucion->save();
                }
            }

            // Verificar si ya existe una distribución del producto en el almacén destino
            $distribucionDestino = DistribucionProducto::where('idProducto', $idProducto)
                ->where('idAlmacenDestino', $idAlmacenDestino)
                ->where('idAlmacenOrigen', $idAlmacenOrigen)
                ->first();

            if ($distribucionDestino) {
                // Si existe, sumamos la cantidad
                $distribucionDestino->cantidad += $productoCantidad;
                $distribucionDestino->save();
            } else {
                // Si no existe, creamos una nueva entrada
                $distribucionProducto = new DistribucionProducto();
                $distribucionProducto->idProducto = $idProducto;
                $distribucionProducto->cantidad = $productoCantidad;
                $distribucionProducto->idAlmacenDestino = $idAlmacenDestino;
                $distribucionProducto->idAlmacenOrigen = $idAlmacenOrigen;
                $distribucionProducto->estado = 'PENDIENTE';
                $distribucionProducto->fechaTraslado = now();
                $distribucionProducto->observacion = "";
                $distribucionProducto->save();
            }

            // Registrar estado de la solicitud
            EstadoSolicitud::create([
                'idResponsableOrigen' => $idResponsableOrigen,
                'estado' => 'PENDIENTE',
                'fechaInicial' => now(),
                'observacion' => 'Solicitud de traslado',
                'idDistribucionProducto' => $distribucionDestino->id ?? $distribucionProducto->id,
            ]);
        }

        return response()->json(['message' => 'Solicitud realizada correctamente'], 200);
    }
}
