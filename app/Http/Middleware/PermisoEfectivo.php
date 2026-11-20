<?php

namespace App\Http\Middleware;

use App\Util\PermisosEfectivos;
use Closure;
use Illuminate\Http\Request;

/**
 * Exige al menos uno de los permisos indicados, según los permisos EFECTIVOS
 * del usuario (base de datos, en vivo).
 *
 * Uso: ->middleware('permiso.efectivo:GESTION_EVENTOS|GESTION_USUARIO')
 *
 * Se aplica con los MISMOS permisos que exige la pantalla del front
 * (ProtectedRoute), así que ningún usuario que hoy usa esa pantalla pierde
 * acceso: sólo deja de ser posible llamar el endpoint sin tenerlos.
 *
 * El middleware `permission` existente (Permisions) lee la sesión web y no
 * funciona con la API JWT; por eso este es aparte.
 */
class PermisoEfectivo
{
    public function handle(Request $request, Closure $next, string $permisos)
    {
        $lista = array_filter(array_map('trim', explode('|', $permisos)));
        if (!auth()->check()) {
            return response()->json(['error' => 'No autenticado'], 401);
        }
        if (!PermisosEfectivos::alguno($lista)) {
            return response()->json(['error' => 'No tienes permiso para esta operación', 'permisos' => $lista], 403);
        }
        return $next($request);
    }
}
