<?php

require __DIR__ . '/../../vendor/autoload.php';
$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$hoy = '2026-05-27';

echo "=== Buscar contrato con LUNES fin ~2026-07-06, JUEVES ~2026-06-11, VIERNES ~2026-06-12 ===\n";

$contratos = DB::table('horarioMateria as hm')
    ->join('contrato as c', 'hm.idContrato', '=', 'c.id')
    ->join('persona as p', 'c.idpersona', '=', 'p.id')
    ->join('dia as d', 'hm.idDia', '=', 'd.id')
    ->whereNotNull('hm.idContrato')
    ->where('hm.estado', 'ASIGNADO')
    ->select('c.id as idContrato', DB::raw("CONCAT(p.nombre1,' ',p.apellido1) as nombre"), 'd.dia', 'hm.fechaFinal', 'hm.horaInicial', 'hm.horaFinal', 'hm.id as hmId')
    ->get()
    ->groupBy('idContrato');

foreach ($contratos as $idC => $rows) {
    $lunes = $rows->filter(fn ($r) => stripos($r->dia, 'LUN') !== false && $r->fechaFinal >= '2026-07-01' && $r->fechaFinal <= '2026-07-10');
    $jueves = $rows->filter(fn ($r) => stripos($r->dia, 'JUE') !== false && $r->fechaFinal >= '2026-06-10' && $r->fechaFinal <= '2026-06-12');
    $viernes = $rows->filter(fn ($r) => stripos($r->dia, 'VIER') !== false && $r->fechaFinal >= '2026-06-11' && $r->fechaFinal <= '2026-06-13');
    $mie13 = $rows->filter(function ($r) use ($hoy) {
        if (stripos($r->dia, 'MIER') === false) return false;
        $hi = substr($r->horaInicial, 0, 5);
        $hf = substr($r->horaFinal, 0, 5);
        return ($hi >= '12:00' && $hi <= '14:00') || ($hi <= '13:00' && $hf >= '17:00');
    });

    if ($lunes->isNotEmpty() && ($jueves->isNotEmpty() || $viernes->isNotEmpty())) {
        $nombre = $rows->first()->nombre;
        echo "\n>>> Candidato contrato {$idC}: {$nombre}\n";
        if ($lunes->isNotEmpty()) echo "  LUNES fin: " . $lunes->pluck('fechaFinal')->unique()->implode(', ') . "\n";
        if ($jueves->isNotEmpty()) echo "  JUEVES fin: " . $jueves->pluck('fechaFinal')->unique()->implode(', ') . "\n";
        if ($viernes->isNotEmpty()) echo "  VIERNES fin: " . $viernes->pluck('fechaFinal')->unique()->implode(', ') . "\n";
        if ($mie13->isNotEmpty()) {
            foreach ($mie13 as $m) {
                echo "  MIE franja: hm#{$m->hmId} {$m->horaInicial}-{$m->horaFinal} fin {$m->fechaFinal}\n";
            }
        }
    }
}

echo "\n=== Miércoles hoy 13-18 (cualquier contrato ASIGNADO vigente) ===\n";
$mie = DB::table('horarioMateria as hm')
    ->join('contrato as c', 'hm.idContrato', '=', 'c.id')
    ->join('persona as p', 'c.idpersona', '=', 'p.id')
    ->join('dia as d', 'hm.idDia', '=', 'd.id')
    ->where('hm.idDia', 3)
    ->where('hm.fechaInicial', '<=', $hoy)
    ->where('hm.fechaFinal', '>=', $hoy)
    ->where('hm.estado', 'ASIGNADO')
    ->where(function ($q) {
        $q->whereBetween('hm.horaInicial', ['12:00:00', '14:00:00'])
            ->orWhere('hm.horaFinal', '>=', '17:00:00');
    })
    ->select('c.id', DB::raw("CONCAT(p.nombre1,' ',p.apellido1) as nombre"), 'hm.id as hmId', 'hm.horaInicial', 'hm.horaFinal', 'hm.fechaFinal', 'd.dia')
    ->get();

foreach ($mie as $m) {
    echo "contrato {$m->id} {$m->nombre} | hm#{$m->hmId} | {$m->horaInicial}-{$m->horaFinal} | fin {$m->fechaFinal}\n";
}

echo "\n=== LUNES con fechaFinal 2026-07-06 exacto ===\n";
$lun = DB::table('horarioMateria as hm')
    ->join('contrato as c', 'hm.idContrato', '=', 'c.id')
    ->join('persona as p', 'c.idpersona', '=', 'p.id')
    ->join('dia as d', 'hm.idDia', '=', 'd.id')
    ->where('d.dia', 'like', '%LUN%')
    ->where('hm.fechaFinal', '2026-07-06')
    ->where('hm.estado', 'ASIGNADO')
    ->select('c.id', DB::raw("CONCAT(p.nombre1,' ',p.apellido1) as nombre"), 'hm.id', 'hm.horaInicial', 'hm.horaFinal')
    ->get();
foreach ($lun as $l) {
    echo "contrato {$l->id} {$l->nombre} hm#{$l->id} {$l->horaInicial}-{$l->horaFinal}\n";
}
