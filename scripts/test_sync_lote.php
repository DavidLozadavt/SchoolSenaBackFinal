<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Company;
use App\Models\EmpresaModuloEduexce;
use App\Models\PersonaEduexce;
use App\Services\EduExceInstitucionService;
use App\Support\EduExceExternalSchoolId;

$companyId = 1;
$centroId = 1;
$svc = app(EduExceInstitucionService::class);
$company = Company::find($companyId);
$modulo = EmpresaModuloEduexce::firstOrCreate(['id_empresa' => $companyId]);
$ext = EduExceExternalSchoolId::forCentro($centroId);

$coll = $svc->aprendicesDeCentro($companyId, $centroId);
$pendientes = 0;
$conectados = 0;
foreach ($coll as $a) {
    $id = (int) $a->user->persona->id;
    $r = PersonaEduexce::where('id_persona', $id)->where('id_empresa', $companyId)->first();
    if (!$r || $r->estado !== 'conectado') {
        $pendientes++;
    } else {
        $conectados++;
    }
}
echo "Pendientes={$pendientes} conectados_marcados={$conectados}\n";

$start = microtime(true);
$sync = $svc->sincronizarAprendicesEnLote($companyId, $modulo, $company, 10, $centroId, $ext);
$elapsed = round(microtime(true) - $start, 2);
echo "Elapsed: {$elapsed}s\n";
echo json_encode($sync, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
