<?php

use App\Permission\PermissionConst;
use App\Support\EduExceAuthorization;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $permisos = [
            PermissionConst::GESTION_USUARIO,
            PermissionConst::GESTION_ROLES,
            PermissionConst::GESTION_ROL_PERMISOS,
        ];

        $permissionIds = DB::table('permissions')
            ->whereIn('name', $permisos)
            ->pluck('id', 'name');

        $vtRoleIds = DB::table('roles')
            ->whereIn('name', EduExceAuthorization::vtRoleNames())
            ->pluck('id');

        foreach ($vtRoleIds as $roleId) {
            foreach ($permissionIds as $permissionId) {
                $exists = DB::table('role_has_permissions')
                    ->where('role_id', $roleId)
                    ->where('permission_id', $permissionId)
                    ->exists();

                if (!$exists) {
                    DB::table('role_has_permissions')->insert([
                        'permission_id' => $permissionId,
                        'role_id' => $roleId,
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        $permisos = [
            PermissionConst::GESTION_USUARIO,
            PermissionConst::GESTION_ROLES,
            PermissionConst::GESTION_ROL_PERMISOS,
        ];

        $permissionIds = DB::table('permissions')
            ->whereIn('name', $permisos)
            ->pluck('id');

        $vtRoleIds = DB::table('roles')
            ->whereIn('name', EduExceAuthorization::vtRoleNames())
            ->pluck('id');

        DB::table('role_has_permissions')
            ->whereIn('role_id', $vtRoleIds)
            ->whereIn('permission_id', $permissionIds)
            ->delete();
    }
};
