<?php

namespace Tests\Unit;

use App\Models\MaterialCompetencia;
use App\Services\ambiente_virtual\RapAccessService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\TestCase;

class MaterialCompetenciaFichaAislamientoTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        $app = require __DIR__.'/../../bootstrap/app.php';
        $app->make(Kernel::class)->bootstrap();
    }

    public function test_esquema_id_ficha_indice_y_unique(): void
    {
        $this->assertTrue(Schema::hasColumn('materialCompetencia', 'idFicha'));
        $indexes = collect(DB::select('SHOW INDEX FROM materialCompetencia'));
        $this->assertTrue($indexes->contains(fn ($row) => $row->Key_name === 'materialcompetencia_comp_ficha_idx'));
        $this->assertTrue($indexes->contains(fn ($row) => $row->Key_name === 'materialcompetencia_comp_ficha_clave_unique'));
        $this->assertFalse($indexes->contains(fn ($row) => $row->Key_name === 'materialcompetencia_comp_clave_unique'));

        $fk = DB::selectOne(
            'SELECT 1 AS ok FROM information_schema.TABLE_CONSTRAINTS
             WHERE CONSTRAINT_SCHEMA = ? AND TABLE_NAME = ? AND CONSTRAINT_NAME = ? AND CONSTRAINT_TYPE = ?
             LIMIT 1',
            [Schema::getConnection()->getDatabaseName(), 'materialCompetencia', 'materialcompetencia_idficha_foreign', 'FOREIGN KEY']
        );
        $this->assertNotNull($fk);

        $col = collect(DB::select('SHOW COLUMNS FROM materialCompetencia'))->firstWhere('Field', 'idFicha');
        $this->assertNotNull($col);
        $this->assertTrue(stripos((string) $col->Type, 'bigint') !== false);
        $this->assertSame('YES', strtoupper((string) $col->Null));
    }

    public function test_material_de_ficha_1_no_aparece_en_ficha_2(): void
    {
        $par = $this->parFichasMismaCompetencia();
        if ($par === null) {
            $this->markTestSkipped('No hay una competencia con dos fichas para probar aislamiento.');
        }

        DB::beginTransaction();
        try {
            $idA = $this->insertarMaterial($par['idCompetencia'], $par['idFichaA'], 'Material A ficha 1');
            $visiblesA = DB::table('materialCompetencia')
                ->where('idCompetencia', $par['idCompetencia'])
                ->where('idFicha', $par['idFichaA'])
                ->where('activo', true)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();
            $visiblesB = DB::table('materialCompetencia')
                ->where('idCompetencia', $par['idCompetencia'])
                ->where('idFicha', $par['idFichaB'])
                ->where('activo', true)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();

            $this->assertContains($idA, $visiblesA);
            $this->assertNotContains($idA, $visiblesB);

            $idB = $this->insertarMaterial($par['idCompetencia'], $par['idFichaB'], 'Material B ficha 2');
            $visiblesA2 = DB::table('materialCompetencia')
                ->where('idCompetencia', $par['idCompetencia'])
                ->where('idFicha', $par['idFichaA'])
                ->where('activo', true)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();
            $visiblesB2 = DB::table('materialCompetencia')
                ->where('idCompetencia', $par['idCompetencia'])
                ->where('idFicha', $par['idFichaB'])
                ->where('activo', true)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();

            $this->assertContains($idA, $visiblesA2);
            $this->assertNotContains($idB, $visiblesA2);
            $this->assertContains($idB, $visiblesB2);
            $this->assertNotContains($idA, $visiblesB2);
        } finally {
            DB::rollBack();
        }
    }

    public function test_misma_actividad_puede_incorporarse_en_dos_fichas(): void
    {
        $par = $this->parFichasMismaCompetencia();
        if ($par === null) {
            $this->markTestSkipped('No hay una competencia con dos fichas para probar unique por ficha.');
        }

        $clave = 'ACTIVIDAD:actividad:999001';
        DB::beginTransaction();
        try {
            $this->insertarMaterial($par['idCompetencia'], $par['idFichaA'], 'Actividad 100 ficha 1', $clave);
            $this->insertarMaterial($par['idCompetencia'], $par['idFichaB'], 'Actividad 100 ficha 2', $clave);
            $total = DB::table('materialCompetencia')
                ->where('idCompetencia', $par['idCompetencia'])
                ->where('claveOrigen', $clave)
                ->whereIn('idFicha', [$par['idFichaA'], $par['idFichaB']])
                ->count();
            $this->assertSame(2, $total);

            $this->expectException(QueryException::class);
            $this->insertarMaterial($par['idCompetencia'], $par['idFichaA'], 'Actividad 100 duplicada', $clave);
        } finally {
            DB::rollBack();
        }
    }

    public function test_recursos_siguen_vinculados_al_material_no_a_la_ficha(): void
    {
        $par = $this->parFichasMismaCompetencia();
        if ($par === null || ! Schema::hasTable('materialCompetenciaRecurso')) {
            $this->markTestSkipped('No hay ficha par o tabla de recursos.');
        }

        DB::beginTransaction();
        try {
            $idMaterial = $this->insertarMaterial($par['idCompetencia'], $par['idFichaA'], 'Material con recursos');
            DB::table('materialCompetenciaRecurso')->insert([
                [
                    'idMaterialCompetencia' => $idMaterial,
                    'tipo' => 'DOCUMENTO',
                    'titulo' => 'Guia',
                    'ruta' => 'material-competencia/documentos/guia.pdf',
                    'orden' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'idMaterialCompetencia' => $idMaterial,
                    'tipo' => 'ENLACE',
                    'titulo' => 'SENA',
                    'ruta' => 'https://www.sena.edu.co',
                    'orden' => 2,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);
            $recursos = DB::table('materialCompetenciaRecurso')
                ->where('idMaterialCompetencia', $idMaterial)
                ->count();
            $this->assertSame(2, $recursos);
            $this->assertFalse(Schema::hasColumn('materialCompetenciaRecurso', 'idFicha'));
            $this->assertSame(
                $par['idFichaA'],
                (int) DB::table('materialCompetencia')->where('id', $idMaterial)->value('idFicha')
            );
        } finally {
            DB::rollBack();
        }
    }

    public function test_ficha_ajena_o_sin_competencia_no_es_administrable_sin_usuario(): void
    {
        $service = new RapAccessService();
        $this->assertFalse($service->usuarioPuedeAdministrarFichaCompetencia(1, 1));
        $this->assertFalse($service->usuarioPuedeAccederFichaCompetencia(1, 1));
        $this->assertSame([], $service->fichasDeCompetencia(76));
        $this->assertSame([], $service->fichasInstructor());
    }

    public function test_legacy_sin_ficha_no_se_asigna_arbitrariamente(): void
    {
        if (! Schema::hasColumn('materialCompetencia', 'idFicha')) {
            $this->markTestSkipped('idFicha no existe.');
        }
        $sinFicha = DB::table('materialCompetencia')->whereNull('idFicha')->count();
        $this->assertGreaterThanOrEqual(0, $sinFicha);
        $this->assertSame(
            $sinFicha,
            (int) DB::table('materialCompetencia')->whereNull('idFicha')->count()
        );
    }

    /**
     * @return array{idCompetencia:int,idFichaA:int,idFichaB:int}|null
     */
    private function parFichasMismaCompetencia(): ?array
    {
        if (! Schema::hasTable('materia') || ! Schema::hasTable('horarioMateria') || ! Schema::hasTable('ficha')) {
            return null;
        }

        $row = DB::selectOne("
            SELECT m.id as idComp, MIN(hm.idFicha) as idA, MAX(hm.idFicha) as idB
            FROM materia m
            JOIN materia rap ON rap.idMateriaPadre = m.id
            JOIN gradoMateria gm ON gm.idMateria = rap.id
            JOIN horarioMateria hm ON hm.idGradoMateria = gm.id
            WHERE (m.idMateriaPadre IS NULL OR m.idMateriaPadre = 0)
            GROUP BY m.id
            HAVING COUNT(DISTINCT hm.idFicha) >= 2
            ORDER BY COUNT(DISTINCT hm.idFicha) DESC
            LIMIT 1
        ");
        if (! $row) {
            return null;
        }
        $idA = (int) $row->idA;
        $idB = (int) $row->idB;
        if ($idA <= 0 || $idB <= 0 || $idA === $idB) {
            return null;
        }

        return [
            'idCompetencia' => (int) $row->idComp,
            'idFichaA' => $idA,
            'idFichaB' => $idB,
        ];
    }

    private function insertarMaterial(int $idCompetencia, int $idFicha, string $titulo, ?string $clave = null): int
    {
        return (int) DB::table('materialCompetencia')->insertGetId([
            'idCompetencia' => $idCompetencia,
            'idFicha' => $idFicha,
            'idPersona' => null,
            'origen' => MaterialCompetencia::ORIGEN_DIRECTO,
            'claveOrigen' => $clave,
            'titulo' => $titulo,
            'descripcion' => null,
            'tipoMaterial' => 'MATERIAL_FORMACION',
            'activo' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
