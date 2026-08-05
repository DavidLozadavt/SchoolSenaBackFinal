<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Permisos ADITIVOS del Administrador VT:
 *
 *  - GESTION_PLANES_MENSAJES:  módulo CRUD "Planes de Mensajes" (sidebar).
 *  - GESTION_DASHBOARD_PLANES: dashboard de ventas y consumo (gate, sin path).
 *
 * GESTION_SOLICITUDES_PLANES se conserva sin cambios.
 */
return new class extends Migration
{
    public function up(): void
    {
        try {
            $planes = Permission::firstOrCreate(
                ['name' => 'GESTION_PLANES_MENSAJES', 'guard_name' => 'web'],
                [
                    'description' => 'Planes de Mensajes',
                    'icon' => 'package',
                    'path' => '/planes-mensajes',
                ]
            );

            // Sin path: es una pestaña dentro del módulo, no un ítem de sidebar.
            $dashboard = Permission::firstOrCreate(
                ['name' => 'GESTION_DASHBOARD_PLANES', 'guard_name' => 'web'],
                [
                    'description' => 'Dashboard de Planes',
                    'icon' => null,
                    'path' => null,
                ]
            );

            $roles = Role::whereIn('name', ['ADMINISTRADOR VT'])->get();
            foreach ($roles as $rol) {
                $rol->givePermissionTo($planes);
                $rol->givePermissionTo($dashboard);
            }

            if ($roles->isEmpty()) {
                Log::warning('Migración planes admin: rol ADMINISTRADOR VT no encontrado, asignar los permisos manualmente.');
            }
        } catch (\Exception $e) {
            Log::warning('No se pudieron crear los permisos de administración de planes: ' . $e->getMessage());
        }
    }

    public function down(): void
    {
        try {
            Permission::whereIn('name', ['GESTION_PLANES_MENSAJES', 'GESTION_DASHBOARD_PLANES'])
                ->get()
                ->each
                ->delete();
        } catch (\Exception $e) {
            // Ignore
        }
    }
};
