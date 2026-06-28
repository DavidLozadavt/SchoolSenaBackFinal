<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use Spatie\Permission\Models\Permission;

$admin = User::query()->where('email', 'admin@gmail.com')->first();
if (!$admin) {
    $admin = User::query()->first();
}

$perm = Permission::where('name', 'GESTION_ICFES')->first();

echo json_encode([
    'admin_email' => $admin?->email,
    'admin_id' => $admin?->id,
    'gestion_icfes_permission_id' => $perm?->id,
], JSON_PRETTY_PRINT);
