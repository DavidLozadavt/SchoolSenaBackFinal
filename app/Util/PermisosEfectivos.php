<?php

namespace App\Util;

use App\Models\ActivationCompanyUser;
use Illuminate\Support\Collection;

/**
 * Permisos efectivos del usuario autenticado en la empresa de su token.
 *
 * Se leen de la base en cada petición (roles de la activación + permisos
 * directos), no del token: un permiso revocado deja de valer en la siguiente
 * petición. Es la misma fuente que usa Nexo (`LyraController`).
 */
class PermisosEfectivos
{
    private static ?Collection $cache = null;
    private static ?string $clave = null;

    public static function actuales(): Collection
    {
        $clave = auth()->id() . ':' . KeyUtil::idCompany();
        if (self::$cache !== null && self::$clave === $clave) {
            return self::$cache;
        }
        $activacion = ActivationCompanyUser::where('company_id', KeyUtil::idCompany())
            ->where(function ($q) {
                QueryUtil::whereUser($q);
                QueryUtil::whereActive($q);
            })
            ->first();
        self::$clave = $clave;
        return self::$cache = $activacion
            ? $activacion->getAllPermissions()->pluck('name')->unique()->values()
            : collect();
    }

    /** ¿Tiene alguno de estos permisos? */
    public static function alguno(array $permisos): bool
    {
        return self::actuales()->intersect($permisos)->isNotEmpty();
    }

    /** Para pruebas o procesos largos que cambian de usuario. */
    public static function olvidar(): void
    {
        self::$cache = null;
        self::$clave = null;
    }
}
