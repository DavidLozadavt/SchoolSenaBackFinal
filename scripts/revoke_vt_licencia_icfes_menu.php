<?php

/**
 * Quita LICENCIA_ICFES del menú School — VT gestiona licencias desde ERP.
 * Uso: php scripts/revoke_vt_licencia_icfes_menu.php
 */

use App\Models\Rol;
use Illuminate\Support\Facades\DB;

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$permId = DB::table('permissions')->where('name', 'LICENCIA_ICFES')->value('id');
if (!$permId) {
    echo "Permiso LICENCIA_ICFES no encontrado.\n";
    exit(0);
}

DB::table('permissions')->where('id', $permId)->update([
    'path' => null,
    'description' => 'Gestionado en ERP — Gestión de Clientes',
]);

$roles = Rol::whereIn('name', ['ADMIN VT', 'administradorVT', 'Administrador VT'])->get();
foreach ($roles as $role) {
    if ($role->hasPermissionTo('LICENCIA_ICFES')) {
        $role->revokePermissionTo('LICENCIA_ICFES');
        echo "Revocado LICENCIA_ICFES de rol: {$role->name}\n";
    }
}

echo "Listo. VT usa ERP /admin/solicitudes para licencias EduExce.\n";
