<?php
/**
 * Configura integración ICFES: permisos MODULO_ICFES / LICENCIA_ICFES + licencia demo.
 * Uso: php scripts/setup_eduexce_nueva_bd.php [company_id]
 */
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\EmpresaModuloEduexce;
use App\Permission\PermissionConst;
use App\Support\EduExceAuthorization;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

$companyId = (int) ($argv[1] ?? 1);

echo "=== Setup ICFES en BD: " . config('database.connections.mysql.database') . " ===\n";

if (!Schema::hasTable('empresa_modulo_eduexce')) {
    echo "ERROR: Falta migración empresa_modulo_eduexce\n";
    exit(1);
}

foreach ([
    PermissionConst::MODULO_ICFES => ['desc' => 'Conectar ICFES', 'path' => '/icfes', 'icon' => 'exit-right'],
    PermissionConst::LICENCIA_ICFES => ['desc' => 'Licencias ICFES — gestionadas en ERP', 'path' => null, 'icon' => 'shield-tick'],
] as $name => $meta) {
    $p = Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web'], ['description' => $meta['desc']]);
    if (Schema::hasColumn('permissions', 'path')) {
        $p->path = $meta['path'];
        $p->icon = $meta['icon'];
        $p->description = $meta['desc'];
        $p->save();
    }
    echo "Permiso {$name}: OK\n";
}

$vtNames = EduExceAuthorization::vtRoleNames();
$icfesPerms = [PermissionConst::MODULO_ICFES, PermissionConst::GESTION_ICFES];
foreach (Role::all() as $role) {
    $isVt = in_array($role->name, $vtNames, true)
        || in_array(strtoupper($role->name), array_map('strtoupper', $vtNames), true);

    if ($isVt) {
        $role->givePermissionTo(PermissionConst::LICENCIA_ICFES);
        foreach ($icfesPerms as $perm) {
            if ($role->hasPermissionTo($perm)) {
                $role->revokePermissionTo($perm);
            }
        }
        echo "  VT → LICENCIA_ICFES: {$role->name}\n";
    } elseif (strtoupper($role->name) === strtoupper(PermissionConst::ROL_ADMIN_INSTITUCION_EDUEXCE)) {
        $role->givePermissionTo(PermissionConst::MODULO_ICFES);
        echo "  EduExce → MODULO_ICFES: {$role->name}\n";
    }
}

foreach (Role::all() as $role) {
    $upper = strtoupper($role->name);
    if ($upper === strtoupper(PermissionConst::ROL_ADMIN_INSTITUCION_EDUEXCE)) {
        continue;
    }
    if (str_contains($upper, 'REGIONAL') || str_contains($upper, 'CENTRO') || str_contains($upper, 'SENA')) {
        foreach ($icfesPerms as $perm) {
            if ($role->hasPermissionTo($perm)) {
                $role->revokePermissionTo($perm);
            }
        }
    }
}

EmpresaModuloEduexce::updateOrCreate(
    ['id_empresa' => $companyId],
    [
        'activo' => true,
        'fecha_vigencia_fin' => now()->addYear()->toDateString(),
    ]
);

echo "Licencia demo empresa {$companyId}: activa\n";
echo "Listo. VT: ERP /admin/solicitudes | Institución EduExce: /icfes\n";
