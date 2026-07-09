<?php

/**
 * Provisiona horario fijo de colegio (apertura + ficha + grado) para un ESTUDIANTEUP.
 *
 * Uso: php scripts/bootstrap_horario_colegio_estudiante.php 1083882813 [idFicha]
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

$doc = $argv[1] ?? null;
$idFichaObjetivo = isset($argv[2]) ? (int) $argv[2] : 75;

if (!$doc) {
    fwrite(STDERR, "Uso: php scripts/bootstrap_horario_colegio_estudiante.php <identificacion> [idFicha]\n");
    exit(1);
}

$persona = DB::table('persona')->where('identificacion', $doc)->first();
if (!$persona) {
    fwrite(STDERR, "Persona no encontrada\n");
    exit(1);
}

$ficha = DB::table('ficha as f')
    ->join('aperturarprograma as ap', 'ap.id', '=', 'f.idAsignacion')
    ->join('programa as p', 'p.id', '=', 'ap.idPrograma')
    ->join('grado as g', 'g.id', '=', 'f.idGrado')
    ->where('f.id', $idFichaObjetivo)
    ->select(
        'f.id as idFicha',
        'f.idAsignacion',
        'f.idGrado',
        'f.idSede',
        'f.codigo as codigoFicha',
        'g.nombreGrado',
        'p.nombrePrograma',
        'ap.idPrograma',
        'ap.fechaInicialClases',
        'ap.fechaFinalClases'
    )
    ->first();

if (!$ficha) {
    fwrite(STDERR, "Ficha {$idFichaObjetivo} no encontrada\n");
    exit(1);
}

$gradoPrograma = DB::table('gradoPrograma')
    ->where('idGrado', $ficha->idGrado)
    ->where('idPrograma', $ficha->idPrograma)
    ->first();

if (!$gradoPrograma) {
    fwrite(STDERR, "No hay gradoPrograma para grado {$ficha->idGrado} y programa {$ficha->idPrograma}\n");
    exit(1);
}

$refMateria = DB::table('materia')->where('id', 1)->first();
$idEmpresa = (int) ($refMateria->idEmpresa ?? 1);
$idArea = (int) ($refMateria->idAreaConocimiento ?? 1);

$fechaInicio = Carbon::parse($ficha->fechaInicialClases);
$fechaFin = Carbon::parse($ficha->fechaFinalClases);
if ($fechaFin->diffInDays($fechaInicio) < 30) {
    $fechaFin = Carbon::parse('2026-11-28');
    DB::table('aperturarprograma')
        ->where('id', $ficha->idAsignacion)
        ->update([
            'fechaInicialClases' => '2026-02-02',
            'fechaFinalClases' => '2026-11-28',
            'updated_at' => now(),
        ]);
    $fechaInicio = Carbon::parse('2026-02-02');
    echo "Apertura {$ficha->idAsignacion} extendida a año lectivo 2026-02-02 — 2026-11-28\n";
}

$contratoId = (int) DB::table('contrato')->where('idEstado', 1)->where('idempresa', $idEmpresa)->value('id');
$aulaId = (int) (DB::table('infraestructura')->where('idSede', $ficha->idSede ?? 6)->value('id') ?? 4);

/** Materias típicas de colegio — horario semanal fijo */
$planColegio = [
    ['nombre' => 'MATEMATICAS PRIMERO', 'slots' => [[1, '07:00:00', '08:00:00'], [3, '07:00:00', '08:00:00'], [5, '08:00:00', '09:00:00']]],
    ['nombre' => 'LENGUA CASTELLANA PRIMERO', 'slots' => [[1, '08:00:00', '09:00:00'], [3, '08:00:00', '09:00:00'], [5, '09:00:00', '10:00:00']]],
    ['nombre' => 'CIENCIAS NATURALES PRIMERO', 'slots' => [[1, '09:00:00', '10:00:00'], [4, '07:00:00', '08:00:00']]],
    ['nombre' => 'CIENCIAS SOCIALES PRIMERO', 'slots' => [[2, '07:00:00', '08:00:00'], [4, '08:00:00', '09:00:00']]],
    ['nombre' => 'INGLES PRIMERO', 'slots' => [[2, '08:00:00', '09:00:00'], [4, '09:00:00', '10:00:00']]],
    ['nombre' => 'EDUCACION FISICA PRIMERO', 'slots' => [[2, '09:00:00', '10:00:00'], [5, '07:00:00', '08:00:00']]],
    ['nombre' => 'EDUCACION ARTISTICA PRIMERO', 'slots' => [[3, '09:00:00', '10:00:00']]],
];

DB::beginTransaction();

try {
    $matricula = DB::table('matricula')->where('idPersona', $persona->id)->orderByDesc('id')->first();
    if ($matricula) {
        DB::table('matricula')->where('id', $matricula->id)->update([
            'estado' => 'MATRICULADO',
            'idGrado' => $ficha->idGrado,
            'idFicha' => $ficha->idFicha,
            'idCompany' => $idEmpresa,
            'updated_at' => now(),
        ]);
        $idMatricula = (int) $matricula->id;
        echo "Matricula {$idMatricula} → ficha {$ficha->idFicha}, grado {$ficha->idGrado}, MATRICULADO\n";
    } else {
        $idMatricula = DB::table('matricula')->insertGetId([
            'fecha' => now()->toDateString(),
            'idPersona' => $persona->id,
            'idCompany' => $idEmpresa,
            'idGrado' => $ficha->idGrado,
            'idFicha' => $ficha->idFicha,
            'estado' => 'MATRICULADO',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        echo "Matricula nueva id={$idMatricula}\n";
    }

    DB::table('horarioMateria')->where('idFicha', $ficha->idFicha)->delete();
    DB::table('matriculaAcademica')->where('idMatricula', $idMatricula)->where('idFicha', $ficha->idFicha)->delete();

    $horariosCreados = 0;

    foreach ($planColegio as $item) {
        $materia = DB::table('materia')
            ->where('nombreMateria', $item['nombre'])
            ->where('idEmpresa', $idEmpresa)
            ->first();

        if (!$materia) {
            $idMateria = DB::table('materia')->insertGetId([
                'nombreMateria' => $item['nombre'],
                'descripcion' => 'Materia colegio — horario fijo semanal',
                'idEmpresa' => $idEmpresa,
                'idCompany' => $idEmpresa,
                'idAreaConocimiento' => $idArea,
                'codigo' => strtoupper(substr(preg_replace('/[^A-Z0-9]/', '', $item['nombre']), 0, 8)),
                'horas' => 4,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            $idMateria = (int) $materia->id;
        }

        $gradoMateria = DB::table('gradoMateria')
            ->where('idGradoPrograma', $gradoPrograma->id)
            ->where('idMateria', $idMateria)
            ->first();

        if (!$gradoMateria) {
            $idGradoMateria = DB::table('gradoMateria')->insertGetId([
                'idGradoPrograma' => $gradoPrograma->id,
                'idMateria' => $idMateria,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            $idGradoMateria = (int) $gradoMateria->id;
        }

        $existsMa = DB::table('matriculaAcademica')
            ->where('idMatricula', $idMatricula)
            ->where('idFicha', $ficha->idFicha)
            ->where('idMateria', $idMateria)
            ->exists();

        if (!$existsMa) {
            DB::table('matriculaAcademica')->insert([
                'idFicha' => $ficha->idFicha,
                'idGradoMateria' => $idGradoMateria,
                'idMatricula' => $idMatricula,
                'idMateria' => $idMateria,
                'estado' => 'MATRICULADO',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        foreach ($item['slots'] as [$idDia, $horaIni, $horaFin]) {
            DB::table('horarioMateria')->insert([
                'horaInicial' => $horaIni,
                'horaFinal' => $horaFin,
                'estado' => 'ASIGNADO',
                'idGradoMateria' => $idGradoMateria,
                'idDia' => $idDia,
                'idInfraestructura' => $aulaId,
                'idFicha' => $ficha->idFicha,
                'fechaInicial' => $fechaInicio->toDateString(),
                'fechaFinal' => $fechaFin->toDateString(),
                'idContrato' => $contratoId ?: null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $horariosCreados++;
        }
    }

    DB::commit();

    echo "OK — {$persona->nombre1} {$persona->apellido1}\n";
    echo "Programa: {$ficha->nombrePrograma} | Ficha: {$ficha->codigoFicha} ({$ficha->nombreGrado})\n";
    echo "Apertura: {$ficha->idAsignacion} | Horarios fijos creados: {$horariosCreados}\n";
} catch (Throwable $e) {
    DB::rollBack();
    fwrite(STDERR, 'Error: ' . $e->getMessage() . "\n");
    exit(1);
}
