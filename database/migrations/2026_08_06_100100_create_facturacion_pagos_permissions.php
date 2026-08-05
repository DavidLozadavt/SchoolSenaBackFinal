<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Permisos ADITIVOS de los dos módulos nuevos:
 *
 *  - GESTION_HISTORIAL_FACTURACION: consulta de todas las compras (Administrador VT).
 *  - GESTION_CONFIGURACION_PAGOS:   configuración global del sistema de pagos.
 *
 * Los permisos de fases anteriores se conservan sin cambios.
 */
return new class extends Migration
{
    public function up(): void
    {
        try {
            $historial = Permission::firstOrCreate(
                ['name' => 'GESTION_HISTORIAL_FACTURACION', 'guard_name' => 'web'],
                [
                    // Sin icon/path: es una PESTAÑA dentro de Solicitudes de Planes,
                    // no un módulo propio del sidebar.
                    'description' => 'Historial de Facturación',
                    'icon' => null,
                    'path' => null,
                ]
            );

            $configuracion = Permission::firstOrCreate(
                ['name' => 'GESTION_CONFIGURACION_PAGOS', 'guard_name' => 'web'],
                [
                    // Sin icon/path: pestaña dentro de Solicitudes de Planes.
                    'description' => 'Configuración de Pagos',
                    'icon' => null,
                    'path' => null,
                ]
            );

            $roles = Role::whereIn('name', ['ADMINISTRADOR VT'])->get();
            foreach ($roles as $rol) {
                $rol->givePermissionTo($historial);
                $rol->givePermissionTo($configuracion);
            }

            if ($roles->isEmpty()) {
                Log::warning('Migración facturación/pagos: rol ADMINISTRADOR VT no encontrado, asignar los permisos manualmente.');
            }
        } catch (\Exception $e) {
            Log::warning('No se pudieron crear los permisos de facturación y configuración de pagos: ' . $e->getMessage());
        }
    }

    public function down(): void
    {
        try {
            Permission::whereIn('name', ['GESTION_HISTORIAL_FACTURACION', 'GESTION_CONFIGURACION_PAGOS'])
                ->get()
                ->each
                ->delete();
        } catch (\Exception $e) {
            // Ignore
        }
    }
};
