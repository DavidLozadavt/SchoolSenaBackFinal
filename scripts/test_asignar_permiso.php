<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\Http;

$role = Role::first();
$perms = Permission::take(3)->pluck('id')->toArray();

echo "Role: {$role->id} {$role->name}\n";
echo 'Perm IDs: ' . json_encode($perms) . "\n";

try {
    $role->syncPermissions($perms);
    echo "syncPermissions with IDs: OK\n";
    echo 'Assigned: ' . $role->fresh()->permissions->pluck('name')->join(', ') . "\n";
} catch (Throwable $e) {
    echo 'syncPermissions IDs error: ' . $e->getMessage() . "\n";
}

$resp = Http::timeout(10)->put('http://127.0.0.1:8000/api/asignar_rol_permiso', [
    'idRol' => $role->id,
    'funciones' => $perms,
]);

echo 'HTTP status: ' . $resp->status() . "\n";
echo substr($resp->body(), 0, 500) . "\n";
