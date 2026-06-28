<?php

use App\Models\User;
use App\Permission\PermissionConst;
use App\Support\EduExceAuthorization;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $moduloId = DB::table('permissions')->where('name', PermissionConst::MODULO_ICFES)->value('id');
        $legacyId = DB::table('permissions')->where('name', PermissionConst::GESTION_ICFES)->value('id');
        $eduexceRoleId = DB::table('roles')
            ->where('name', PermissionConst::ROL_ADMIN_INSTITUCION_EDUEXCE)
            ->value('id');

        if (Schema::hasColumn('permissions', 'description')) {
            DB::table('permissions')
                ->where('name', PermissionConst::MODULO_ICFES)
                ->update([
                    'description' => 'Módulo ICFES / EduExce (institución)',
                    'updated_at' => now(),
                ]);
        }

        if ($legacyId && Schema::hasColumn('permissions', 'path')) {
            DB::table('permissions')
                ->where('name', PermissionConst::GESTION_ICFES)
                ->update(['path' => null, 'updated_at' => now()]);
        }

        $permissionIds = array_values(array_filter([$moduloId, $legacyId]));
        if ($permissionIds === []) {
            return;
        }

        // Quitar ICFES de todos los roles excepto admin institución EduExce.
        DB::table('role_has_permissions')
            ->whereIn('permission_id', $permissionIds)
            ->when($eduexceRoleId, fn ($q) => $q->where('role_id', '!=', $eduexceRoleId))
            ->delete();

        // VT solo licencias, nunca módulo operativo.
        $vtRoleIds = DB::table('roles')
            ->whereIn('name', EduExceAuthorization::vtRoleNames())
            ->pluck('id');

        foreach ($vtRoleIds as $roleId) {
            DB::table('role_has_permissions')
                ->where('role_id', $roleId)
                ->whereIn('permission_id', $permissionIds)
                ->delete();
        }

        // Roles SENA regional / centro: sin acceso EduExce.
        foreach (DB::table('roles')->get() as $role) {
            if ($eduexceRoleId && (int) $role->id === (int) $eduexceRoleId) {
                continue;
            }

            $name = strtoupper((string) $role->name);
            $isSenaOperativo = str_contains($name, 'REGIONAL')
                || str_contains($name, 'CENTRO')
                || str_contains($name, 'SENA');

            if ($isSenaOperativo) {
                DB::table('role_has_permissions')
                    ->where('role_id', $role->id)
                    ->whereIn('permission_id', $permissionIds)
                    ->delete();
            }
        }

        if ($eduexceRoleId && $moduloId) {
            DB::table('role_has_permissions')->insertOrIgnore([
                'permission_id' => $moduloId,
                'role_id' => $eduexceRoleId,
            ]);
        }

        // Permisos directos en usuarios de centro de formación.
        if (Schema::hasTable('users') && Schema::hasTable('model_has_permissions')) {
            $userIdsCentro = DB::table('users')
                ->whereNotNull('idCentroFormacion')
                ->pluck('id');

            if ($userIdsCentro->isNotEmpty()) {
                DB::table('model_has_permissions')
                    ->where('model_type', User::class)
                    ->whereIn('model_id', $userIdsCentro)
                    ->whereIn('permission_id', $permissionIds)
                    ->delete();
            }
        }
    }

    public function down(): void
    {
        // Sin reversión automática: evitar reabrir acceso SENA por error.
    }
};
