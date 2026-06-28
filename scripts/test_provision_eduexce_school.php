<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\EduExceLicenciaService;

$id = (int) ($argv[1] ?? 2);
try {
    $svc = app(EduExceLicenciaService::class);
    $result = $svc->activarLicenciaEduexce($id);
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
} catch (Throwable $e) {
    echo "ERROR: {$e->getMessage()}\n";
    exit(1);
}
