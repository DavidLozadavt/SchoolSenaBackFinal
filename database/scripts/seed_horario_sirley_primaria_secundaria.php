<?php
/**
 * Horario real (primaria + secundaria) para la docente Carol Viviana Legarda Córdoba
 * (usuario diazsirley234@gmail.com → persona 1155 → contrato 91).
 *
 * Uso:
 *   php database/scripts/seed_horario_sirley_primaria_secundaria.php
 */

require __DIR__ . '/../../vendor/autoload.php';
$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

const SEED_TAG = 'SEED_HORARIO_SIRLEY_PRI_SEC';
const ID_CONTRATO = 91;
const ID_PERSONA = 1155;
const ID_COMPANY = 1;
const ID_SEDE = 1;          // SEDE ADMINISTRATIVA CCYS
const ID_JORNADA = 1;       // MAÑANA
const ID_PERIODO = 25;
const ID_RED = 3;           // EDUCACION
const ID_TIPO_FORMACION = 1; // PRESENCIAL
const ID_ESTADO_PROGRAMA = 2; // APROBADO
const ID_INFRA_PRI = 1;     // SOFTWARE 1
const ID_INFRA_SEC = 2;     // SOFTWARE 2
const FECHA_INI = '2026-02-02';
const FECHA_FIN = '2026-11-27';

DB::beginTransaction();

try {
    $contrato = DB::table('contrato')
        ->where('id', ID_CONTRATO)
        ->where('idpersona', ID_PERSONA)
        ->where('idEstado', 1)
        ->first();

    if (!$contrato) {
        throw new RuntimeException('No existe contrato activo 91 para persona 1155.');
    }

    $persona = DB::table('persona')->where('id', ID_PERSONA)->first();
    echo "Docente: {$persona->nombre1} {$persona->apellido1} | contrato " . ID_CONTRATO . "\n";

    // --- Limpieza idempotente de seed previo ---
    $fichasPrevias = DB::table('ficha')
        ->whereIn('codigo', ['PRI-SIRLEY-3A', 'SEC-SIRLEY-8B'])
        ->pluck('id');

    if ($fichasPrevias->isNotEmpty()) {
        $hmIds = DB::table('horarioMateria')->whereIn('idFicha', $fichasPrevias)->pluck('id');
        if ($hmIds->isNotEmpty()) {
            if (DB::getSchemaBuilder()->hasTable('sesionMateria')) {
                DB::table('sesionMateria')->whereIn('idHorarioMateria', $hmIds)->delete();
            }
            if (DB::getSchemaBuilder()->hasTable('asignacionSesion')) {
                DB::table('asignacionSesion')->whereIn('idHorarioMateria', $hmIds)->delete();
            }
            DB::table('horarioMateria')->whereIn('id', $hmIds)->delete();
        }
        DB::table('ficha')->whereIn('id', $fichasPrevias)->delete();
    }

    $programasPrevios = DB::table('programa')
        ->whereIn('codigoPrograma', ['EBP-SIRLEY', 'EBS-SIRLEY'])
        ->pluck('id');

    if ($programasPrevios->isNotEmpty()) {
        $apIds = DB::table('aperturarprograma')->whereIn('idPrograma', $programasPrevios)->pluck('id');
        if ($apIds->isNotEmpty()) {
            DB::table('ficha')->whereIn('idAsignacion', $apIds)->delete();
            DB::table('aperturarprograma')->whereIn('id', $apIds)->delete();
        }

        $gpIds = DB::table('gradoPrograma')->whereIn('idPrograma', $programasPrevios)->pluck('id');
        if ($gpIds->isNotEmpty()) {
            $gmIds = DB::table('gradoMateria')->whereIn('idGradoPrograma', $gpIds)->pluck('id');
            if ($gmIds->isNotEmpty()) {
                DB::table('horarioMateria')->whereIn('idGradoMateria', $gmIds)->delete();
                DB::table('gradoMateria')->whereIn('id', $gmIds)->delete();
            }
            DB::table('gradoPrograma')->whereIn('id', $gpIds)->delete();
        }

        DB::table('programa')->whereIn('id', $programasPrevios)->delete();
    }

    $materiasPrevias = DB::table('materia')
        ->where('codigo', 'like', 'SIRLEY-%')
        ->pluck('id');
    if ($materiasPrevias->isNotEmpty()) {
        DB::table('gradoMateria')->whereIn('idMateria', $materiasPrevias)->delete();
        DB::table('materia')->whereIn('id', $materiasPrevias)->delete();
    }

    // Grados anuales: 3° (id 26) y 8° (id 31) — tipoGrado AÑO
    $grado3 = DB::table('grado')->where('id', 26)->where('numeroGrado', 3)->first();
    $grado8 = DB::table('grado')->where('id', 31)->where('numeroGrado', 8)->first();
    if (!$grado3 || !$grado8) {
        throw new RuntimeException('No se encontraron grados 3° (id 26) y 8° (id 31) de tipo AÑO.');
    }

    $now = now();

    // --- Programas ---
    $idProgramaPri = DB::table('programa')->insertGetId([
        'nombrePrograma' => 'EDUCACIÓN BÁSICA PRIMARIA',
        'codigoPrograma' => 'EBP-SIRLEY',
        'descripcionPrograma' => 'Programa escolar de primaria (seed docente Sirley / Carol Legarda)',
        'idNivelEducativo' => 2, // PRIMARIA
        'idTipoFormacion' => ID_TIPO_FORMACION,
        'idEstadoPrograma' => ID_ESTADO_PROGRAMA,
        'idCompany' => ID_COMPANY,
        'idRed' => ID_RED,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $idProgramaSec = DB::table('programa')->insertGetId([
        'nombrePrograma' => 'EDUCACIÓN BÁSICA SECUNDARIA',
        'codigoPrograma' => 'EBS-SIRLEY',
        'descripcionPrograma' => 'Programa escolar de secundaria / bachillerato (seed docente Sirley / Carol Legarda)',
        'idNivelEducativo' => 3, // BACHILLER
        'idTipoFormacion' => ID_TIPO_FORMACION,
        'idEstadoPrograma' => ID_ESTADO_PROGRAMA,
        'idCompany' => ID_COMPANY,
        'idRed' => ID_RED,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    echo "Programas: primaria #{$idProgramaPri}, secundaria #{$idProgramaSec}\n";

    // --- Aperturas ---
    $apBase = [
        'observacion' => SEED_TAG,
        'idPeriodo' => ID_PERIODO,
        'estado' => 'EN CURSO',
        'idSede' => ID_SEDE,
        'pension' => 0,
        'fechaInicialClases' => FECHA_INI,
        'fechaFinalClases' => FECHA_FIN,
        'fechaInicialInscripciones' => '2026-01-15',
        'fechaFinalInscripciones' => '2026-01-31',
        'fechaInicialMatriculas' => '2026-01-15',
        'fechaFinalMatriculas' => '2026-02-01',
        'tipoCalificacion' => 'NUMERICO',
        'created_at' => $now,
        'updated_at' => $now,
    ];

    $idAperturaPri = DB::table('aperturarprograma')->insertGetId(array_merge($apBase, [
        'idPrograma' => $idProgramaPri,
    ]));
    $idAperturaSec = DB::table('aperturarprograma')->insertGetId(array_merge($apBase, [
        'idPrograma' => $idProgramaSec,
    ]));

    // --- Fichas / grupos ---
    $idFichaPri = DB::table('ficha')->insertGetId([
        'codigo' => 'PRI-SIRLEY-3A',
        'idJornada' => ID_JORNADA,
        'idAsignacion' => $idAperturaPri,
        'idSede' => ID_SEDE,
        'idInstructorLider' => ID_CONTRATO,
        'idInfraestructura' => ID_INFRA_PRI,
        'porcentajeEjecucion' => 100,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $idFichaSec = DB::table('ficha')->insertGetId([
        'codigo' => 'SEC-SIRLEY-8B',
        'idJornada' => ID_JORNADA,
        'idAsignacion' => $idAperturaSec,
        'idSede' => ID_SEDE,
        'idInstructorLider' => ID_CONTRATO,
        'idInfraestructura' => ID_INFRA_SEC,
        'porcentajeEjecucion' => 100,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    echo "Fichas: {$idFichaPri} PRI-SIRLEY-3A, {$idFichaSec} SEC-SIRLEY-8B\n";

    // --- Grado-programa ---
    $idGpPri = DB::table('gradoPrograma')->insertGetId([
        'idPrograma' => $idProgramaPri,
        'idGrado' => 26, // 3°
        'cupos' => 35,
        'fechaInicio' => FECHA_INI,
        'fechaFin' => FECHA_FIN,
        'estado' => 'EN CURSO',
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $idGpSec = DB::table('gradoPrograma')->insertGetId([
        'idPrograma' => $idProgramaSec,
        'idGrado' => 31, // 8°
        'cupos' => 40,
        'fechaInicio' => FECHA_INI,
        'fechaFin' => FECHA_FIN,
        'estado' => 'EN CURSO',
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    // Unique compuesto materia: (nombreMateria, idEmpresa) → nombres distintos por nivel.
    // Si ya existe (seed previo / datos reales), reutilizar y actualizar código/padre.
    $insertMateria = function (string $codigo, string $nombre, ?int $padre = null) use ($now): int {
        $existente = DB::table('materia')
            ->where('nombreMateria', $nombre)
            ->where('idEmpresa', ID_COMPANY)
            ->first();

        if ($existente) {
            DB::table('materia')->where('id', $existente->id)->update([
                'codigo' => $codigo,
                'descripcion' => SEED_TAG,
                'idMateriaPadre' => $padre,
                'updated_at' => $now,
            ]);
            return (int) $existente->id;
        }

        return DB::table('materia')->insertGetId([
            'nombreMateria' => $nombre,
            'descripcion' => SEED_TAG,
            'codigo' => $codigo,
            'idCompany' => 0,
            'idEmpresa' => ID_COMPANY,
            'idMateriaPadre' => $padre,
            'horas' => 4,
            'creditos' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    };

    // Primaria 3° — áreas + temas
    $matPriAreas = [
        ['SIRLEY-P-MAT', 'Matemáticas 3°', [
            ['SIRLEY-P-MAT-1', 'Números hasta 10.000 y operaciones básicas'],
            ['SIRLEY-P-MAT-2', 'Geometría: figuras planas y perímetro'],
        ]],
        ['SIRLEY-P-LEN', 'Lengua Castellana 3°', [
            ['SIRLEY-P-LEN-1', 'Comprensión lectora de textos narrativos'],
            ['SIRLEY-P-LEN-2', 'Producción de textos descriptivos'],
        ]],
        ['SIRLEY-P-CN', 'Ciencias Naturales 3°', [
            ['SIRLEY-P-CN-1', 'Seres vivos y ecosistemas locales'],
        ]],
        ['SIRLEY-P-CS', 'Ciencias Sociales 3°', [
            ['SIRLEY-P-CS-1', 'Mi municipio: historia y geografía'],
        ]],
    ];

    // Secundaria 8°
    $matSecAreas = [
        ['SIRLEY-S-MAT', 'Matemáticas 8°', [
            ['SIRLEY-S-MAT-1', 'Álgebra: ecuaciones lineales'],
            ['SIRLEY-S-MAT-2', 'Estadística descriptiva'],
        ]],
        ['SIRLEY-S-LEN', 'Lengua Castellana 8°', [
            ['SIRLEY-S-LEN-1', 'Géneros literarios y análisis textual'],
        ]],
        ['SIRLEY-S-CN', 'Ciencias Naturales 8°', [
            ['SIRLEY-S-CN-1', 'Biología: célula y sistemas del cuerpo'],
        ]],
        ['SIRLEY-S-CS', 'Ciencias Sociales 8°', [
            ['SIRLEY-S-CS-1', 'Historia de Colombia siglo XX'],
        ]],
        ['SIRLEY-S-ING', 'Inglés 8°', [
            ['SIRLEY-S-ING-1', 'Present simple and continuous'],
        ]],
    ];

    $gradoMateriasPri = [];
    foreach ($matPriAreas as [$codArea, $nomArea, $hijos]) {
        $idArea = $insertMateria($codArea, $nomArea, null);
        foreach ($hijos as [$cod, $nom]) {
            $idHijo = $insertMateria($cod, $nom, $idArea);
            $idGm = DB::table('gradoMateria')->insertGetId([
                'idGradoPrograma' => $idGpPri,
                'idMateria' => $idHijo,
                'estado' => 'PENDIENTE',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $gradoMateriasPri[$cod] = ['gm' => $idGm, 'nombre' => $nom, 'area' => $nomArea];
        }
    }

    $gradoMateriasSec = [];
    foreach ($matSecAreas as [$codArea, $nomArea, $hijos]) {
        $idArea = $insertMateria($codArea, $nomArea, null);
        foreach ($hijos as [$cod, $nom]) {
            $idHijo = $insertMateria($cod, $nom, $idArea);
            $idGm = DB::table('gradoMateria')->insertGetId([
                'idGradoPrograma' => $idGpSec,
                'idMateria' => $idHijo,
                'estado' => 'PENDIENTE',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $gradoMateriasSec[$cod] = ['gm' => $idGm, 'nombre' => $nom, 'area' => $nomArea];
        }
    }

    // --- Horario semanal (idDia: 1 Lun … 5 Vie) ---
    $slots = [
        // Primaria 3A
        ['PRI', 'SIRLEY-P-MAT-1', 1, '07:00:00', '09:00:00', ID_INFRA_PRI, $idFichaPri],
        ['PRI', 'SIRLEY-P-LEN-1', 2, '07:00:00', '09:00:00', ID_INFRA_PRI, $idFichaPri],
        ['PRI', 'SIRLEY-P-CN-1',  3, '09:00:00', '11:00:00', ID_INFRA_PRI, $idFichaPri],
        ['PRI', 'SIRLEY-P-CS-1',  4, '07:00:00', '09:00:00', ID_INFRA_PRI, $idFichaPri],
        ['PRI', 'SIRLEY-P-MAT-2', 5, '07:00:00', '09:00:00', ID_INFRA_PRI, $idFichaPri],
        // Secundaria 8B
        ['SEC', 'SIRLEY-S-MAT-1', 1, '10:00:00', '12:00:00', ID_INFRA_SEC, $idFichaSec],
        ['SEC', 'SIRLEY-S-LEN-1', 2, '10:00:00', '12:00:00', ID_INFRA_SEC, $idFichaSec],
        ['SEC', 'SIRLEY-S-CN-1',  3, '07:00:00', '09:00:00', ID_INFRA_SEC, $idFichaSec],
        ['SEC', 'SIRLEY-S-CS-1',  4, '10:00:00', '12:00:00', ID_INFRA_SEC, $idFichaSec],
        ['SEC', 'SIRLEY-S-ING-1', 5, '10:00:00', '12:00:00', ID_INFRA_SEC, $idFichaSec],
    ];

    $dias = [1 => 'LUNES', 2 => 'MARTES', 3 => 'MIÉRCOLES', 4 => 'JUEVES', 5 => 'VIERNES'];
    $creados = 0;

    foreach ($slots as [$nivel, $codMat, $idDia, $hi, $hf, $infra, $idFicha]) {
        $map = $nivel === 'PRI' ? $gradoMateriasPri : $gradoMateriasSec;
        if (!isset($map[$codMat])) {
            throw new RuntimeException("Materia {$codMat} no creada");
        }
        $gm = $map[$codMat]['gm'];
        $idHm = DB::table('horarioMateria')->insertGetId([
            'horaInicial' => $hi,
            'horaFinal' => $hf,
            'estado' => 'ASIGNADO',
            'idGradoMateria' => $gm,
            'idDia' => $idDia,
            'idInfraestructura' => $infra,
            'idFicha' => $idFicha,
            'fechaInicial' => FECHA_INI,
            'fechaFinal' => FECHA_FIN,
            'observacion' => SEED_TAG,
            'idContrato' => ID_CONTRATO,
            'festivos' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $creados++;
        echo sprintf(
            "  HM#%d %s %s %s-%s | %s > %s\n",
            $idHm,
            $dias[$idDia],
            substr($hi, 0, 5),
            substr($hf, 0, 5),
            $nivel,
            $map[$codMat]['area'],
            $map[$codMat]['nombre']
        );
    }

    DB::commit();

    $total = DB::table('horarioMateria')->where('idContrato', ID_CONTRATO)->whereNotNull('idDia')->count();
    echo "\nOK: {$creados} franjas creadas. Total horarioMateria contrato 91 con día: {$total}\n";
    echo "Recarga /ambiente-virtual/horario con la cuenta diazsirley234@gmail.com\n";
} catch (Throwable $e) {
    DB::rollBack();
    fwrite(STDERR, 'ERROR: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
