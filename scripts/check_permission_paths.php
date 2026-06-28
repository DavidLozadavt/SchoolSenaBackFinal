<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Spatie\Permission\Models\Permission;

$names = ['GESTION_USUARIO', 'GESTION_ROLES', 'GESTION_ROL_PERMISOS', 'LICENCIA_ICFES', 'MODULO_ICFES'];
foreach (Permission::whereIn('name', $names)->get() as $p) {
    echo "{$p->name} path=" . ($p->path ?? 'NULL') . " padre=" . ($p->idPermissionPadre ?? 'NULL') . " desc={$p->description}\n";
}
