<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\EmpresaModuloEduexce;

$modulos = EmpresaModuloEduexce::whereNotNull('id_institucion_eduexce')->get();
if ($modulos->isEmpty()) {
    echo "sin registros en empresa_modulo_eduexce\n";
    exit(0);
}

foreach ($modulos as $m) {
    echo implode('|', [
        'id_edx=' . $m->id_institucion_eduexce,
        'empresa=' . $m->id_empresa,
        'activo=' . ($m->activo ? '1' : '0'),
        'vigente=' . ($m->licenciaVigente() ? '1' : '0'),
        'vigencia=' . ($m->fecha_vigencia_fin?->format('Y-m-d') ?? 'null'),
    ]) . "\n";
}
