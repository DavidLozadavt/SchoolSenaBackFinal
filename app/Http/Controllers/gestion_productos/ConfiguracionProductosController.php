<?php

namespace App\Http\Controllers\gestion_productos;

use App\Http\Controllers\Controller;
use App\Models\Almacen;
use App\Models\DistribucionProducto;
use App\Models\HistorialPrecio;
use App\Models\Producto;
use App\Models\Nomina\Sede;
use App\Util\KeyUtil;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ConfiguracionProductosController extends Controller
{
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
        $ivaSi = $request->input('ivaSi');
        $porcentajeIva = $request->input('porcentajeIva');

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
                    $query->where('nombre', 'Sede Principal');
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

        $producto->ivaSi = ($ivaSi === 'SI') ? 1 : 0;
        $producto->porcentajeIva = $porcentajeIva ?? 0;
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


    public function editarCamposProducto(Request $request, $id)
    {
        try {
            $producto = Producto::find($id);

            if (!$producto) {
                return response()->json([
                    'success' => false,
                    'message' => 'Producto no encontrado'
                ], 404);
            }

            $valorCompra = $request->input('valorCompra');
            $valorVenta = $request->input('valorVenta');

            if ($valorVenta !== null) {
                $producto->valorVenta = $valorVenta;
                $producto->save();
            }

            if ($valorCompra !== null || $valorVenta !== null) {
                $historial = new HistorialPrecio();
                $historial->idProducto = $producto->id;
                $historial->idUser = auth()->id();
                $historial->idCompany = KeyUtil::idCompany();
                $historial->valorCompra = $valorCompra ?? 0;
                $historial->valorVenta = $valorVenta ?? $producto->valorVenta;
                $historial->porcentajeUtilidad = $valorCompra > 0 && $valorVenta !== null
                    ? round((($valorVenta - $valorCompra) / $valorCompra) * 100, 2)
                    : 0;
                $historial->fechaActualizacion = now();
                $historial->save();
            }

            return response()->json([
                'success' => true,
                'message' => 'Campos actualizados correctamente',
                'producto' => $producto
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar los campos',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


    private function storeUrlProducto($archivo)
    {
        if ($archivo) {
            return '/storage/' . $archivo->store(Producto::RUTA_PRODUCTO, ['disk' => 'public']);
        }
        return Producto::RUTA_PRODUCTO_DEFAULT;
    }


    public function storeProductoIndividual(Request $request)
    {
        DB::beginTransaction();

        try {
            $idUser = auth()->user()->id;

            // Validación básica
            $validated = $request->validate([
                'caracteristicas' => 'nullable|string',
                'idTipoProducto'  => 'required|integer',
                'idCategoria'     => 'required|integer',
                'idMarca'         => 'required|integer',
                'idMedida'        => 'required|integer',
                'modelo'          => 'required|string',
                'serial'          => 'nullable|string',
                'cantidad'        => 'required|integer|min:1',
                'valor'           => 'required|numeric',
                'impuesto'        => 'nullable|numeric',
                'imagen'          => 'nullable|file|image|max:2048',
            ]);

            // Crear producto
            $producto = new Producto();
            $producto->caracteristicas = $validated['caracteristicas'] ?? null;
            $producto->idTipoProducto = $validated['idTipoProducto'];
            $producto->idCategoria = $validated['idCategoria'];
            $producto->idMarca = $validated['idMarca'];
            $producto->idMedida = $validated['idMedida'];
            $producto->modelo = $validated['modelo'];
            $producto->serial = $validated['serial'] ?? null;
            $producto->cantidad = $validated['cantidad'];
            $producto->estado = 'DISPONIBLE';

            // Imagen
            if ($request->hasFile('imagen')) {
                $archivo = $request->file('imagen');
                $producto->urlProducto = $this->storeUrlProducto($archivo);
            } else {
                $producto->urlProducto = Producto::RUTA_PRODUCTO_DEFAULT;
            }

            $producto->save(); // Aquí se genera el idProducto automáticamente
            $producto->codigoProducto = $producto->id . '_' . ($producto->serial ?? 'SN');
            $producto->save();

            // Crear historial de precios
            $historialPrecio = new HistorialPrecio();
            $historialPrecio->valorCompra = $validated['valor'];
            $historialPrecio->idProducto = $producto->id;
            $historialPrecio->idUser = $idUser;
            $historialPrecio->idCompany = KeyUtil::idCompany();
            $historialPrecio->fechaActualizacion = now();
            $historialPrecio->save();


            $idCompany = KeyUtil::idCompany();
            $sede = Sede::firstOrCreate(
                ['nombre' => 'Sede Principal'],
                ['estado' => 'ACTIVA']
            );
            $almacen = Almacen::firstOrCreate(
                ['nombreAlmacen' => 'Almacén Principal', 'idSede' => $sede->id],
                ['direccion' => 'Cambiar', 'estado' => 'ACTIVO', 'descripcion' => 'Almacén Principal']
            );

            $distribucion = new DistribucionProducto();
            $distribucion->idAlmacenOrigen = $almacen->id;
            $distribucion->idAlmacenDestino = $almacen->id;
            $distribucion->idProducto = $producto->id;
            $distribucion->estado = 'ACEPTADO';
            $distribucion->fechaTraslado = now();
            $distribucion->cantidad = $producto->cantidad;
            $distribucion->idCompany = $idCompany;
            $distribucion->save();

            DB::commit();

            return response()->json(['message' => 'Producto creado correctamente', 'producto' => $producto], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Error interno del servidor', 'detalle' => $e->getMessage()], 500);
        }
    }


    public function getHistorialPrecios($idProducto)
    {
        $historialPrecios = HistorialPrecio::with('producto')
            ->where('idProducto', $idProducto)
            ->orderBy('fechaActualizacion', 'asc')
            ->get();

        return response()->json($historialPrecios);
    }


    public function productosConHistorial()
    {
        $productos = Producto::whereHas('historialPrecios')->get(['id', 'modelo', 'caracteristicas', 'urlProducto']);
        return response()->json($productos);
    }
}
