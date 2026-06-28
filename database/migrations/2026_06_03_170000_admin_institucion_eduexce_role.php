<?php

use App\Permission\PermissionConst;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        $role = Role::firstOrCreate(
            ['name' => PermissionConst::ROL_ADMIN_INSTITUCION_EDUEXCE, 'guard_name' => 'web']
        );

        $moduloId = Permission::where('name', PermissionConst::MODULO_ICFES)->value('id');
        if ($moduloId && !$role->hasPermissionTo(PermissionConst::MODULO_ICFES)) {
            DB::table('role_has_permissions')->insertOrIgnore([
                'permission_id' => $moduloId,
                'role_id' => $role->id,
            ]);
        }
    }

    public function down(): void
    {
        $role = Role::where('name', PermissionConst::ROL_ADMIN_INSTITUCION_EDUEXCE)->first();
        if ($role) {
            $role->permissions()->detach();
            $role->delete();
        }
    }
};
