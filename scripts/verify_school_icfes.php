<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\EmpresaModuloEduexce;
use App\Models\PersonaEduexce;
use App\Permission\PermissionConst;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;

$checks = [];

$checks['db_name'] = config('database.connections.mysql.database');
$checks['tables'] = [
    'empresa_modulo_eduexce' => Schema::hasTable('empresa_modulo_eduexce'),
    'persona_eduexce' => Schema::hasTable('persona_eduexce'),
];
$checks['permiso_gestion_icfes'] = Permission::where('name', PermissionConst::GESTION_ICFES)->exists();
$checks['empresa_modulos'] = EmpresaModuloEduexce::all(['id_empresa', 'activo', 'id_institucion_eduexce', 'fecha_vigencia_fin'])->toArray();
$checks['personas_eduexce_count'] = PersonaEduexce::count();
$checks['personas_conectadas'] = PersonaEduexce::where('estado', 'conectado')->count();

$eduexceUrl = rtrim(env('EDUEXCE_API_URL', ''), '/');
$apiKey = env('EDUEXCE_INTEGRATION_API_KEY', '');

try {
    $health = Http::timeout(8)->get("{$eduexceUrl}/health");
    $checks['eduexce_health'] = $health->successful() ? $health->json('status') : 'HTTP ' . $health->status();
} catch (Throwable $e) {
    $checks['eduexce_health'] = 'ERROR: ' . $e->getMessage();
}

try {
    $res = Http::timeout(10)
        ->withHeaders(['X-Integration-Token' => $apiKey])
        ->get("{$eduexceUrl}/integracion/institucion/1/resumen");
    $checks['eduexce_resumen_empresa_1'] = $res->successful()
        ? ['ok' => true, 'nombre' => $res->json('nombre_institucion'), 'activa' => $res->json('is_active')]
        : ['ok' => false, 'error' => $res->json('error') ?? substr($res->body(), 0, 150)];
} catch (Throwable $e) {
    $checks['eduexce_resumen_empresa_1'] = ['ok' => false, 'error' => $e->getMessage()];
}

$checks['routes_ok'] = file_exists(app_path('Http/Controllers/EduExceIntegrationController.php'))
    && file_exists(app_path('Services/EduExceApiService.php'));

echo json_encode($checks, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
