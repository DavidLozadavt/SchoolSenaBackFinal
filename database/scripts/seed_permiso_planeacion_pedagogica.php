<?php
/**
 * Crea el permiso de menú "Planeación pedagógica" (debajo de Horario)
 * y lo asigna a DOCENTEUP e INSTRUCTOR SENA.
 *
 *   php database/scripts/seed_permiso_planeacion_pedagogica.php
 */

require __DIR__ . '/../../vendor/autoload.php';
$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Permission\PermissionConst;
use Illuminate\Support\Facades\DB;

$name = PermissionConst::AULA_VIRTUAL_INSTRUCTOR_PLANEACION_PEDAGOGICA;
$path = '/ambiente-virtual/planeacion-pedagogica';
$now = now();

DB::beginTransaction();

try {
    $perm = DB::table('permissions')->where('name', $name)->first();

    if ($perm) {
        DB::table('permissions')->where('id', $perm->id)->update([
            'description' => 'Planeación pedagógica',
            'icon' => 'notepad-edit',
            'path' => $path,
            'idPermissionPadre' => null,
            'updated_at' => $now,
        ]);
        $permId = (int) $perm->id;
        echo "Permiso actualizado id={$permId}\n";
    } else {
        $permId = (int) DB::table('permissions')->insertGetId([
            'name' => $name,
            'guard_name' => 'web',
            'description' => 'Planeación pedagógica',
            'icon' => 'notepad-edit',
            'path' => $path,
            'idPermissionPadre' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        echo "Permiso creado id={$permId}\n";
    }

    $roles = DB::table('roles')
        ->whereIn('name', ['DOCENTEUP', 'INSTRUCTOR SENA'])
        ->get(['id', 'name']);

    foreach ($roles as $role) {
        $exists = DB::table('role_has_permissions')
            ->where('role_id', $role->id)
            ->where('permission_id', $permId)
            ->exists();

        if (!$exists) {
            DB::table('role_has_permissions')->insert([
                'permission_id' => $permId,
                'role_id' => $role->id,
            ]);
            echo "Asignado a rol {$role->name} (id={$role->id})\n";
        } else {
            echo "Ya tenía el permiso: {$role->name}\n";
        }
    }

    // También asegurar que Horario tenga description/icon/path correctos (referencia)
    DB::table('permissions')
        ->where('name', PermissionConst::AULA_VIRTUAL_INSTRUCTOR_HORARIO)
        ->update([
            'description' => 'Horario',
            'icon' => 'calendar-tick',
            'path' => '/ambiente-virtual/horario',
            'updated_at' => $now,
        ]);

    DB::commit();

    if (class_exists(\Spatie\Permission\PermissionRegistrar::class)) {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    echo "OK: menú Planeación pedagógica listo ({$path})\n";
} catch (Throwable $e) {
    DB::rollBack();
    fwrite(STDERR, 'ERROR: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
