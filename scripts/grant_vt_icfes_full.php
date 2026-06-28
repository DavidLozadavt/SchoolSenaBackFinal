<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Permission\PermissionConst;
use App\Support\EduExceAuthorization;
use Spatie\Permission\Models\Role;

foreach (EduExceAuthorization::vtRoleNames() as $name) {
    $role = Role::where('name', $name)->first();
    if (!$role) {
        continue;
    }
    $role->givePermissionTo(PermissionConst::LICENCIA_ICFES);
    if ($role->hasPermissionTo(PermissionConst::MODULO_ICFES)) {
        $role->revokePermissionTo(PermissionConst::MODULO_ICFES);
    }
    echo "OK: {$role->name} → LICENCIA_ICFES (sin MODULO_ICFES)\n";
}

app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
echo "Listo. Cierra sesión y vuelve a entrar.\n";
