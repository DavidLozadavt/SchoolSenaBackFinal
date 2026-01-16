<?php

namespace App\Http\Controllers\gestion_almacen;

use App\Http\Controllers\Controller;
use App\Models\DistribucionProducto;
use App\Models\EstadoSolicitud;
use Carbon\Carbon;
use Illuminate\Http\Request;

class SolicitudProductoAlmacenController extends Controller
{


    public function getSolictidesProductosAlmacen()
    {

        $solicitudes = DistribucionProducto::with('producto', 'almacenOrigen', 'almacenDestino')
            ->where('estado', '!=', 'ACEPTADO')
            ->get();


        return response()->json($solicitudes);
    }


    public function aprobarProductoAlmacen($id)
    {
        try {
            $idResponsableDestino = auth()->id();


            $distribucion = DistribucionProducto::find($id);

            $distribucion->estado = 'ACEPTADO';
            $distribucion->idAlmacenOrigen = $distribucion->idAlmacenDestino;
            $distribucion->save();


            $ultimoEstado = EstadoSolicitud::where('idDistribucionProducto', $id)
                ->orderBy('created_at', 'desc')
                ->first();

            if ($ultimoEstado) {
                $ultimoEstado->update(['fechaFinal' => now()]);
            }


            EstadoSolicitud::create([
                'idDistribucionProducto' => $id,
                'estado' => 'ACEPTADO',
                'fechaInicial' => now(),
                'observacion' => 'Solicitud de translado aceptada',
                'fechaFinal' => now(),
                'idResponsableDestino' => $idResponsableDestino,
            ]);

            return response()->json(['message' => 'Solicitud aceptada correctamente'], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al actualizar producto y estado', 'error' => $e->getMessage()], 500);
        }
    }





    public function rechazarProductoAlmacen(Request $request)
    {
        $observacion = $request->input('observacion');
        $id = $request->input('idDistribucionProducto');
        $idResponsableDestino = auth()->id();
        try {

            $distribucion = DistribucionProducto::find($id);

            $distribucion->estado = 'RECHAZADO';
            $distribucion->idAlmacenDestino = $distribucion->idAlmacenOrigen;
            $distribucion->save();
    

            $ultimoEstado = EstadoSolicitud::where('idDistribucionProducto', $id)
                ->orderBy('created_at', 'desc')
                ->first();

            if ($ultimoEstado) {
                $ultimoEstado->update(['fechaFinal' => now()]);
            }


            EstadoSolicitud::create([
                'observacion' => $observacion,
                'estado' => 'RECHAZADO',
                'fechaInicial' => now(),
                'fechaFinal' => now(),
                'idDistribucionProducto' => $id,
                'idResponsableDestino' => $idResponsableDestino,
            ]);

            EstadoSolicitud::create([
                'observacion' => 'Devolución al almacen de origen',
                'estado' => 'PENDIENTE',
                'fechaInicial' => now(),
                'fechaFinal' => now(),
                'idDistribucionProducto' => $id,
                'idResponsableDestino' => $idResponsableDestino,
            ]);

            return response()->json(['message' => 'Solicitud rechazada correctamente'], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al actualizar producto y estado', 'error' => $e->getMessage()], 500);
        }
    }




    public function getTrazabilidadSolicitud($id)
    {
        $trazabilidadSolicitud = EstadoSolicitud::with('distribucionProducto')
        ->where('idDistribucionProducto', $id)
        ->orderBy('created_at', 'desc')
        ->get();

        $trazabilidadSolicitud->transform(function ($item) {
            $fechaInicial = new Carbon($item->fechaInicial);
            $fechaFinal = new Carbon($item->fechaFinal);

            $item->dias_diferencia = $fechaInicial->diffInDays($fechaFinal);

            return $item;
        });

        return response()->json($trazabilidadSolicitud);
    }
}
