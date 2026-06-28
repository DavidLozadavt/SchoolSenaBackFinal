<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

try {
    $service = app(\App\Services\EduExceApiService::class);
    $result = $service->listarInstituciones('nativas');
    echo 'OK total=' . count($result['instituciones'] ?? []) . PHP_EOL;
} catch (Throwable $e) {
    echo 'ERR ' . $e->getMessage() . PHP_EOL;
    exit(1);
}
