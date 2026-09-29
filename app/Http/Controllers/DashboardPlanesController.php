<?php

namespace App\Http\Controllers;

use App\Models\MensajesPlan;
use App\Models\SolicitudPlanMensaje;
use App\Models\WompiTransaccion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Dashboard del Administrador VT para el módulo de Planes de Mensajes (Mejora 7).
 *
 * Solo lectura: agrega datos ya existentes, no modifica nada. Protegido por el
 * permiso GESTION_DASHBOARD_PLANES en las rutas.
 */
class DashboardPlanesController extends Controller
{
    public function resumen(Request $request)
    {
        try {
            $desde = $request->get('desde')
                ? \Carbon\Carbon::parse($request->get('desde'))->startOfDay()
                : now()->subYear()->startOfDay();
            $hasta = $request->get('hasta')
                ? \Carbon\Carbon::parse($request->get('hasta'))->endOfDay()
                : now()->endOfDay();

            $aprobadas = SolicitudPlanMensaje::where('estado', SolicitudPlanMensaje::APROBADA)
                ->whereBetween('created_at', [$desde, $hasta]);

            return response()->json([
                'rango' => ['desde' => $desde->toDateString(), 'hasta' => $hasta->toDateString()],

                'tarjetas' => [
                    'planesActivos'        => MensajesPlan::where('activo', true)->count(),
                    'planesVendidos'       => (clone $aprobadas)->count(),
                    'ingresos'             => (float) (clone $aprobadas)->sum('valor'),
                    'solicitudesPendientes' => SolicitudPlanMensaje::whereIn(
                        'estado',
                        SolicitudPlanMensaje::ESTADOS_REVISABLES
                    )->count(),
                    'pagosAprobados'       => WompiTransaccion::where('status', WompiTransaccion::APPROVED)
                        ->whereBetween('created_at', [$desde, $hasta])->count(),
                    'pagosRechazados'      => WompiTransaccion::whereIn('status', [
                        WompiTransaccion::DECLINED,
                        WompiTransaccion::VOIDED,
                        WompiTransaccion::ERROR,
                    ])->whereBetween('created_at', [$desde, $hasta])->count(),
                    'mensajesVendidos'     => (int) (clone $aprobadas)->sum('cantidadMensajes'),
                    'mensajesConsumidos'   => (int) DB::table('usuarioMensajesSaldos')->sum('mensajesConsumidos'),
                ],

                'topPlanes'        => $this->topPlanes($desde, $hasta),
                'topUsuarios'      => $this->topUsuarios(),
                'porMes'           => $this->porMes($desde, $hasta),
                'porMetodoPago'    => $this->porMetodoPago($desde, $hasta),
                'porEstadoPago'    => $this->porEstadoPago($desde, $hasta),
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al construir el dashboard: ' . $e->getMessage()], 500);
        }
    }

    /** Top 10 planes más vendidos (solicitudes aprobadas). */
    private function topPlanes($desde, $hasta): array
    {
        return SolicitudPlanMensaje::where('estado', SolicitudPlanMensaje::APROBADA)
            ->whereBetween('created_at', [$desde, $hasta])
            ->select('planNombre', DB::raw('COUNT(*) as ventas'), DB::raw('SUM(valor) as ingresos'))
            ->groupBy('planNombre')
            ->orderByDesc('ventas')
            ->limit(10)
            ->get()
            ->toArray();
    }

    /** Usuarios con mayor consumo de mensajes. */
    private function topUsuarios(): array
    {
        return DB::table('usuarioMensajesSaldos as s')
            ->leftJoin('usuario as u', 'u.id', '=', 's.userId')
            ->leftJoin('persona as p', 'p.id', '=', 'u.idpersona')
            ->select([
                's.userId',
                'u.email',
                DB::raw("TRIM(CONCAT_WS(' ', p.nombre1, p.nombre2, p.apellido1, p.apellido2)) as nombre"),
                's.mensajesConsumidos',
                's.mensajesDisponibles',
            ])
            ->where('s.mensajesConsumidos', '>', 0)
            ->orderByDesc('s.mensajesConsumidos')
            ->limit(10)
            ->get()
            ->toArray();
    }

    /** Ventas e ingresos por mes. */
    private function porMes($desde, $hasta): array
    {
        return SolicitudPlanMensaje::where('estado', SolicitudPlanMensaje::APROBADA)
            ->whereBetween('created_at', [$desde, $hasta])
            ->select(
                DB::raw("DATE_FORMAT(created_at, '%Y-%m') as mes"),
                DB::raw('COUNT(*) as ventas'),
                DB::raw('SUM(valor) as ingresos'),
                DB::raw('SUM(cantidadMensajes) as mensajes')
            )
            ->groupBy('mes')
            ->orderBy('mes')
            ->get()
            ->toArray();
    }

    /** Transacciones agrupadas por método de pago. */
    private function porMetodoPago($desde, $hasta): array
    {
        return WompiTransaccion::whereBetween('created_at', [$desde, $hasta])
            ->select(
                DB::raw("COALESCE(paymentMethodType, 'NO_INFORMADO') as metodo"),
                DB::raw('COUNT(*) as transacciones'),
                DB::raw('SUM(amount) as total')
            )
            ->groupBy('metodo')
            ->orderByDesc('transacciones')
            ->get()
            ->toArray();
    }

    /** Transacciones agrupadas por estado de Wompi. */
    private function porEstadoPago($desde, $hasta): array
    {
        return WompiTransaccion::whereBetween('created_at', [$desde, $hasta])
            ->select('status', DB::raw('COUNT(*) as transacciones'), DB::raw('SUM(amount) as total'))
            ->groupBy('status')
            ->orderByDesc('transacciones')
            ->get()
            ->toArray();
    }
}
