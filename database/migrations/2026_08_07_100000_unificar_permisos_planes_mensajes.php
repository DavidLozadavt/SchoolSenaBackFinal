<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Unifica los permisos del módulo de Planes de Mensajes en uno solo.
 *
 * Antes existían cinco permisos (uno por pestaña), lo que provocaba que a un
 * rol le faltara alguno y esa parte del módulo fallara en silencio: la pestaña
 * no aparecía o devolvía 403 al cargar.
 *
 * A partir de aquí, GESTION_SOLICITUDES_PLANES cubre TODO el módulo. Los
 * cuatro permisos restantes se eliminan; cualquier rol que tuviera alguno de
 * ellos recibe el permiso unificado para no perder acceso.
 */
return new class extends Migration
{
    private const OBSOLETOS = [
        'GESTION_PLANES_MENSAJES',
        'GESTION_DASHBOARD_PLANES',
        'GESTION_HISTORIAL_FACTURACION',
        'GESTION_CONFIGURACION_PAGOS',
    ];

    public function up(): void
    {
        try {
            $unificado = Permission::firstOrCreate(
                ['name' => 'GESTION_SOLICITUDES_PLANES', 'guard_name' => 'web'],
                [
                    'description' => 'Planes de Mensajes',
                    'icon' => 'dollar',
                    'path' => '/solicitudes-planes',
                ]
            );

            // Cualquier rol que tuviera uno de los permisos antiguos conserva el
            // acceso a través del permiso unificado.
            foreach (self::OBSOLETOS as $nombre) {
                $permiso = Permission::where('name', $nombre)->first();

                if (!$permiso) {
                    continue;
                }

                foreach (Role::all() as $rol) {
                    if ($rol->hasPermissionTo($permiso) && !$rol->hasPermissionTo($unificado)) {
                        $rol->givePermissionTo($unificado);
                    }
                }

                // Al eliminarlo, Spatie limpia también role_has_permissions.
                $permiso->delete();
            }

            app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        } catch (\Exception $e) {
            Log::warning('No se pudieron unificar los permisos de planes de mensajes: ' . $e->getMessage());
        }
    }

    public function down(): void
    {
        try {
            // Recrea los permisos antiguos (como pestañas, sin path) y se los
            // devuelve a los roles que tengan el permiso unificado.
            $unificado = Permission::where('name', 'GESTION_SOLICITUDES_PLANES')->first();

            foreach (self::OBSOLETOS as $nombre) {
                $permiso = Permission::firstOrCreate(
                    ['name' => $nombre, 'guard_name' => 'web'],
                    ['description' => $nombre, 'icon' => null, 'path' => null]
                );

                if (!$unificado) {
                    continue;
                }

                foreach (Role::all() as $rol) {
                    if ($rol->hasPermissionTo($unificado)) {
                        $rol->givePermissionTo($permiso);
                    }
                }
            }

            app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        } catch (\Exception $e) {
            // Ignore
        }
    }
};
