<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$updated = \App\Models\Company::where('inscripcionHabilitada', 1)->get();
echo "UPDATED COMPANIES:\n";
foreach ($updated as $u) {
    echo "ID: {$u->id}, NIT: {$u->nit}, idForm: {$u->idFormularioInscripcion}, Habilitada: {$u->inscripcionHabilitada}\n";
}
