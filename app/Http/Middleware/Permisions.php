<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Tymon\JWTAuth\Facades\JWTAuth;

class Permisions
{
    /**
     * Handle an incoming request.
     *
     * Autoriza según los permisos del usuario. Fuente canónica: el payload del JWT
     * (igual que KeyUtil::permissions()); con respaldo a la sesión por compatibilidad.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string|array  $permissionsList  permisos requeridos (separados por '|')
     * @return mixed
     */
    public function handle(Request $request, Closure $next, $permissionsList)
    {
        $required = is_array($permissionsList)
            ? $permissionsList
            : explode('|', $permissionsList);

        $userPermissions = $this->resolveUserPermissions();

        foreach ($required as $permission) {
            $permission = (string) $permission;
            if ($permission !== '' && in_array($permission, $userPermissions, true)) {
                return $next($request);
            }
        }

        abort(403, 'No tiene permisos para acceder a este recurso.');
    }

    /**
     * Obtiene la lista de nombres de permisos del usuario autenticado.
     *
     * @return array<int, string>
     */
    private function resolveUserPermissions(): array
    {
        // 1) Desde el JWT (fuente canónica del proyecto).
        try {
            $perms = JWTAuth::parseToken()->getPayload()->get('permissions');
            $names = $this->normalizePermissionNames($perms);
            if (!empty($names)) {
                return $names;
            }
        } catch (\Throwable $e) {
            // Sin token válido: se intenta el respaldo de sesión.
        }

        // 2) Respaldo: sesión (string con los nombres concatenados).
        $sessionPerms = Session::get('permissions');
        if (is_string($sessionPerms) && trim($sessionPerms) !== '') {
            return preg_split('/[\s,|]+/', trim($sessionPerms), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        }
        if (is_array($sessionPerms)) {
            return $this->normalizePermissionNames($sessionPerms);
        }

        return [];
    }

    /**
     * Normaliza la estructura de permisos (lista de nombres o mapa nombre=>valor) a
     * una lista simple de nombres.
     *
     * @param  mixed  $perms
     * @return array<int, string>
     */
    private function normalizePermissionNames($perms): array
    {
        if ($perms instanceof \Illuminate\Support\Collection) {
            $perms = $perms->all();
        }

        if (!is_array($perms) || $perms === []) {
            return [];
        }

        // Mapa asociativo (nombre => algo) → usar las claves.
        $isAssoc = array_keys($perms) !== range(0, count($perms) - 1);
        $names = $isAssoc ? array_keys($perms) : array_values($perms);

        return array_values(array_filter(array_map(
            fn ($v) => is_string($v) ? $v : (is_scalar($v) ? (string) $v : null),
            $names
        )));
    }
}
