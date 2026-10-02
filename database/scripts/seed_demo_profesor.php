<?php

require __DIR__ . '/../../vendor/autoload.php';
$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\AsignacionSesion;
use App\Models\HorarioCompartido;
use App\Models\HorarioMateria;
use Illuminate\Support\Facades\DB;

// Profesor titular: Angy Muñoz (NO David Lozada)
$idTitular = 8;
$idReemplazante = 34;  // KELLY JOAQUI
$idSecundario = 9;     // Raul Gomez

DB::beginTransaction();

try {
    // Limpiar demos anteriores (David u otros)
    $demoReemplazos = AsignacionSesion::where('observacion', 'like', '%demo%')->get();
    foreach ($demoReemplazos as $r) {
        $r->delete();
    }

    $demoCompartidos = HorarioCompartido::where('observacion', 'like', '%demo%')->get();
    foreach ($demoCompartidos as $c) {
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
        $c->delete();
    }

    HorarioCompartido::where('observacion', 'like', '%pendiente de asignar%')->delete();

    $titular = DB::table('contrato as c')
        ->join('persona as p', 'c.idpersona', '=', 'p.id')
        ->where('c.id', $idTitular)
        ->select(DB::raw("CONCAT(p.nombre1,' ',p.apellido1) as nombre"))
        ->first();

    if (!$titular) {
        throw new RuntimeException("No existe el contrato titular {$idTitular}.");
    }

    $horarioTitular = HorarioMateria::where('idContrato', $idTitular)
        ->where('estado', 'ASIGNADO')
        ->whereNotNull('idDia')
        ->orderByDesc('id')
        ->first();

    if (!$horarioTitular) {
        throw new RuntimeException("No hay horario ASIGNADO para {$titular->nombre} (contrato {$idTitular}).");
    }

    $horarioTitular2 = HorarioMateria::where('idContrato', $idTitular)
        ->where('estado', 'ASIGNADO')
        ->where('id', '!=', $horarioTitular->id)
        ->whereNotNull('idDia')
        ->orderByDesc('id')
        ->first();

    if (!$horarioTitular2) {
        throw new RuntimeException('No hay segunda clase titular para el profesor.');
    }

    echo "Profesor titular: {$titular->nombre} (contrato {$idTitular})\n";
    echo "Clase titular 1: horarioMateria #{$horarioTitular->id} | Ficha {$horarioTitular->idFicha}\n";
    echo "Clase titular 2: horarioMateria #{$horarioTitular2->id}\n";

    $fechaInicioReemplazo = '2026-05-27';
    $fechaFinReemplazo    = '2026-06-10';

    $reemplazo = AsignacionSesion::create([
        'idHorarioMateria'     => $horarioTitular->id,
        'idContratoTrabajador' => $idTitular,
        'idContrato'           => $idReemplazante,
        'fechaInicio'          => $fechaInicioReemplazo,
        'fechaFin'             => $fechaFinReemplazo,
        'observacion'          => 'Reemplazo demo: instructor cubre a Angy Muñoz',
        'estado'               => 'ACTIVO',
    ]);

    echo "Reemplazo creado: reemplazo #{$reemplazo->id} | horario #{$horarioTitular->id}\n";
    echo "  Titular {$idTitular} -> Reemplazante {$idReemplazante} | {$fechaInicioReemplazo} a {$fechaFinReemplazo}\n";

    $compartido = HorarioCompartido::create([
        'idHorarioMateria'     => $horarioTitular2->id,
        'idContratoSecundario' => $idSecundario,
        'fechaInicial'         => $horarioTitular2->fechaInicial,
        'fechaFinal'           => $horarioTitular2->fechaFinal,
        'observacion'          => 'Horario compartido demo: Angy Muñoz + Raul Gomez',
        'estado'               => 'ACTIVO',
    ]);

    $clon = HorarioMateria::duplicarParaHorarioCompartido($compartido);
    if ($clon) {
        $compartido->update(['idHorarioMateriaSecundario' => $clon->id]);
        echo "Horario compartido ACTIVO: horarioCompartido #{$compartido->id}\n";
        echo "  Base #{$horarioTitular2->id} | Clon secundario #{$clon->id} | Secundario contrato {$idSecundario}\n";
    }

    $horarioPendiente = HorarioMateria::where('idContrato', $idTitular)
        ->where('estado', 'ASIGNADO')
        ->whereNotIn('id', array_filter([$horarioTitular->id, $horarioTitular2->id, $clon->id ?? null]))
        ->whereNotNull('idDia')
        ->orderByDesc('id')
        ->first();

    if ($horarioPendiente) {
        $pendiente = HorarioCompartido::create([
            'idHorarioMateria' => $horarioPendiente->id,
            'fechaInicial'     => $horarioPendiente->fechaInicial,
            'fechaFinal'       => $horarioPendiente->fechaFinal,
            'observacion'      => 'Horario compartido pendiente de asignar segundo instructor',
            'estado'           => 'PENDIENTE',
        ]);
        echo "Horario compartido PENDIENTE: horarioCompartido #{$pendiente->id} | horario #{$horarioPendiente->id}\n";
    }

    DB::commit();

    echo "\n=== RESUMEN ===\n";
    echo "Titular: {$titular->nombre} (contrato {$idTitular})\n";
    echo "Reemplazos de clase: " . AsignacionSesion::where('idContratoTrabajador', $idTitular)->count() . "\n";
    echo "Horarios compartidos del titular: " . HorarioCompartido::whereIn('idHorarioMateria', [$horarioTitular2->id, $horarioPendiente->id ?? 0])->count() . "\n";
    echo "\nListo.\n";
} catch (Throwable $e) {
    DB::rollBack();
    echo 'ERROR: ' . $e->getMessage() . "\n";
    exit(1);
}
