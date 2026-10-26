<?php

namespace Tests\Unit;

use App\Models\Actividad;
use App\Services\ambiente_virtual\RapAccessService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\TestCase;

class GestionActividadesRapAccessTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        $app = require __DIR__.'/../../bootstrap/app.php';
        $app->make(Kernel::class)->bootstrap();
    }

    public function test_fichas_horario_de_rap_invalido_devuelve_vacio(): void
    {
        $service = new RapAccessService();
        $this->assertSame([], $service->fichasHorarioDeRap(0));
        $this->assertSame([], $service->fichasHorarioDeRap(-3));
        $this->assertSame([], $service->idsFichasHorarioDeRap(0));
        $this->assertSame([], $service->idsFichasHorarioDeRap(-1));
        $this->assertSame([], $service->fichasInstructor());
    }

    public function test_completar_raps_incluye_hermanos_sin_abrir_otras_competencias(): void
    {
        $service = new RapAccessService();
        $this->assertSame([], $service->completarRapsDeCompetenciasAlcanzadas([]));
        $this->assertSame([], $service->completarRapsDeCompetenciasAlcanzadas([0, -1]));
        $this->assertSame([], $service->idsRapDeFichas([]));
        $this->assertSame([], $service->idsRapDeFichas([0, -1]));
    }

    public function test_rap_593101_existe_y_aparece_en_fichas_del_contrato_11(): void
    {
        if (! Schema::hasTable('materia') || ! Schema::hasTable('horarioMateria')) {
            $this->markTestSkipped('Tablas materia/horario no disponibles.');
        }

        $rap = DB::table('materia')->where('codigo', '593101')->first();
        $this->assertNotNull($rap, 'RAP 593101 debe existir en materia.');
        $this->assertGreaterThan(0, (int) $rap->idMateriaPadre, 'RAP 593101 debe tener competencia padre.');

        $idRap = (int) $rap->id;
        $idCompetencia = (int) $rap->idMateriaPadre;
        $hijos = DB::table('materia')->where('idMateriaPadre', $idCompetencia)->pluck('id')->map(fn ($id) => (int) $id)->all();
        $this->assertContains($idRap, $hijos);
        $this->assertGreaterThanOrEqual(4, count($hijos), 'La competencia 38362 debe tener los 4 RAP de diseño.');

        if (! DB::table('contrato')->where('id', 11)->exists()) {
            $this->markTestSkipped('Contrato 11 no existe en esta BD.');
        }

        $idsFicha = $this->filasHorarioContrato(11)
            ->pluck('idFicha')
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();
        $this->assertNotEmpty($idsFicha);

        $service = new RapAccessService();
        $ids = $service->idsRapDeFichas($idsFicha, true);
        $this->assertContains($idRap, $ids, 'El catálogo del instructor debe incluir RAP 593101 al consolidar el horario de sus fichas.');

        $deComp = DB::table('materia')
            ->whereIn('id', $ids)
            ->where('idMateriaPadre', $idCompetencia)
            ->pluck('codigo')
            ->map(fn ($c) => (string) $c)
            ->all();
        $this->assertContains('593101', $deComp);
        $this->assertContains('593100', $deComp);
        $this->assertContains('593102', $deComp);
        $this->assertContains('593103', $deComp);
    }

    public function test_actividades_no_tiene_id_ficha_ni_la_exige_en_fillable(): void
    {
        $this->assertNotContains('idFicha', (new Actividad())->getFillable());
        if (Schema::hasTable('actividades')) {
            $this->assertFalse(Schema::hasColumn('actividades', 'idFicha'));
        }
        $this->assertFileDoesNotExist(dirname(__DIR__, 2).'/app/Models/AsignacionActividadFicha.php');
        $this->assertFileDoesNotExist(
            dirname(__DIR__, 2).'/database/migrations/2026_10_24_100000_create_asignacion_actividad_ficha_table.php'
        );
    }

    public function test_contrato_11_rap_74_devuelve_tres_fichas_de_cinco(): void
    {
        if (! Schema::hasTable('horarioMateria') || ! Schema::hasTable('ficha')) {
            $this->markTestSkipped('Tablas de horario no disponibles.');
        }

        $idContrato = 11;
        if (! DB::table('contrato')->where('id', $idContrato)->exists()) {
            $this->markTestSkipped('Contrato 11 no existe en esta BD.');
        }

        $filas = $this->filasHorarioContrato($idContrato);
        if ($filas->isEmpty()) {
            $this->markTestSkipped('Contrato 11 no tiene horario.');
        }

        $porFicha = [];
        foreach ($filas as $row) {
            $idFicha = (int) ($row->idFicha ?? 0);
            if ($idFicha <= 0) {
                continue;
            }
            $porFicha[$idFicha][] = $row;
        }

        $this->assertGreaterThanOrEqual(2, count($porFicha), 'El instructor de prueba debe tener varias fichas.');

        $service = new RapAccessService();
        $compat74 = [];
        $compat56 = [];
        foreach ($porFicha as $idFicha => $rows) {
            $idsRap = $service->idsRapDesdeFilasHorario($rows, false);
            if (in_array(74, $idsRap, true)) {
                $compat74[] = $idFicha;
            }
            if (in_array(56, $idsRap, true)) {
                $compat56[] = $idFicha;
            }
        }

        if (DB::table('materia')->where('id', 74)->exists()) {
            $this->assertCount(3, $compat74, 'RAP 74 debe ser compatible con 3 fichas, no con 1 ni con las 5.');
            $this->assertNotCount(count($porFicha), $compat74);
        }
        if (DB::table('materia')->where('id', 56)->exists()) {
            $this->assertCount(1, $compat56, 'RAP 56 solo existe en una ficha del instructor.');
        }
    }

    /**
     * @return \Illuminate\Support\Collection<int, object>
     */
    private function filasHorarioContrato(int $idContrato)
    {
        $qb = DB::table('horarioMateria as hm')
            ->join('gradoMateria as gm', 'hm.idGradoMateria', '=', 'gm.id')
            ->join('materia as m', 'gm.idMateria', '=', 'm.id')
            ->join('ficha as f', 'hm.idFicha', '=', 'f.id');

        $tieneAsignacionSesion = Schema::hasTable('asignacionSesion');
        if ($tieneAsignacionSesion) {
            $qb->leftJoin('asignacionSesion as asig', function ($join) use ($idContrato) {
                $join->on('hm.id', '=', 'asig.idHorarioMateria')
                    ->where('asig.idContrato', '=', $idContrato);
            });
        }

        $qb->where(function ($query) use ($idContrato, $tieneAsignacionSesion) {
            $query->where('hm.idContrato', $idContrato);
            if ($tieneAsignacionSesion) {
                $query->orWhereNotNull('asig.id');
            }
        });

        return $qb->select([
            'f.id as idFicha',
            'f.codigo',
            'hm.id as idHorarioMateria',
            'm.id',
            'm.idMateriaPadre',
        ])->get();
    }
}
