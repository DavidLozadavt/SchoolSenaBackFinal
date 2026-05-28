<?php

/**
 * Inserta 4 reemplazos y 2 horarios compartidos después de las 14:22 (hoy).
 *
 * Uso:
 *   php database/scripts/seed_demo_asignaciones_post_1422.php
 *   php database/scripts/seed_demo_asignaciones_post_1422.php --limpiar
 */

require __DIR__ . '/../../vendor/autoload.php';
$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\AsignacionSesion;
use App\Models\HorarioMateria;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

$idTitular      = (int) (getenv('DEMO_TITULAR') ?: 11);
$idReemplazante = (int) (getenv('DEMO_REEMPLAZANTE') ?: 8);
$idSecundario   = (int) (getenv('DEMO_SECUNDARIO') ?: 51);
$marcaDemo      = 'DEMO prueba post-14:22';

$limpiar = in_array('--limpiar', $argv ?? [], true) || in_array('-c', $argv ?? [], true);

$slotsReemplazo = [
    ['14:23:00', '14:24:00', 'Reemplazo 1 — Angy cubre a David'],
    ['14:25:00', '14:26:00', 'Reemplazo 2 — Angy cubre a David'],
    ['14:27:00', '14:28:00', 'Reemplazo 3 — Angy cubre a David'],
    ['14:29:00', '14:30:00', 'Reemplazo 4 — Angy cubre a David'],
];

$slotsCompartido = [
    ['14:31:00', '14:32:00', 'Compartido ACTIVO — David + contrato 51', true],
    ['14:33:00', '14:34:00', 'Compartido PENDIENTE — sin segundo instructor', false],
];

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

function limpiarDemos(): void
{
    $asigs = AsignacionSesion::where('observacion', 'like', '%DEMO prueba post-14:22%')->get();
    foreach ($asigs as $a) {
        if ($a->tipoAsignacion === 'HORARIO COMPARTIDO' && $a->idContrato) {
            $base = HorarioMateria::find($a->idHorarioMateria);
            if ($base) {
                $clon = HorarioMateria::where('idFicha', $base->idFicha)
                    ->where('idGradoMateria', $base->idGradoMateria)
                    ->where('idDia', $base->idDia)
                    ->where('horaInicial', $base->horaInicial)
                    ->where('horaFinal', $base->horaFinal)
                    ->where('idContrato', $a->idContrato)
                    ->where('id', '!=', $base->id)
                    ->first();
                if ($clon) {
                    $clon->sesionMaterias()->each(function ($s) {
                        $s->asistencia()->delete();
                        $s->delete();
                    });
                    $clon->detallesRmi()->delete();
                    $clon->delete();
                }
            }
        }
        $a->delete();
    }

    HorarioMateria::where('observacion', 'like', '%DEMO prueba post-14:22%')->each(function (HorarioMateria $hm) {
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
        limpiarDemos();
        DB::commit();
        echo "Demos post-14:22 eliminados.\n";
        exit(0);
    }

    limpiarDemos();

    $hoy = Carbon::today();
    $hoyStr = $hoy->toDateString();
    $idDia = idDiaDesdeFecha($hoy);

    foreach ([$idTitular, $idReemplazante, $idSecundario] as $cid) {
        if (!DB::table('contrato')->where('id', $cid)->exists()) {
            throw new RuntimeException("No existe contrato {$cid}.");
        }
    }

    $plantilla = HorarioMateria::where('idContrato', $idTitular)
        ->where('estado', 'ASIGNADO')
        ->whereNotNull('idDia')
        ->whereNotNull('idFicha')
        ->orderByDesc('id')
        ->first();

    if (!$plantilla) {
        throw new RuntimeException('No hay horario plantilla para el titular.');
    }

    $diaRow = DB::table('dia')->where('id', $idDia)->first();

    echo "=== Demos asignación sesión (post 14:22) ===\n";
    echo "Fecha: {$hoyStr} (" . ($diaRow->dia ?? $idDia) . ")\n";
    echo "Titular: " . nombreContrato($idTitular) . " (#{$idTitular})\n";
    echo "Reemplazante: " . nombreContrato($idReemplazante) . " (#{$idReemplazante})\n";
    echo "Secundario compartido: " . nombreContrato($idSecundario) . " (#{$idSecundario})\n\n";

    $creadosReemplazo = [];
    foreach ($slotsReemplazo as $i => [$hi, $hf, $nota]) {
        $horario = $plantilla->replicate();
        $horario->idContrato   = $idTitular;
        $horario->idDia        = $idDia;
        $horario->horaInicial  = $hi;
        $horario->horaFinal    = $hf;
        $horario->fechaInicial = $hoyStr;
        $horario->fechaFinal   = $hoyStr;
        $horario->estado       = 'ASIGNADO';
        $horario->observacion  = $marcaDemo;
        $horario->save();
        HorarioMateria::generarRmis($horario);

        $asig = AsignacionSesion::create([
            'idHorarioMateria' => $horario->id,
            'idContrato'       => $idReemplazante,
            'tipoAsignacion'   => 'REEMPLAZO',
            'fechaInicio'      => $hoyStr,
            'fechaFin'         => $hoyStr,
            'observacion'      => "{$marcaDemo} | {$nota}",
        ]);

        $creadosReemplazo[] = ['horario' => $horario->id, 'asig' => $asig->id, 'hora' => "{$hi}-{$hf}"];
    }

    $creadosCompartido = [];
    foreach ($slotsCompartido as [$hi, $hf, $nota, $conSecundario]) {
        $horario = $plantilla->replicate();
        $horario->idContrato   = $idTitular;
        $horario->idDia        = $idDia;
        $horario->horaInicial  = $hi;
        $horario->horaFinal    = $hf;
        $horario->fechaInicial = $hoyStr;
        $horario->fechaFinal   = $hoyStr;
        $horario->estado       = 'ASIGNADO';
        $horario->observacion  = $marcaDemo;
        $horario->save();
        HorarioMateria::generarRmis($horario);

        $asig = AsignacionSesion::create([
            'idHorarioMateria' => $horario->id,
            'idContrato'       => $conSecundario ? $idSecundario : null,
            'tipoAsignacion'   => 'HORARIO COMPARTIDO',
            'fechaInicio'      => $hoyStr,
            'fechaFin'         => $hoyStr,
            'observacion'      => "{$marcaDemo} | {$nota}",
        ]);

        $clonId = null;
        if ($conSecundario) {
            $clon = HorarioMateria::duplicarParaAsignacionCompartida($asig);
            $clonId = $clon?->id;
        }

        $creadosCompartido[] = [
            'horario' => $horario->id,
            'asig'    => $asig->id,
            'clon'    => $clonId,
            'hora'    => "{$hi}-{$hf}",
            'estado'  => $conSecundario ? 'ACTIVO' : 'PENDIENTE',
        ];
    }

    DB::commit();

    echo "--- 4 REEMPLAZOS ---\n";
    foreach ($creadosReemplazo as $n => $r) {
        echo ($n + 1) . ". hm #{$r['horario']} | asig #{$r['asig']} | {$r['hora']}\n";
    }

    echo "\n--- 2 HORARIO COMPARTIDO ---\n";
    foreach ($creadosCompartido as $n => $r) {
        echo ($n + 1) . ". hm #{$r['horario']} | asig #{$r['asig']} | {$r['hora']} | {$r['estado']}";
        if ($r['clon']) {
            echo " | clon #{$r['clon']}";
        }
        echo "\n";
    }

    echo "\n=== Probar ===\n";
    echo "• Reemplazante (" . nombreContrato($idReemplazante) . "): Mis formaciones / ambiente virtual → badge Reemplazo.\n";
    echo "• Titular (" . nombreContrato($idTitular) . "): ve franjas compartidas y que otro lo reemplaza.\n";
    echo "• Malla RAP: compartido PENDIENTE (14:33) debe pedir segundo instructor.\n";
    echo "• Limpiar: php database/scripts/seed_demo_asignaciones_post_1422.php --limpiar\n\n";
    echo "Listo.\n";
} catch (Throwable $e) {
    DB::rollBack();
    echo 'ERROR: ' . $e->getMessage() . "\n";
    exit(1);
}
