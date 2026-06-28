<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\EduExceInstitucionService;

$companyId = 1;
$centroId = 1;
$svc = app(EduExceInstitucionService::class);

$lista = $svc->aprendicesMatricula($companyId, $centroId);
$conGrado = $lista->filter(fn ($a) => !empty($a['grado']))->count();
$conJornada = $lista->filter(fn ($a) => !empty($a['jornada']))->count();

echo "Matricula centro comercio: {$lista->count()}\n";
echo "Con ficha (grado): {$conGrado}\n";
echo "Con jornada: {$conJornada}\n";
echo "Muestra:\n";
echo json_encode($lista->take(3)->values(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
