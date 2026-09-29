<?php
/**
 * Seed aprendices para fichas PRI-SIRLEY-3A (40) y SEC-SIRLEY-8B (41)
 * para que Asignar actividad liste estudiantes.
 *
 *   php database/scripts/seed_aprendices_sirley_planeacion.php
 */

require __DIR__ . '/../../vendor/autoload.php';
$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

echo "=== Seed aprendices Sirley ===\n";

$idCompany = (int) (DB::table('contrato')->where('id', 91)->value('idCompany') ?: 1);

$fichas = DB::table('ficha')->whereIn('codigo', ['PRI-SIRLEY-3A', 'SEC-SIRLEY-8B'])->get();
if ($fichas->isEmpty()) {
    $fichas = DB::table('ficha')->whereIn('id', [40, 41])->get();
}

$nombres = [
    ['CAMILA', 'ANDREA', 'LOPEZ', 'GARCIA'],
    ['JUAN', 'DAVID', 'MARTINEZ', 'ROJAS'],
    ['VALENTINA', 'SOFIA', 'PEREZ', 'GOMEZ'],
    ['SANTIAGO', 'ANDRES', 'RAMIREZ', 'DIAZ'],
    ['ISABELLA', 'MARIA', 'TORRES', 'CASTRO'],
    ['MATIAS', 'FELIPE', 'HERRERA', 'MORALES'],
    ['SOFIA', 'ALEJANDRA', 'RUIZ', 'VARGAS'],
    ['DANIEL', 'ESTEBAN', 'MENDOZA', 'SILVA'],
    ['MARIA', 'JOSE', 'ORTEGA', 'NUNEZ'],
    ['NICOLAS', 'ALEJANDRO', 'FLORES', 'MEJIA'],
    ['LUCIA', 'FERNANDA', 'CASTILLO', 'PENA'],
    ['SEBASTIAN', 'IVAN', 'GUERRERO', 'RIO'],
];

function resolverGradoYMaterias(int $idFicha): array
{
    $rows = DB::table('horarioMateria as hm')
        ->join('gradoMateria as gm', 'hm.idGradoMateria', '=', 'gm.id')
        ->join('gradoPrograma as gp', 'gm.idGradoPrograma', '=', 'gp.id')
        ->where('hm.idFicha', $idFicha)
        ->select('gp.idGrado', 'gm.idMateria', 'gm.id as idGradoMateria')
        ->get();

    $idGrado = (int) ($rows->first()->idGrado ?? 3);
    $materias = $rows->pluck('idMateria')->unique()->filter()->map(fn ($v) => (int) $v)->values()->all();
    $gradoMaterias = $rows->pluck('idGradoMateria')->unique()->filter()->map(fn ($v) => (int) $v)->values()->all();

    if (empty($materias)) {
        $materias = DB::table('materia')->orderBy('id')->limit(4)->pluck('id')->map(fn ($v) => (int) $v)->all();
    }

    // Asegurar que idGrado exista
    if (!DB::table('grado')->where('id', $idGrado)->exists()) {
        $idGrado = (int) (DB::table('grado')->orderBy('id')->value('id') ?: 1);
    }

    return compact('idGrado', 'materias', 'gradoMaterias');
}

DB::beginTransaction();
try {
    $created = 0;
    foreach ($fichas as $ficha) {
        $idFicha = (int) $ficha->id;
        $meta = resolverGradoYMaterias($idFicha);
        $idGrado = $meta['idGrado'];
        $materias = $meta['materias'];

        $ya = DB::table('matricula')->where('idFicha', $idFicha)->where('estado', 'EN FORMACION')->count();
        echo "Ficha {$idFicha} ({$ficha->codigo}): grado={$idGrado}, materias=" . count($materias) . ", actuales={$ya}\n";

        foreach ($nombres as $i => $nom) {
            $doc = sprintf('9%02d%08d', $idFicha, $i + 1);
            $email = strtolower($nom[0]) . ".ficha{$idFicha}.{$i}@colegio.edu.co";

            $personaId = DB::table('persona')->where('identificacion', $doc)->value('id');
            if (!$personaId) {
                $personaId = DB::table('persona')->insertGetId([
                    'identificacion' => $doc,
                    'nombre1' => $nom[0],
                    'nombre2' => $nom[1],
                    'apellido1' => $nom[2],
                    'apellido2' => $nom[3],
                    'fechaNac' => '2014-0' . (($i % 9) + 1) . '-15',
                    'direccion' => 'Calle 10 # ' . ($i + 1) . '-20',
                    'email' => $email,
                    'perfil' => 'ESTUDIANTE',
                    'sexo' => $i % 2 === 0 ? 'F' : 'M',
                    'idTipoIdentificacion' => 7,
                    'celular' => '300' . str_pad((string) ($idFicha * 100 + $i), 7, '0', STR_PAD_LEFT),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $matId = DB::table('matricula')
                ->where('idFicha', $idFicha)
                ->where('idPersona', $personaId)
                ->value('id');

            if (!$matId) {
                $matId = DB::table('matricula')->insertGetId([
                    'fecha' => now()->toDateString(),
                    'idFicha' => $idFicha,
                    'idPersona' => $personaId,
                    'idCompany' => $idCompany,
                    'idGrado' => $idGrado,
                    'estado' => 'EN FORMACION',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $created++;
            } else {
                DB::table('matricula')->where('id', $matId)->update([
                    'estado' => 'EN FORMACION',
                    'idGrado' => $idGrado,
                    'idCompany' => $idCompany,
                ]);
            }

            foreach ($materias as $idMateria) {
                $exists = DB::table('matriculaAcademica')
                    ->where('idFicha', $idFicha)
                    ->where('idMatricula', $matId)
                    ->where('idMateria', $idMateria)
                    ->exists();
                if ($exists) {
                    continue;
                }
                DB::table('matriculaAcademica')->insert([
                    'idFicha' => $idFicha,
                    'idMatricula' => $matId,
                    'idMateria' => $idMateria,
                    'estado' => 'POR EVALUAR',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        $count = DB::table('matricula')->where('idFicha', $idFicha)->where('estado', 'EN FORMACION')->count();
        $maCount = DB::table('matriculaAcademica')->where('idFicha', $idFicha)->count();
        echo "  → matrículas EN FORMACION={$count}, matriculaAcademica={$maCount}\n";
    }

    DB::commit();
    echo "Listo. Nuevas matrículas creadas/actualizadas (aprox inserts): {$created}\n";
    echo "Cierra el modal Asignar, vuelve a abrir y deberías ver los estudiantes.\n";
} catch (Throwable $e) {
    DB::rollBack();
    echo 'ERROR: ' . $e->getMessage() . "\n";
    exit(1);
}
