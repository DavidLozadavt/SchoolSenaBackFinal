<?php

namespace Tests\Unit;

use App\Models\MaterialCompetencia;
use App\Support\MaterialCompetenciaReglas;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\TestCase;

class MaterialCompetenciaConsolidacionTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        $app = require __DIR__.'/../../bootstrap/app.php';
        $app->make(Kernel::class)->bootstrap();
    }

    public function test_existe_solo_la_tabla_material_competencia(): void
    {
        $this->assertTrue(Schema::hasTable('materialCompetencia'));
        $this->assertFalse(Schema::hasTable('materialRap'));
        $this->assertFalse(class_exists(\App\Models\MaterialRap::class));
        $this->assertSame('materialCompetencia', (new MaterialCompetencia())->getTable());
        $this->assertTrue(Schema::hasColumn('materialCompetencia', 'idCompetencia'));
        $this->assertTrue(Schema::hasColumn('materialCompetencia', 'claveOrigen'));
        $this->assertFalse(Schema::hasColumn('materialCompetencia', 'idRap'));
        $this->assertTrue(
            DB::table('migrations')->where('migration', '2026_10_22_120000_create_material_competencia_table')->exists()
        );
    }

    public function test_registros_apuntan_a_competencias_reales(): void
    {
        if (! Schema::hasTable('materialCompetencia') || ! Schema::hasTable('materia')) {
            $this->markTestSkipped('Tablas no disponibles.');
        }

        $ids = DB::table('materialCompetencia')->pluck('idCompetencia')->map(fn ($id) => (int) $id)->all();
        $this->assertIsArray($ids);
        foreach ($ids as $idCompetencia) {
            $this->assertGreaterThan(0, $idCompetencia);
            $padre = DB::table('materia')->where('id', $idCompetencia)->value('idMateriaPadre');
            $this->assertTrue(
                $padre === null || (int) $padre <= 0,
                'idCompetencia='.$idCompetencia.' no es una competencia.'
            );
        }
    }

    public function test_unicidad_de_incorporacion_es_por_competencia(): void
    {
        $clave = MaterialCompetenciaReglas::claveIncorporacion(
            MaterialCompetenciaReglas::ORIGEN_ACTIVIDAD,
            36,
            null
        );
        $this->assertSame('ACTIVIDAD:actividad:36', $clave);

        if (! Schema::hasTable('materialCompetencia')) {
            return;
        }

        $duplicados = DB::table('materialCompetencia')
            ->select('idCompetencia', 'claveOrigen', DB::raw('COUNT(*) as total'))
            ->whereNotNull('claveOrigen')
            ->groupBy('idCompetencia', 'claveOrigen')
            ->having('total', '>', 1)
            ->count();

        $this->assertSame(0, $duplicados);
    }
}
