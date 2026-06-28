<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Permission\PermissionConst;
use App\Support\EduExceAuthorization;
use Spatie\Permission\Models\Role;

$vtNames = array_map('strtoupper', EduExceAuthorization::vtRoleNames());
$eduexceRole = strtoupper(PermissionConst::ROL_ADMIN_INSTITUCION_EDUEXCE);
$icfesPerms = [PermissionConst::MODULO_ICFES, PermissionConst::GESTION_ICFES];

foreach (Role::all() as $role) {
    $upper = strtoupper($role->name);

    if ($upper === $eduexceRole) {
        continue;
    }

    $isVt = in_array($upper, $vtNames, true);
    $isSena = str_contains($upper, 'REGIONAL')
        || str_contains($upper, 'CENTRO')
        || str_contains($upper, 'SENA');

    if ($isVt || $isSena) {
        foreach ($icfesPerms as $perm) {
            if ($role->hasPermissionTo($perm)) {
                $role->revokePermissionTo($perm);
                echo "Revocado {$perm} de: {$role->name}\n";
            }
        }
    }
}

echo "Limpieza completada.\n";
