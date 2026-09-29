<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Permisos del módulo de Planes de Mensajes.
 *
 *  - GESTION_SOLICITUDES_PLANES: módulo del Administrador VT (aprueba/rechaza
 *    solicitudes y administra el catálogo de planes). Aparece en el sidebar.
 *  - No se crea permiso para comprar un plan: CUALQUIER usuario autenticado puede
 *    consultar su saldo y solicitar un plan (el plan es del usuario, no del rol).
 */
return new class extends Migration
{
    public function up(): void
    {
        try {
            $permission = Permission::firstOrCreate(
                ['name' => 'GESTION_SOLICITUDES_PLANES', 'guard_name' => 'web'],
                [
                    'description' => 'Solicitudes de Planes',
                    'icon' => 'dollar',
                    'path' => '/solicitudes-planes',
                ]
            );

            $roles = Role::whereIn('name', ['ADMINISTRADOR VT'])->get();
            foreach ($roles as $rol) {
                $rol->givePermissionTo($permission);
            }

            if ($roles->isEmpty()) {
                Log::warning('Migración planes de mensajes: rol ADMINISTRADOR VT no encontrado, asignar GESTION_SOLICITUDES_PLANES manualmente.');
            }
        } catch (\Exception $e) {
            Log::warning('No se pudo crear/asignar el permiso GESTION_SOLICITUDES_PLANES: ' . $e->getMessage());
        }
    }

    public function down(): void
    {
        try {
            Permission::where('name', 'GESTION_SOLICITUDES_PLANES')->first()?->delete();
        } catch (\Exception $e) {
            // Ignore
        }
    }
};
