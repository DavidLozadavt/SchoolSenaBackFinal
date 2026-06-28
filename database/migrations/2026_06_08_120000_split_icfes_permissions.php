<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Support\EduExceAuthorization;

return new class extends Migration
{
    private function insertPermission(array $data): void
    {
        if (DB::table('permissions')->where('name', $data['name'])->exists()) {
            return;
        }

        $row = array_merge([
            'guard_name' => 'web',
            'created_at' => now(),
            'updated_at' => now(),
        ], $data);

        if (Schema::hasColumn('permissions', 'icon') && !isset($row['icon'])) {
            $row['icon'] = null;
        }
        if (Schema::hasColumn('permissions', 'path') && !array_key_exists('path', $row)) {
            $row['path'] = null;
        }
        if (Schema::hasColumn('permissions', 'idPermissionPadre') && !array_key_exists('idPermissionPadre', $row)) {
            $row['idPermissionPadre'] = null;
        }

        DB::table('permissions')->insert($row);
    }

    public function up(): void
    {
        $this->insertPermission([
            'name' => 'MODULO_ICFES',
            'description' => 'Módulo ICFES / EduExce (centro)',
            'icon' => 'book-open',
            'path' => '/icfes',
        ]);

        $this->insertPermission([
            'name' => 'LICENCIA_ICFES',
            'description' => 'Licencias ICFES — Virtual Technology',
            'icon' => 'shield-tick',
            'path' => '/icfes/licencias',
        ]);

        if (DB::table('permissions')->where('name', 'GESTION_ICFES')->exists()) {
            $update = [
                'description' => '(Legacy) Módulo ICFES — use MODULO_ICFES o LICENCIA_ICFES',
                'updated_at' => now(),
            ];
            if (Schema::hasColumn('permissions', 'path')) {
                $update['path'] = null;
            }
            DB::table('permissions')->where('name', 'GESTION_ICFES')->update($update);
        }

        $moduloId = DB::table('permissions')->where('name', 'MODULO_ICFES')->value('id');
        $licenciaId = DB::table('permissions')->where('name', 'LICENCIA_ICFES')->value('id');
        $legacyId = DB::table('permissions')->where('name', 'GESTION_ICFES')->value('id');

        if (!$moduloId || !$licenciaId) {
            return;
        }

        $vtRoles = DB::table('roles')
            ->whereIn('name', EduExceAuthorization::vtRoleNames())
            ->pluck('id');

        $rolesWithLegacy = DB::table('role_has_permissions')
            ->when($legacyId, fn ($q) => $q->where('permission_id', $legacyId))
            ->pluck('role_id')
            ->unique();

        foreach ($rolesWithLegacy as $roleId) {
            $isVt = $vtRoles->contains($roleId);

            if ($isVt) {
                $this->attachPermission((int) $roleId, (int) $licenciaId);
                $this->detachPermission((int) $roleId, (int) $moduloId);
            } else {
                $this->attachPermission((int) $roleId, (int) $moduloId);
                $this->detachPermission((int) $roleId, (int) $licenciaId);
            }

            if ($legacyId) {
                DB::table('role_has_permissions')
                    ->where('role_id', $roleId)
                    ->where('permission_id', $legacyId)
                    ->delete();
            }
        }

        foreach ($vtRoles as $roleId) {
            $this->attachPermission((int) $roleId, (int) $licenciaId);
            $this->detachPermission((int) $roleId, (int) $moduloId);
        }

        $noOperacion = ['INSTRUCTOR', 'APRENDIZ', 'ESTUDIANTE', 'DOCENTE'];
        foreach (DB::table('roles')->get() as $role) {
            $upper = strtoupper((string) $role->name);
            foreach ($noOperacion as $needle) {
                if (str_contains($upper, $needle) && !str_contains($upper, 'ADMIN')) {
                    $this->detachPermission((int) $role->id, (int) $moduloId);
                    break;
                }
            }
        }
    }

    private function attachPermission(int $roleId, int $permissionId): void
    {
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

    private function detachPermission(int $roleId, int $permissionId): void
    {
        DB::table('role_has_permissions')
            ->where('role_id', $roleId)
            ->where('permission_id', $permissionId)
            ->delete();
    }

    public function down(): void
    {
        $names = ['MODULO_ICFES', 'LICENCIA_ICFES'];
        $ids = DB::table('permissions')->whereIn('name', $names)->pluck('id');

        DB::table('role_has_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('name', $names)->delete();

        if (DB::table('permissions')->where('name', 'GESTION_ICFES')->exists()) {
            $update = ['description' => 'Módulo ICFES / EduExce', 'updated_at' => now()];
            if (Schema::hasColumn('permissions', 'path')) {
                $update['path'] = '/icfes';
            }
            DB::table('permissions')->where('name', 'GESTION_ICFES')->update($update);
        }
    }
};
