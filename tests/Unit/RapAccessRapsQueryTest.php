<?php

namespace Tests\Unit;

use App\Models\Materia;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\TestCase;

class RapAccessRapsQueryTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        $app = require __DIR__.'/../../bootstrap/app.php';
        $app->make(Kernel::class)->bootstrap();
    }

    public function test_listado_de_raps_no_usa_columnas_ambiguas_tras_join(): void
    {
        if (! Schema::hasTable('materia')) {
            $this->markTestSkipped('Tabla materia no disponible.');
        }

        $ids = DB::table('materia')
            ->whereNotNull('idMateriaPadre')
            ->where('idMateriaPadre', '>', 0)
            ->limit(5)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($ids === []) {
            $this->markTestSkipped('No hay RAP en materia.');
        }

        $rows = Materia::query()
            ->from('materia')
            ->whereIn('materia.id', $ids)
            ->whereNotNull('materia.idMateriaPadre')
            ->where('materia.idMateriaPadre', '>', 0)
            ->leftJoin('materia as comp', 'materia.idMateriaPadre', '=', 'comp.id')
            ->orderBy('materia.nombreMateria')
            ->get([
                'materia.id',
                'materia.nombreMateria',
                'materia.codigo',
                'materia.idMateriaPadre',
                'comp.nombreMateria as competenciaNombre',
                'comp.codigo as competenciaCodigo',
            ]);

        $this->assertGreaterThan(0, $rows->count());
        $this->assertTrue($rows->every(fn ($r) => (int) $r->id > 0));
    }

    public function test_listado_de_competencias_no_usa_columnas_ambiguas(): void
    {
        if (! Schema::hasTable('materia')) {
            $this->markTestSkipped('Tabla materia no disponible.');
        }

        $ids = DB::table('materia')
            ->where(function ($q) {
                $q->whereNull('idMateriaPadre')->orWhere('idMateriaPadre', '<=', 0);
            })
            ->limit(5)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($ids === []) {
            $this->markTestSkipped('No hay competencias en materia.');
        }

        $rows = Materia::query()
            ->from('materia')
            ->whereIn('materia.id', $ids)
            ->where(function ($q) {
                $q->whereNull('materia.idMateriaPadre')->orWhere('materia.idMateriaPadre', '<=', 0);
            })
            ->orderBy('materia.nombreMateria')
            ->get([
                'materia.id',
                'materia.nombreMateria',
                'materia.codigo',
                'materia.idMateriaPadre',
            ]);

        $this->assertGreaterThan(0, $rows->count());
        $this->assertTrue($rows->every(fn ($r) => (int) $r->idMateriaPadre <= 0));
    }

    public function test_raps_de_una_competencia_comparten_el_mismo_padre(): void
    {
        if (! Schema::hasTable('materia')) {
            $this->markTestSkipped('Tabla materia no disponible.');
        }

        $idCompetencia = (int) DB::table('materia as c')
            ->join('materia as r', 'r.idMateriaPadre', '=', 'c.id')
            ->where(function ($q) {
                $q->whereNull('c.idMateriaPadre')->orWhere('c.idMateriaPadre', '<=', 0);
            })
            ->value('c.id');

        if ($idCompetencia <= 0) {
            $this->markTestSkipped('No hay competencia con RAP.');
        }

        $hijos = DB::table('materia')
            ->where('idMateriaPadre', $idCompetencia)
            ->get(['id', 'idMateriaPadre']);

        $this->assertGreaterThan(0, $hijos->count());
        $this->assertTrue($hijos->every(fn ($r) => (int) $r->idMateriaPadre === $idCompetencia));
    }

    public function test_completar_hermanos_incluye_todos_los_raps_de_la_competencia_alcanzada(): void
    {
        if (! Schema::hasTable('materia')) {
            $this->markTestSkipped('Tabla materia no disponible.');
        }

        $caso = DB::table('materia as r')
            ->join('materia as hermano', function ($join) {
                $join->on('hermano.idMateriaPadre', '=', 'r.idMateriaPadre')
                    ->whereColumn('hermano.id', '!=', 'r.id');
            })
            ->whereNotNull('r.idMateriaPadre')
            ->where('r.idMateriaPadre', '>', 0)
            ->select('r.id as idRap', 'r.idMateriaPadre as idCompetencia')
            ->first();

        if (! $caso) {
            $this->markTestSkipped('No hay competencia con más de un RAP.');
        }

        $idRap = (int) $caso->idRap;
        $idCompetencia = (int) $caso->idCompetencia;
        $service = new \App\Services\ambiente_virtual\RapAccessService();

        $hijos = $service->idsRapDeCompetencia($idCompetencia);
        $this->assertGreaterThan(1, count($hijos));
        $this->assertContains($idRap, $hijos);

        $completos = $service->completarRapsDeCompetenciasAlcanzadas([$idRap]);
        foreach ($hijos as $idHijo) {
            $this->assertContains($idHijo, $completos);
        }

        $ajeno = (int) (DB::table('materia')
            ->whereNotNull('idMateriaPadre')
            ->where('idMateriaPadre', '>', 0)
            ->where('idMateriaPadre', '!=', $idCompetencia)
            ->value('id') ?? 0);
        if ($ajeno > 0) {
            $this->assertNotContains($ajeno, $completos);
        }
    }

    public function test_expandir_horario_de_competencia_incluye_hijos_sin_completar_ajenos(): void
    {
        if (! Schema::hasTable('materia')) {
            $this->markTestSkipped('Tabla materia no disponible.');
        }

        $idCompetencia = (int) DB::table('materia as c')
            ->join('materia as r', 'r.idMateriaPadre', '=', 'c.id')
            ->where(function ($q) {
                $q->whereNull('c.idMateriaPadre')->orWhere('c.idMateriaPadre', '<=', 0);
            })
            ->value('c.id');

        if ($idCompetencia <= 0) {
            $this->markTestSkipped('No hay competencia con RAP.');
        }

        $service = new \App\Services\ambiente_virtual\RapAccessService();
        $hijos = $service->idsRapDeCompetencia($idCompetencia);
        $this->assertNotSame([], $hijos);

        $filas = collect([(object) ['id' => $idCompetencia, 'idMateriaPadre' => null]]);
        $ids = $service->idsRapDesdeFilasHorario($filas, false);
        foreach ($hijos as $idHijo) {
            $this->assertContains($idHijo, $ids);
        }
        $this->assertNotContains($idCompetencia, $ids);
    }
}
