<?php

/**
 * Sincroniza todas las empresas School con módulo EduExce activo hacia la API local.
 * Uso: php scripts/bootstrap_eduexce_todas.php
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\EmpresaModuloEduexce;

$modulos = EmpresaModuloEduexce::query()
    ->where('activo', true)
    ->orderBy('id_empresa')
    ->get();

if ($modulos->isEmpty()) {
    echo "No hay empresas con licencia EduExce activa.\n";
    exit(0);
}

foreach ($modulos as $m) {
    $id = (int) $m->id_empresa;
    echo "\n========== Empresa {$id} ==========\n";
    passthru(PHP_BINARY . ' ' . escapeshellarg(__DIR__ . '/bootstrap_eduexce_empresa.php') . ' ' . $id, $code);
    if ($code !== 0) {
        echo "AVISO: empresa {$id} terminó con código {$code}\n";
    }
}

echo "\nListo.\n";
