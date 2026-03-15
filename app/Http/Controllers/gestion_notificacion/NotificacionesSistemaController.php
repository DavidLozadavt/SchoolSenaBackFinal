<?php

namespace App\Http\Controllers\gestion_notificacion;

use App\Http\Controllers\Controller;
use App\Models\NotificacionSistema;
use App\Util\KeyUtil;
use Illuminate\Http\Request;

class NotificacionesSistemaController extends Controller
{
    public function indexByUser(Request $request)
    {
        // Atrapar el usuario actual
        $user = KeyUtil::user();

        if (!$user) {
            return response()->json([
                'message' => 'Usuario no autenticado'
            ], 401);
        }

        // Obtener notificaciones del usuario, ordenadas por fecha/hora descendente
        $notificaciones = NotificacionSistema::with([
            'usuarioRemitente.persona',
            'empresa',
            'tipoNotificacion',
        ])
            ->where('idUsuarioReceptor', $user->id)
            ->orderBy('fecha', 'desc')
            ->orderBy('hora', 'desc')
            ->get();

        return response()->json([
            'message' => 'Notificaciones obtenidas con éxito',
            'data' => $notificaciones
        ]);
    }
    public function update(Request $request, $id)
    {
        $user = KeyUtil::user();

        if (!$user) {
            return response()->json([
                'message' => 'Usuario no autenticado'
            ], 401);
        }

        $notificacion = NotificacionSistema::where('id', $id)
            ->where('idUsuarioReceptor', $user->id) // ✅ solo puede editar sus propias notificaciones
            ->first();

        if (!$notificacion) {
            return response()->json([
                'message' => 'Notificación no encontrada'
            ], 404);
        }

        $notificacion->estado_id = $request->estado_id;
        $notificacion->save();

        return response()->json([
            'message' => 'Notificación actualizada',
            'data' => $notificacion
        ]);
    }
}
