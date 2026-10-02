<?php

require __DIR__ . '/../../vendor/autoload.php';
$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$idContrato = 11;
$hoy = '2026-05-27';

echo "=== DAVID LOZADA contrato {$idContrato} - ASIGNADO vigente ===\n\n";

$horarios = DB::table('horarioMateria as hm')
    ->join('dia as d', 'hm.idDia', '=', 'd.id')
    ->where('hm.idContrato', $idContrato)
    ->where('hm.estado', 'ASIGNADO')
    ->whereNotNull('hm.idDia')
    ->select('hm.id', 'd.dia', 'hm.horaInicial', 'hm.horaFinal', 'hm.fechaInicial', 'hm.fechaFinal')
    ->orderBy('d.id')
    ->orderBy('hm.horaInicial')
    ->get();

$porDia = $horarios->groupBy('dia');
foreach ($porDia as $dia => $items) {
    $maxFin = $items->max('fechaFinal');
    echo "{$dia} (hasta {$maxFin}):\n";
    foreach ($items as $h) {
        $vig = ($h->fechaInicial <= $hoy && $h->fechaFinal >= $hoy) ? 'VIGENTE HOY' : '';
        echo sprintf(
            "  #%s %s-%s | %s → %s %s\n",
            $h->id,
            substr($h->horaInicial, 0, 5),
            substr($h->horaFinal, 0, 5),
            $h->fechaInicial,
            $h->fechaFinal,
            $vig
        );
    }
}

echo "\n=== HOY MIÉRCOLES vigente ===\n";
$hoyHm = $horarios->filter(fn ($h) => stripos(
    DB::table('dia')->where('id', DB::table('horarioMateria')->where('id', $h->id)->value('idDia'))->value('dia') ?? '',
    'MIER'
) !== false);
// simpler:
$mie = DB::table('horarioMateria as hm')
    ->join('dia as d', 'hm.idDia', '=', 'd.id')
    ->where('hm.idContrato', $idContrato)
    ->where('hm.idDia', 3)
    ->where('hm.estado', 'ASIGNADO')
    ->where('hm.fechaInicial', '<=', $hoy)
    ->where('hm.fechaFinal', '>=', $hoy)
    ->select('hm.*', 'd.dia')
    ->get();
foreach ($mie as $m) {
    echo "#{$m->id} {$m->dia} {$m->horaInicial}-{$m->horaFinal} fin {$m->fechaFinal}\n";
}

echo "\n=== VIERNES David ===\n";
$vie = DB::table('horarioMateria as hm')
    ->join('dia as d', 'hm.idDia', '=', 'd.id')
    ->where('hm.idContrato', $idContrato)
    ->where('hm.idDia', 5)
    ->where('hm.estado', 'ASIGNADO')
    ->select('hm.id', 'hm.fechaFinal', 'hm.horaInicial', 'hm.horaFinal')
    ->get();
foreach ($vie as $v) {
    echo "#{$v->id} fin {$v->fechaFinal} {$v->horaInicial}-{$v->horaFinal}\n";
}
