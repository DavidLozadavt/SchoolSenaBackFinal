<?php
/**
 * Validación post-corrección: horas literales horarioMateria (24h), sin ajuste por jornada.
 * Ejecutar: php tests/horario_24h_validacion.php
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

function enVentana24h(string $horaIni, string $horaFin, DateTime $ahora): bool
{
    [$hIni, $mIni] = array_map('intval', explode(':', substr($horaIni, 0, 5)));
    [$hFin, $mFin] = array_map('intval', explode(':', substr($horaFin, 0, 5)));
    $inicio = (clone $ahora)->setTime($hIni, $mIni, 0);
    $fin = (clone $ahora)->setTime($hFin, $mFin, 0);
    if ($fin < $inicio) {
        $fin->modify('+1 day');
    }
    return $ahora >= $inicio && $ahora <= $fin;
}

function estadoClaseSim(string $horaIni, string $horaFin, string $fechaIni, string $fechaFin, int $idDia, DateTime $ahora): string
{
    $hoy = (clone $ahora)->setTime(0, 0, 0);
    $fi = new DateTime(substr($fechaIni, 0, 10));
    $ff = new DateTime(substr($fechaFin, 0, 10));
    if ($hoy < $fi || $hoy > $ff) {
        return 'PENDIENTE';
    }
    $carbonDay = (int) $ahora->format('w');
    $diaJs = $idDia === 7 ? 0 : $idDia;
    if ($carbonDay !== $diaJs) {
        return 'PENDIENTE';
    }
    return enVentana24h($horaIni, $horaFin, $ahora) ? 'EN_CURSO' : 'PENDIENTE';
}

$ctrl = app(\App\Http\Controllers\FichaController::class);
$ref = new ReflectionClass($ctrl);
$mCarbon = $ref->getMethod('carbonFinVentanaClaseDia');
$mCarbon->setAccessible(true);

$casos = [
    [
        'nombre' => 'Caso 1 MAÑANA 07:00-13:00',
        'horaIni' => '07:00', 'horaFin' => '13:00',
        'jornada' => 'MAÑANA', 'idDia' => 1,
        'fechaIni' => '2026-01-01', 'fechaFin' => '2026-12-31',
        'dentro' => '2026-06-01 10:00:00',
        'fuera' => '2026-06-01 14:00:00',
    ],
    [
        'nombre' => 'Caso 2 TARDE 13:00-19:00',
        'horaIni' => '13:00', 'horaFin' => '19:00',
        'jornada' => 'TARDE', 'idDia' => 2,
        'fechaIni' => '2026-01-01', 'fechaFin' => '2026-12-31',
        'dentro' => '2026-06-02 15:00:00',
        'fuera' => '2026-06-02 10:00:00',
    ],
    [
        'nombre' => 'Caso 3 NOCHE 18:00-22:00',
        'horaIni' => '18:00', 'horaFin' => '22:00',
        'jornada' => 'NOCHE', 'idDia' => 3,
        'fechaIni' => '2026-01-01', 'fechaFin' => '2026-12-31',
        'dentro' => '2026-06-03 19:00:00',
        'fuera' => '2026-06-03 10:00:00',
    ],
    [
        'nombre' => 'Caso 4 HM90 NOCHE+08:00-13:00',
        'horaIni' => '08:00', 'horaFin' => '13:00',
        'jornada' => 'NOCHE', 'idDia' => 1,
        'fechaIni' => '2026-04-04', 'fechaFin' => '2026-07-04',
        'dentro' => '2026-06-01 10:00:00',
        'fuera' => '2026-06-01 20:30:00',
    ],
];

echo "=== Validación horarios 24h (sin ajuste jornada) ===\n\n";
$ok = 0;
$fail = 0;

foreach ($casos as $c) {
    $dentro = new DateTime($c['dentro']);
    $fuera = new DateTime($c['fuera']);
    $estDentro = estadoClaseSim($c['horaIni'], $c['horaFin'], $c['fechaIni'], $c['fechaFin'], $c['idDia'], $dentro);
    $estFuera = estadoClaseSim($c['horaIni'], $c['horaFin'], $c['fechaIni'], $c['fechaFin'], $c['idDia'], $fuera);
    $finCarbon = $mCarbon->invoke($ctrl, substr($c['dentro'], 0, 10), $c['horaIni'] . ':00', $c['horaFin'] . ':00', $c['jornada']);

    $pasaDentro = $estDentro === 'EN_CURSO';
    $pasaFuera = $estFuera === 'PENDIENTE';
    $pasa = $pasaDentro && $pasaFuera;
    if ($pasa) {
        $ok++;
    } else {
        $fail++;
    }

    echo $c['nombre'] . "\n";
    echo "  Dentro ({$c['dentro']}): {$estDentro} " . ($pasaDentro ? 'OK' : 'FALLO') . "\n";
    echo "  Fuera  ({$c['fuera']}): {$estFuera} " . ($pasaFuera ? 'OK' : 'FALLO') . "\n";
    echo "  carbonFinVentana fin: " . ($finCarbon ? $finCarbon->format('H:i') : 'null') . " (esperado {$c['horaFin']})\n\n";
}

$hm90 = DB::table('horarioMateria')->where('id', 90)->first();
if ($hm90) {
    $est10 = estadoClaseSim($hm90->horaInicial, $hm90->horaFinal, $hm90->fechaInicial, $hm90->fechaFinal, (int) $hm90->idDia, new DateTime('2026-06-01 10:00:00'));
    $est2030 = estadoClaseSim($hm90->horaInicial, $hm90->horaFinal, $hm90->fechaInicial, $hm90->fechaFinal, (int) $hm90->idDia, new DateTime('2026-06-01 20:30:00'));
    echo "HM90 BD real (id=90)\n";
    echo "  10:00 lunes: {$est10} " . ($est10 === 'EN_CURSO' ? 'OK' : 'FALLO') . "\n";
    echo "  20:30 lunes: {$est2030} " . ($est2030 === 'PENDIENTE' ? 'OK' : 'FALLO') . "\n";
    if ($est10 === 'EN_CURSO' && $est2030 === 'PENDIENTE') {
        $ok++;
    } else {
        $fail++;
    }
}

echo "\nResumen: {$ok} OK, {$fail} FALLO\n";
exit($fail > 0 ? 1 : 0);
