<?php

/**
 * Clase de prueba REEMPLAZO solo para hoy (12:30–12:32 p. m.).
 *
 * Uso:
 *   php database/scripts/seed_clase_reemplazo_hoy.php
 *   php database/scripts/seed_clase_reemplazo_hoy.php --limpiar
 */

require __DIR__ . '/../../vendor/autoload.php';
$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\AsignacionSesion;
use App\Models\HorarioMateria;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

$idTitular      = (int) (getenv('DEMO_TITULAR') ?: 11);      // David Lozada (cubre su franja)
$idReemplazante = (int) (getenv('DEMO_REEMPLAZANTE') ?: 8);  // Angy Muñoz
$horaInicial    = getenv('DEMO_HORA_INI') ?: '12:44:00';
$horaFinal      = getenv('DEMO_HORA_FIN') ?: '12:45:00';
$marcaDemo      = 'DEMO clase reemplazo hoy 12:44-12:45';

$limpiar = in_array('--limpiar', $argv ?? [], true) || in_array('-c', $argv ?? [], true);

function nombreContrato(int $id): string
{
    $row = DB::table('contrato as c')
        ->join('persona as p', 'c.idpersona', '=', 'p.id')
        ->where('c.id', $id)
        ->select(DB::raw("TRIM(CONCAT(COALESCE(p.nombre1,''), ' ', COALESCE(p.apellido1,''))) as nombre"))
        ->first();

    return $row->nombre ?? "(contrato {$id})";
}

function idDiaDesdeFecha(Carbon $fecha): int
{
    return (int) $fecha->format('N');
}

function limpiarDemosReemplazo(): void
{
    $reemplazos = AsignacionSesion::where('observacion', 'like', '%DEMO clase reemplazo hoy%')->get();
    foreach ($reemplazos as $r) {
        $r->delete();
    }

    HorarioMateria::where('observacion', 'like', '%DEMO clase reemplazo hoy%')->each(function (HorarioMateria $hm) {
        $hm->sesionMaterias()->each(function ($s) {
            $s->asistencia()->delete();
            $s->delete();
        });
        $hm->detallesRmi()->delete();
        $hm->delete();
    });
}

DB::beginTransaction();

try {
    if ($limpiar) {
        limpiarDemosReemplazo();
        DB::commit();
        echo "Demos de reemplazo eliminados.\n";
        exit(0);
    }

    limpiarDemosReemplazo();

    $hoy = Carbon::today();
    $hoyStr = $hoy->toDateString();
    $idDia = idDiaDesdeFecha($hoy);

    $titularNombre = nombreContrato($idTitular);
    $reemplNombre  = nombreContrato($idReemplazante);

    if (!DB::table('contrato')->where('id', $idTitular)->exists()) {
        throw new RuntimeException("No existe contrato titular {$idTitular}.");
    }
    if (!DB::table('contrato')->where('id', $idReemplazante)->exists()) {
        throw new RuntimeException("No existe contrato reemplazante {$idReemplazante}.");
    }
    if ($idTitular === $idReemplazante) {
        throw new RuntimeException('Titular y reemplazante deben ser contratos distintos.');
    }

    $plantilla = HorarioMateria::where('idContrato', $idTitular)
        ->where('estado', 'ASIGNADO')
        ->whereNotNull('idDia')
        ->whereNotNull('idFicha')
        ->orderByDesc('id')
        ->first();

    if (!$plantilla) {
        throw new RuntimeException("No hay horario ASIGNADO para {$titularNombre} (contrato {$idTitular}).");
    }

    $diaRow = DB::table('dia')->where('id', $idDia)->first();
    $nombreDia = $diaRow->dia ?? "idDia {$idDia}";

    echo "=== Clase reemplazo de prueba ===\n";
    echo "Fecha: {$hoyStr} ({$nombreDia})\n";
    echo "Franja: {$horaInicial} – {$horaFinal}\n";
    echo "Titular (ausente): {$titularNombre} (contrato {$idTitular})\n";
    echo "Reemplazante: {$reemplNombre} (contrato {$idReemplazante})\n";
    echo "Plantilla horario: #{$plantilla->id}\n\n";

    $horario = $plantilla->replicate();
    $horario->idContrato   = $idTitular;
    $horario->idDia        = $idDia;
    $horario->horaInicial  = $horaInicial;
    $horario->horaFinal    = $horaFinal;
    $horario->fechaInicial = $hoyStr;
    $horario->fechaFinal   = $hoyStr;
    $horario->estado       = 'ASIGNADO';
    $horario->observacion  = $marcaDemo;
    $horario->save();

    HorarioMateria::generarRmis($horario);

    $reemplazo = AsignacionSesion::create([
        'idHorarioMateria'     => $horario->id,
        'idContratoTrabajador' => $idTitular,
        'idContrato'           => $idReemplazante,
        'fechaInicio'          => $hoyStr,
        'fechaFin'             => $hoyStr,
        'observacion'          => $marcaDemo,
        'estado'               => 'ACTIVO',
    ]);

    DB::commit();

    echo "Horario titular:   #{$horario->id}\n";
    echo "Reemplazo:         #{$reemplazo->id} (ACTIVO)\n\n";

    echo "=== Cómo probar ===\n";
    echo "• Entra con {$reemplNombre} → Pendientes / En curso con badge «Reemplazo».\n";
    echo "• Entra con {$titularNombre} → verá que otro cubre la franja hoy.\n";
    echo "• Detalle reemplazante: /ambiente-virtual/clase/{$horario->id}\n";
    echo "• Limpiar: php database/scripts/seed_clase_reemplazo_hoy.php --limpiar\n\n";
    echo "Listo.\n";
} catch (Throwable $e) {
    DB::rollBack();
    echo 'ERROR: ' . $e->getMessage() . "\n";
    exit(1);
}
