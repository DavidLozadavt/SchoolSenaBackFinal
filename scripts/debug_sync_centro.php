<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\CentroModuloEduexce;
use App\Models\CentrosFormacion;
use App\Models\Company;
use App\Models\EmpresaModuloEduexce;
use App\Models\PersonaEduexce;
use App\Services\EduExceApiService;
use App\Services\EduExceInstitucionService;
use App\Support\EduExceExternalSchoolId;

$companyId = 1;
$svc = app(EduExceInstitucionService::class);
$api = app(EduExceApiService::class);

echo "=== Centros ===\n";
foreach (CentrosFormacion::where('idEmpresa', $companyId)->get() as $c) {
    $ext = EduExceExternalSchoolId::forCentro($c->id);
    $count = $svc->aprendicesPorCentro($companyId, $c->id);
    $coll = $svc->aprendicesDeCentro($companyId, $c->id);
    $mod = CentroModuloEduexce::where('id_empresa', $companyId)->where('id_centro_formacion', $c->id)->first();
    $eduexce = '?';
    try {
        $r = $api->resumenInstitucion($ext);
        $eduexce = (string) ($r['total_estudiantes'] ?? 0);
    } catch (Throwable $e) {
        $eduexce = 'ERR: ' . $e->getMessage();
    }
    echo "{$c->nombre} (id={$c->id}) matricula_count={$count} sync_coll={$coll->count()} eduexce={$eduexce} licencia=" . ($mod?->activo ? 'ON' : 'OFF') . "\n";
}

$conectados = PersonaEduexce::where('id_empresa', $companyId)->where('estado', 'conectado')->count();
echo "\nPersonaEduexce conectados (regional): {$conectados}\n";

$company = Company::find($companyId);
$modulo = EmpresaModuloEduexce::firstOrCreate(['id_empresa' => $companyId]);
$centroId = 2; // COMERCIO typical
$coll = $svc->aprendicesDeCentro($companyId, $centroId);
if ($coll->isNotEmpty()) {
    $first = $coll->first();
    $idPersona = (int) $first->user->persona->id;
    echo "\nProbando provision 1 aprendiz persona={$idPersona}...\n";
    try {
        $ext = EduExceExternalSchoolId::forCentro($centroId);
        $result = $svc->provisionarAprendiz($companyId, $idPersona, $modulo, $company, $ext);
        echo json_encode($result, JSON_PRETTY_PRINT) . "\n";
    } catch (Throwable $e) {
        echo "ERROR: " . $e->getMessage() . "\n";
    }
} else {
    echo "\nSin aprendices en coleccion sync para centro {$centroId}\n";
}
