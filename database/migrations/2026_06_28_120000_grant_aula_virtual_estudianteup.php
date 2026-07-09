<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const PERMISSIONS = [
        'AULA_VIRTUAL_APRENDIZ',
        'AULA_VIRTUAL_APRENDIZ_CLASES',
        'AULA_VIRTUAL_APRENDIZ_ACTIVIDADES',
        'AULA_VIRTUAL_APRENDIZ_GRUPOS',
        'AULA_VIRTUAL_APRENDIZ_BIBLIOTECA',
    ];

    public function up(): void
    {
        $roleId = DB::table('roles')->where('name', 'ESTUDIANTEUP')->value('id');
        if (!$roleId) {
            return;
        }

        foreach (self::PERMISSIONS as $name) {
            $permissionId = DB::table('permissions')->where('name', $name)->value('id');
            if (!$permissionId) {
                continue;
            }

            $exists = DB::table('role_has_permissions')
                ->where('role_id', $roleId)
                ->where('permission_id', $permissionId)
                ->exists();

            if (!$exists) {
                DB::table('role_has_permissions')->insert([
                    'role_id' => $roleId,
                    'permission_id' => $permissionId,
                ]);
            }
        }
    }

    public function down(): void
    {
        $roleId = DB::table('roles')->where('name', 'ESTUDIANTEUP')->value('id');
        if (!$roleId) {
            return;
        }

        $permissionIds = DB::table('permissions')
            ->whereIn('name', self::PERMISSIONS)
            ->pluck('id');

        DB::table('role_has_permissions')
            ->where('role_id', $roleId)
            ->whereIn('permission_id', $permissionIds)
            ->delete();
    }
};
