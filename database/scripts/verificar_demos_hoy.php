<?php
require __DIR__ . '/../../vendor/autoload.php';
$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$hoy = date('Y-m-d');
echo "Fecha hoy (servidor): {$hoy}\n\n";

$compartidos = DB::table('horarioCompartido')
    ->where('observacion', 'like', '%DEMO clase compartida%')
    ->get();
echo "=== COMPARTIDA ===\n";
if ($compartidos->isEmpty()) {
    echo "No hay demo compartida. Ejecuta: php database/scripts/seed_clase_compartida_hoy.php\n";
} else {
    foreach ($compartidos as $c) {
        $hm = DB::table('horarioMateria')->where('id', $c->idHorarioMateria)->first();
        echo "horarioCompartido #{$c->id} | hm #{$c->idHorarioMateria} | {$hm->horaInicial}-{$hm->horaFinal} | {$hm->fechaInicial}\n";
    }
}

$reemplazos = DB::table('reemplazo')
    ->where('observacion', 'like', '%DEMO clase reemplazo%')
    ->whereNotNull('idHorarioMateria')
    ->get();
echo "\n=== REEMPLAZO ===\n";
if ($reemplazos->isEmpty()) {
    echo "No hay demo reemplazo. Ejecuta: php database/scripts/seed_clase_reemplazo_hoy.php\n";
} else {
    foreach ($reemplazos as $r) {
        $hm = DB::table('horarioMateria')->where('id', $r->idHorarioMateria)->first();
        echo "reemplazo #{$r->id} | hm #{$r->idHorarioMateria} | {$hm->horaInicial}-{$hm->horaFinal} | reemplazante contrato {$r->idContratoRemplazo}\n";
    }
}
