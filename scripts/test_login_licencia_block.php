<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\EmpresaModuloEduexce;
use App\Services\EduExceLicenciaService;

$companyId = (int) ($argv[1] ?? 4);
$svc = app(EduExceLicenciaService::class);
$block = $svc->loginBlockedByLicenciaInactiva($companyId);

echo 'company_id=' . $companyId . PHP_EOL;
echo 'block=' . json_encode($block, JSON_UNESCAPED_UNICODE) . PHP_EOL;

$modulo = EmpresaModuloEduexce::where('id_empresa', $companyId)->first();
if ($modulo) {
    echo 'modulo: activo=' . ($modulo->activo ? '1' : '0')
        . ' id_edx=' . $modulo->id_institucion_eduexce
        . ' vigente=' . ($modulo->licenciaVigente() ? '1' : '0') . PHP_EOL;
}
