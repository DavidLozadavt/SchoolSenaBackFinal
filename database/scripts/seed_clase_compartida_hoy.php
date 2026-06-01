<?php

/**
 * Clase de prueba COMPARTIDA solo para hoy (12:00–12:04 p. m.).
 *
 * Uso:
 *   php database/scripts/seed_clase_compartida_hoy.php
 *   php database/scripts/seed_clase_compartida_hoy.php --limpiar
 *
 * Variables al inicio del script (contratos / horas).
 */

require __DIR__ . '/../../vendor/autoload.php';
$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\HorarioCompartido;
use App\Models\HorarioMateria;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

// ─── Configuración ───────────────────────────────────────────────────────────
$idTitular     = (int) (getenv('DEMO_TITULAR') ?: 11);   // David Lozada
$idSecundario  = (int) (getenv('DEMO_SECUNDARIO') ?: 8); // Angy Muñoz (cambiar si hace falta)
$horaInicial   = getenv('DEMO_HORA_INI') ?: '12:10:00';
$horaFinal     = getenv('DEMO_HORA_FIN') ?: '12:14:00';
$marcaDemo     = 'DEMO clase compartida hoy 12:10-12:14';

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

/** idDia en BD: 1=Lun … 7=Dom (convención del proyecto). */
function idDiaDesdeFecha(Carbon $fecha): int
{
    $phpDow = (int) $fecha->format('N'); // 1=Lun … 7=Dom
    return $phpDow;
}

function limpiarDemosAnteriores(): void
{
    $compartidos = HorarioCompartido::where('observacion', 'like', '%DEMO clase compartida hoy%')->get();
    foreach ($compartidos as $c) {
        if ($c->idHorarioMateriaSecundario) {
            $clon = HorarioMateria::find($c->idHorarioMateriaSecundario);
            if ($clon) {
                $clon->sesionMaterias()->each(function ($s) {
                    $s->asistencia()->delete();
                    $s->delete();
                });
                $clon->detallesRmi()->delete();
                $clon->delete();
            }
        }
        $base = HorarioMateria::find($c->idHorarioMateria);
        if ($base && str_contains((string) $base->observacion, 'DEMO clase compartida hoy')) {
            $base->sesionMaterias()->each(function ($s) {
                $s->asistencia()->delete();
                $s->delete();
            });
            $base->detallesRmi()->delete();
            $base->delete();
        }
        $c->delete();
    }

    HorarioMateria::where('observacion', 'like', '%DEMO clase compartida hoy%')->each(function (HorarioMateria $hm) {
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
        limpiarDemosAnteriores();
        DB::commit();
        echo "Demos anteriores eliminados.\n";
        exit(0);
    }

    limpiarDemosAnteriores();

    $hoy = Carbon::today();
    $hoyStr = $hoy->toDateString();
    $idDia = idDiaDesdeFecha($hoy);

    $titularNombre = nombreContrato($idTitular);
    $secNombre     = nombreContrato($idSecundario);

    if (!DB::table('contrato')->where('id', $idTitular)->exists()) {
        throw new RuntimeException("No existe contrato titular {$idTitular}.");
    }
    if (!DB::table('contrato')->where('id', $idSecundario)->exists()) {
        throw new RuntimeException("No existe contrato secundario {$idSecundario}.");
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

    echo "=== Clase compartida de prueba ===\n";
    echo "Fecha: {$hoyStr} ({$nombreDia})\n";
    echo "Franja: {$horaInicial} – {$horaFinal}\n";
    echo "Titular: {$titularNombre} (contrato {$idTitular})\n";
    echo "Secundario: {$secNombre} (contrato {$idSecundario})\n";
    echo "Plantilla horario: #{$plantilla->id}\n\n";

    $horarioTitular = $plantilla->replicate();
    $horarioTitular->idContrato   = $idTitular;
    $horarioTitular->idDia        = $idDia;
    $horarioTitular->horaInicial  = $horaInicial;
    $horarioTitular->horaFinal    = $horaFinal;
    $horarioTitular->fechaInicial = $hoyStr;
    $horarioTitular->fechaFinal   = $hoyStr;
    $horarioTitular->estado       = 'ASIGNADO';
    $horarioTitular->observacion  = $marcaDemo;
    $horarioTitular->save();

    HorarioMateria::generarRmis($horarioTitular);

    $compartido = HorarioCompartido::create([
        'idHorarioMateria'     => $horarioTitular->id,
        'idContratoSecundario' => $idSecundario,
        'fechaInicial'         => $hoyStr,
        'fechaFinal'           => $hoyStr,
        'observacion'          => $marcaDemo,
        'estado'               => 'ACTIVO',
    ]);

    $clon = HorarioMateria::duplicarParaHorarioCompartido($compartido);
    if (!$clon) {
        throw new RuntimeException('No se pudo duplicar el horario para el instructor secundario.');
    }

    $compartido->update(['idHorarioMateriaSecundario' => $clon->id]);

    DB::commit();

    echo "Creado horario titular:     #{$horarioTitular->id}\n";
    echo "Horario compartido:         #{$compartido->id} (ACTIVO)\n";
    echo "Horario secundario (clon):  #{$clon->id}\n\n";

    echo "=== Cómo probar ===\n";
    echo "1. Antes de {$horaInicial} → debe aparecer en PENDIENTES con badge «Compartido».\n";
    echo "2. Entre {$horaInicial} y {$horaFinal} → EN CURSO (titular y secundario).\n";
    echo "3. Después de {$horaFinal} → COMPLETADA al registrar sesión en el detalle.\n";
    echo "4. Detalle: /ambiente-virtual/clase/{$horarioTitular->id}\n";
    echo "5. Limpiar: php database/scripts/seed_clase_compartida_hoy.php --limpiar\n\n";
    echo "Listo.\n";
} catch (Throwable $e) {
    DB::rollBack();
    echo 'ERROR: ' . $e->getMessage() . "\n";
    exit(1);
}
