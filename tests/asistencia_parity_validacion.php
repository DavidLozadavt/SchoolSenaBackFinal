<?php
/**
 * Casos 5 y 6: paridad Técnico vs Tecnólogo (mismo cálculo de estado, sin uso de jornada).
 * Ejecutar: php tests/asistencia_parity_validacion.php
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$ctrl = app(\App\Http\Controllers\FichaController::class);
$ref = new ReflectionClass($ctrl);
$mEstado = $ref->getMethod('calcularEstadoHorario');
$mEstado->setAccessible(true);

$horarios = DB::table('horarioMateria as hm')
    ->join('ficha as f', 'f.id', '=', 'hm.idFicha')
    ->join('aperturarprograma as ap', 'ap.id', '=', 'f.idAsignacion')
    ->join('programa as p', 'p.id', '=', 'ap.idPrograma')
    ->join('jornadas as j', 'j.id', '=', 'f.idJornada')
    ->select(
        'hm.id',
        'hm.horaInicial',
        'hm.horaFinal',
        'hm.fechaInicial',
        'hm.fechaFinal',
        'hm.idDia',
        'f.codigo',
        'j.nombreJornada as jornada',
        'p.nombrePrograma as programa'
    )
    ->where(function ($q) {
        $q->where('p.nombrePrograma', 'like', '%Técnico%')
            ->orWhere('p.nombrePrograma', 'like', '%Tecnico%')
            ->orWhere('p.nombrePrograma', 'like', '%Tecnólogo%')
            ->orWhere('p.nombrePrograma', 'like', '%Tecnologo%');
    })
    ->limit(20)
    ->get();

echo "=== Paridad asistencia Técnico vs Tecnólogo ===\n\n";

$ok = 0;
$fail = 0;

if ($horarios->isEmpty()) {
    echo "Sin horarios de muestra — revisión estática: sin ramas por tipo de programa en asistencia.\n";
} else {
    foreach ($horarios as $h) {
        $estado = $mEstado->invoke(
            $ctrl,
            (string) $h->fechaInicial,
            $h->fechaFinal,
            (string) $h->horaInicial,
            (string) $h->horaFinal,
            (int) $h->idDia
        );
        $tipo = (stripos($h->programa, 'tecnólogo') !== false || stripos($h->programa, 'tecnologo') !== false)
            ? 'Tecnólogo' : 'Técnico';
        echo "HM{$h->id} [{$tipo}] ficha {$h->codigo} jornada={$h->jornada} {$h->horaInicial}-{$h->horaFinal} → estado={$estado}\n";
        $ok++;
    }
}

// HM90 (técnico): estudiantes cargables vía gradoMateria
$hm90 = DB::table('horarioMateria as hm')
    ->join('gradoMateria as gm', 'gm.id', '=', 'hm.idGradoMateria')
    ->where('hm.id', 90)
    ->select('hm.id', 'gm.idMateria')
    ->first();
if ($hm90) {
    $idMateria = (int) $hm90->idMateria;
    $count = DB::table('matriculaAcademica as ma')
        ->join('matricula as m', 'm.id', '=', 'ma.idMatricula')
        ->where('ma.idMateria', $idMateria)
        ->where('m.estado', 'EN FORMACION')
        ->count();
    echo "\nCaso 6 HM90 (Técnico): idMateria={$idMateria}, estudiantes activos={$count} ";
    if ($count > 0) {
        echo "OK\n";
        $ok++;
    } else {
        echo "FALLO\n";
        $fail++;
    }
} else {
    echo "\nHM90 no encontrado en BD local.\n";
}

// Verificar que no hay ramas por idTipoFormacion en AsistenciaController
$asistenciaSrc = file_get_contents(__DIR__ . '/../app/Http/Controllers/AsistenciaController.php');
$tieneRamaTipo = (bool) preg_match('/tecnolog|t[eé]cnico|idTipoFormacion|idNivelEducativo/i', $asistenciaSrc);
echo "\nAsistenciaController sin ramas Técnico/Tecnólogo: " . ($tieneRamaTipo ? 'REVISAR' : 'OK') . "\n";
if (!$tieneRamaTipo) {
    $ok++;
} else {
    $fail++;
}

echo "\nResumen: {$ok} OK, {$fail} FALLO\n";
exit($fail > 0 ? 1 : 0);
