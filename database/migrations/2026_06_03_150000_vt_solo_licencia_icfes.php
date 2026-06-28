<?php

use App\Support\EduExceAuthorization;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $moduloId = DB::table('permissions')->where('name', 'MODULO_ICFES')->value('id');
        if (!$moduloId) {
            return;
        }

        $vtRoleIds = DB::table('roles')
            ->whereIn('name', EduExceAuthorization::vtRoleNames())
            ->pluck('id');

        foreach ($vtRoleIds as $roleId) {
            DB::table('role_has_permissions')
                ->where('role_id', $roleId)
                ->where('permission_id', $moduloId)
                ->delete();
        }
    }

    public function down(): void
    {
        $moduloId = DB::table('permissions')->where('name', 'MODULO_ICFES')->value('id');
        $licenciaId = DB::table('permissions')->where('name', 'LICENCIA_ICFES')->value('id');

        if (!$moduloId || !$licenciaId) {
            return;
        }

        $vtRoleIds = DB::table('roles')
            ->whereIn('name', EduExceAuthorization::vtRoleNames())
            ->pluck('id');

        foreach ($vtRoleIds as $roleId) {
            $exists = DB::table('role_has_permissions')
                ->where('role_id', $roleId)
                ->where('permission_id', $moduloId)
                ->exists();

            if (!$exists) {
                DB::table('role_has_permissions')->insert([
                    'permission_id' => $moduloId,
                    'role_id' => $roleId,
                ]);
            }
        }
    }
};
