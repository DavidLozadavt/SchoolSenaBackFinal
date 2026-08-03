<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Permiso del módulo de Estadísticas de Mensajes WhatsApp (solo lectura).
 * Asignado únicamente al rol administrador principal.
 */
return new class extends Migration
{
    public function up(): void
    {
        try {
            // Sin icon/path: es un permiso de gate (pestaña dentro de Seguimiento
            // de Aspirantes), no debe aparecer como item propio en el menú
            // dinámico (PermissionHierarchyController convierte cualquier
            // permiso raíz con path en un ítem de sidebar).
            $permission = Permission::firstOrCreate(
                ['name' => 'GESTION_ESTADISTICAS_WHATSAPP_SENA', 'guard_name' => 'web'],
                [
                    'description' => 'Estadísticas WhatsApp',
                    'icon' => null,
                    'path' => null,
                ]
            );

            // Mismos roles que ya tienen GESTION_SEGUIMIENTO_ASPIRANTES.
            $roles = Role::whereIn('name', ['ADMINISTRADOR VT', 'ADMIN REGIONAL', 'ADMIN CENTRO'])->get();
            foreach ($roles as $rol) {
                $rol->givePermissionTo($permission);
            }
            if ($roles->isEmpty()) {
                Log::warning('Migración estadísticas WhatsApp: ningún rol administrador encontrado, asignar el permiso manualmente.');
            }
        } catch (\Exception $e) {
            Log::warning('No se pudo crear/asignar el permiso GESTION_ESTADISTICAS_WHATSAPP_SENA: ' . $e->getMessage());
        }
    }

    public function down(): void
    {
        try {
            $permission = Permission::where('name', 'GESTION_ESTADISTICAS_WHATSAPP_SENA')->first();
            $permission?->delete();
        } catch (\Exception $e) {
            // Ignore
        }
    }
};
