<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

echo "Permisos ICFES:\n";
foreach (Permission::where('name', 'like', '%ICFES%')->get() as $p) {
    echo "  - {$p->name} path={$p->path}\n";
}

echo "\nRoles:\n";
foreach (Role::all() as $r) {
    $icfes = $r->permissions->pluck('name')->filter(fn ($p) => str_contains($p, 'ICFES'))->implode(', ');
    if ($icfes) {
        echo "  {$r->name} => {$icfes}\n";
    }
}
