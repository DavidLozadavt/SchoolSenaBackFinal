<?php

require __DIR__ . '/../../vendor/autoload.php';
$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$idContrato = 8; // Angy Muñoz

echo "=== PROFESOR contrato {$idContrato} ===\n";
$p = DB::table('contrato as c')
    ->join('persona as per', 'c.idpersona', '=', 'per.id')
    ->where('c.id', $idContrato)
    ->select('per.nombre1', 'per.apellido1', 'per.email')
    ->first();
echo ($p->nombre1 ?? '') . ' ' . ($p->apellido1 ?? '') . "\n\n";

echo "=== HORARIOS ASIGNADOS (titular) ===\n";
$horarios = DB::table('horarioMateria as hm')
    ->leftJoin('dia as d', 'hm.idDia', '=', 'd.id')
    ->leftJoin('ficha as f', 'hm.idFicha', '=', 'f.id')
    ->leftJoin('gradoMateria as gm', 'hm.idGradoMateria', '=', 'gm.id')
    ->leftJoin('materia as m', 'gm.idMateria', '=', 'm.id')
    ->where('hm.idContrato', $idContrato)
    ->whereNotNull('hm.idDia')
    ->select(
        'hm.id',
        'd.dia',
        'hm.idDia',
        'hm.horaInicial',
        'hm.horaFinal',
        'hm.fechaInicial',
        'hm.fechaFinal',
        'hm.estado',
        'f.codigo as ficha',
        'm.nombreMateria'
    )
    ->orderBy('hm.idDia')
    ->orderBy('hm.horaInicial')
    ->get();

foreach ($horarios as $h) {
    echo sprintf(
        "#%s | %s (idDia=%s) | %s-%s | %s → %s | %s | %s\n",
        $h->id,
        $h->dia,
        $h->idDia,
        substr($h->horaInicial, 0, 5),
        substr($h->horaFinal, 0, 5),
        $h->fechaInicial,
        $h->fechaFinal,
        $h->estado,
        $h->nombreMateria ?? '-'
    );
}

echo "\n=== REEMPLAZOS (cubre / le cubren) ===\n";
$reemplazos = DB::table('reemplazo as r')
    ->leftJoin('dia as d', function ($j) {
        $j->on('d.id', '=', DB::raw('(SELECT idDia FROM horarioMateria WHERE id = r.idHorarioMateria LIMIT 1)'));
    })
    ->join('horarioMateria as hm', 'r.idHorarioMateria', '=', 'hm.id')
    ->leftJoin('dia as dia', 'hm.idDia', '=', 'dia.id')
    ->whereNotNull('r.idHorarioMateria')
    ->where(function ($q) use ($idContrato) {
        $q->where('r.idContratoTrabajador', $idContrato)
            ->orWhere('r.idContratoRemplazo', $idContrato);
    })
    ->select(
        'r.id',
        'r.idHorarioMateria',
        'dia.dia',
        'hm.horaInicial',
        'hm.horaFinal',
        'r.fechaInicial',
        'r.fechaFinal',
        'r.idContratoTrabajador',
        'r.idContratoRemplazo',
        'r.observacion'
    )
    ->get();

foreach ($reemplazos as $r) {
    $rol = (int) $r->idContratoRemplazo === $idContrato ? 'REEMPLAZANTE' : 'TITULAR_REEMPLAZADO';
    echo sprintf(
        "#%s | hm#%s | %s %s-%s | %s→%s | %s | tit=%s rem=%s\n",
        $r->id,
        $r->idHorarioMateria,
        $r->dia,
        substr($r->horaInicial, 0, 5),
        substr($r->horaFinal, 0, 5),
        $r->fechaInicial,
        $r->fechaFinal,
        $rol,
        $r->idContratoTrabajador,
        $r->idContratoRemplazo
    );
}

echo "\n=== HORARIOS COMPARTIDOS ===\n";
$comp = DB::table('horarioCompartido as hc')
    ->join('horarioMateria as hm', 'hc.idHorarioMateria', '=', 'hm.id')
    ->leftJoin('dia as d', 'hm.idDia', '=', 'd.id')
    ->where(function ($q) use ($idContrato) {
        $q->where('hm.idContrato', $idContrato)
            ->orWhere('hc.idContratoSecundario', $idContrato);
    })
    ->select('hc.*', 'd.dia', 'hm.horaInicial', 'hm.horaFinal', 'hm.idContrato as titularHm')
    ->get();

foreach ($comp as $c) {
    echo sprintf(
        "#%s | base hm#%s %s %s-%s | %s→%s | sec contrato=%s | clon hm#%s | %s\n",
        $c->id,
        $c->idHorarioMateria,
        $c->dia,
        substr($c->horaInicial, 0, 5),
        substr($c->horaFinal, 0, 5),
        $c->fechaInicial,
        $c->fechaFinal,
        $c->idContratoSecundario ?? 'null',
        $c->idHorarioMateriaSecundario ?? 'null',
        $c->estado
    );
}

echo "\n=== CLASES HOY MIÉRCOLES (idDia=3 en BD típico) ===\n";
$hoy = date('Y-m-d');
$diaSemana = (int) date('w'); // 0=dom 3=mié
$idDiaCarbon = $diaSemana === 0 ? 7 : $diaSemana;
echo "Hoy: {$hoy} | PHP dayOfWeek={$diaSemana} | idDia esperado={$idDiaCarbon}\n";

$dias = DB::table('dia')->get();
echo "Tabla dia:\n";
foreach ($dias as $d) {
    echo "  id={$d->id} dia={$d->dia}\n";
}

$hmHoy = DB::table('horarioMateria as hm')
    ->leftJoin('dia as d', 'hm.idDia', '=', 'd.id')
    ->where('hm.idContrato', $idContrato)
    ->where('hm.idDia', $idDiaCarbon)
    ->where('hm.fechaInicial', '<=', $hoy)
    ->where('hm.fechaFinal', '>=', $hoy)
    ->select('hm.*', 'd.dia')
    ->get();

foreach ($hmHoy as $h) {
    echo sprintf(
        "HOY titular: #%s | %s | %s-%s | fin %s\n",
        $h->id,
        $h->dia,
        substr($h->horaInicial, 0, 5),
        substr($h->horaFinal, 0, 5),
        $h->fechaFinal
    );
}

echo "\n=== RESUMEN POR DÍA (fecha fin del horario titular) ===\n";
$porDia = $horarios->groupBy('dia');
foreach ($porDia as $dia => $items) {
    $fines = $items->pluck('fechaFinal')->unique()->sort()->values();
    echo "{$dia}: termina " . $fines->implode(', ') . " (" . $items->count() . " franja(s))\n";
}
