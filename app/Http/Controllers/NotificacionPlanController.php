<?php

namespace App\Http\Controllers;

use App\Models\NotificacionPlan;
use Illuminate\Http\Request;

/**
 * Notificaciones in-app del módulo de Planes de Mensajes (Mejora 6).
 *
 * Cada usuario solo accede a las suyas: todas las consultas filtran por
 * `auth()->id()`, nunca por un id recibido del frontend.
 */
class NotificacionPlanController extends Controller
{
    public function index(Request $request)
    {
        try {
            $query = NotificacionPlan::where('userId', auth()->id())->orderByDesc('id');

            if ($request->boolean('soloNoLeidas')) {
                $query->noLeidas();
            }

            return response()->json([
                'notificaciones' => $query->limit(min((int) $request->get('limite', 30), 100))->get(),
                'noLeidas'       => NotificacionPlan::where('userId', auth()->id())->noLeidas()->count(),
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al obtener las notificaciones: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Contador para el indicador de la campana.
     */
    public function noLeidas()
    {
        try {
            return response()->json([
                'noLeidas' => NotificacionPlan::where('userId', auth()->id())->noLeidas()->count(),
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al contar las notificaciones: ' . $e->getMessage()], 500);
        }
    }

    public function marcarLeida($id)
    {
        try {
            $notificacion = NotificacionPlan::where('userId', auth()->id())->find($id);

            if (!$notificacion) {
                return response()->json(['error' => 'Notificación no encontrada.'], 404);
            }

            if (!$notificacion->leidaEn) {
                $notificacion->update(['leidaEn' => now()]);
            }

            return response()->json(['message' => 'Notificación marcada como leída.'], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al marcar la notificación: ' . $e->getMessage()], 500);
        }
    }

    public function marcarTodasLeidas()
    {
        try {
            $actualizadas = NotificacionPlan::where('userId', auth()->id())
                ->whereNull('leidaEn')
                ->update(['leidaEn' => now()]);

            return response()->json([
                'message'      => 'Notificaciones marcadas como leídas.',
                'actualizadas' => $actualizadas,
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al marcar las notificaciones: ' . $e->getMessage()], 500);
        }
    }
}
