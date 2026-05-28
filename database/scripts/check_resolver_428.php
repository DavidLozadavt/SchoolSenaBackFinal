<?php
require __DIR__ . '/../../vendor/autoload.php';
$app = require __DIR__ . '/../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$controller = app(\App\Http\Controllers\FichaController::class);
$ref = new ReflectionClass($controller);
$method = $ref->getMethod('resolverModalidadRap');
$method->setAccessible(true);
$result = $method->invoke($controller, 428, 11);
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
