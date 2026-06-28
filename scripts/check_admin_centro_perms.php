<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$r = Spatie\Permission\Models\Role::where('name', 'ADMIN CENTRO')->first();
echo "ADMIN CENTRO: " . ($r?->permissions->count() ?? 0) . " permisos\n";
foreach ($r?->permissions ?? [] as $p) {
    if (str_contains($p->name, 'ICFES')) echo "  {$p->name}\n";
}
