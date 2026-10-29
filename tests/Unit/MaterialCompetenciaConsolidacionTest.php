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
        $this->assertTrue(Schema::hasColumn('materialCompetencia', 'urlDocumento'));
        $this->assertTrue(Schema::hasColumn('materialCompetencia', 'urlAdicional'));
        $this->assertTrue(Schema::hasColumn('materialCompetencia', 'urlVideo'));
        $this->assertFalse(Schema::hasColumn('materialCompetencia', 'idRap'));
        $this->assertTrue(
            DB::table('migrations')->whereIn('migration', [
                '2026_10_22_120000_create_material_competencia_table',
                '2026_10_22_120000_transform_material_rap_to_material_competencia',
            ])->exists()
        );
        $this->assertTrue(Schema::hasTable('materialCompetenciaRecurso'));
        $this->assertTrue(Schema::hasColumn('materialCompetenciaRecurso', 'idMaterialCompetencia'));
        $this->assertTrue(Schema::hasColumn('materialCompetenciaRecurso', 'tipo'));
        $this->assertTrue(Schema::hasColumn('materialCompetenciaRecurso', 'ruta'));
        $this->assertTrue(
            DB::table('migrations')->where('migration', '2026_10_27_233358_add_url_video_and_recursos_to_material_competencia')->exists()
        );
        $this->assertFalse(
            DB::table('migrations')->whereIn('migration', [
                '2026_10_27_233358_add_url_video_to_material_competencia_table',
                '2026_10_27_235303_create_material_competencia_recurso_table',
                '2026_10_28_002842_sync_legacy_fields_into_material_competencia_recurso_table',
            ])->exists()
        );
    }

    public function test_recursos_copiados_no_duplican_el_material(): void
    {
        if (! Schema::hasTable('materialCompetencia') || ! Schema::hasTable('materialCompetenciaRecurso')) {
            $this->markTestSkipped('Tablas no disponibles.');
        }
        $materiales = DB::table('materialCompetencia')->count();
        $huerfanos = DB::table('materialCompetenciaRecurso as r')
            ->leftJoin('materialCompetencia as m', 'm.id', '=', 'r.idMaterialCompetencia')
            ->whereNull('m.id')
            ->count();
        $this->assertSame(0, $huerfanos);
        $this->assertGreaterThan(0, $materiales);
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
