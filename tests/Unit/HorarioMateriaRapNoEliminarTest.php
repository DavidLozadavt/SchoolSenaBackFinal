<?php

namespace Tests\Unit;

use App\Enums\EstadoHorarioMateria;
use App\Models\HorarioMateria;
use App\Support\HorarioMateriaVinculoReglas;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\TestCase;

class HorarioMateriaRapNoEliminarTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        $app = require __DIR__.'/../../bootstrap/app.php';
        $app->make(Kernel::class)->bootstrap();
    }

    public function test_ultimo_horario_de_ficha_se_conserva_como_placeholder(): void
    {
        $this->assertTrue(HorarioMateriaVinculoReglas::debeConservarPlaceholder(1));
        $this->assertTrue(HorarioMateriaVinculoReglas::debeConservarPlaceholder(0));
        $this->assertFalse(HorarioMateriaVinculoReglas::debeConservarPlaceholder(2));
        $this->assertFalse(HorarioMateriaVinculoReglas::debeConservarPlaceholder(5));
    }

    public function test_rap_593101_y_competencia_siguen_en_materia(): void
    {
        if (! Schema::hasTable('materia')) {
            $this->markTestSkipped('Tabla materia no disponible.');
        }

        $rap = DB::table('materia')->where('codigo', '593101')->first();
        $this->assertNotNull($rap, 'RAP 593101 debe existir.');
        $this->assertSame(54, (int) $rap->id);
        $this->assertSame(52, (int) $rap->idMateriaPadre);

        $competencia = DB::table('materia')->where('id', 52)->first();
        $this->assertNotNull($competencia);
        $this->assertSame('38362', (string) $competencia->codigo);
        $this->assertTrue($competencia->idMateriaPadre === null || (int) $competencia->idMateriaPadre === 0);
    }

    public function test_grado_materia_373_se_comparte_entre_fichas_y_el_conteo_global_era_incorrecto(): void
    {
        if (! Schema::hasTable('horarioMateria') || ! Schema::hasTable('ficha')) {
            $this->markTestSkipped('Tablas de horario/ficha no disponibles.');
        }

        $fichaA = DB::table('ficha')->where('codigo', '3411909')->first();
        $fichaB = DB::table('ficha')->where('codigo', '3411925')->first();
        $this->assertNotNull($fichaA);
        $this->assertNotNull($fichaB);

        $global = (int) DB::table('horarioMateria')->where('idGradoMateria', 373)->count();
        $enA = (int) DB::table('horarioMateria')->where('idGradoMateria', 373)->where('idFicha', $fichaA->id)->count();
        $enB = (int) DB::table('horarioMateria')->where('idGradoMateria', 373)->where('idFicha', $fichaB->id)->count();

        $this->assertGreaterThan(0, $enB, 'Ficha 3411925 debe tener horario del RAP 593101.');
        $this->assertGreaterThan($enB, $global, 'Otras fichas inflan el conteo global del mismo gradoMateria.');
        $this->assertTrue(
            HorarioMateriaVinculoReglas::debeConservarPlaceholder($enB),
            'El último horario de 3411925 debe conservarse aunque existan filas en 3411909.'
        );
        $this->assertFalse(
            HorarioMateriaVinculoReglas::debeConservarPlaceholder($global),
            'El conteo global (bug anterior) habría autorizado borrar el vínculo de 3411925.'
        );
        $this->assertFalse(
            HorarioMateriaVinculoReglas::debeConservarPlaceholder($enA),
            'Ficha 3411909 tiene más de un slot: se puede borrar uno sin perder el RAP.'
        );
    }

    public function test_eliminar_ultimo_slot_de_ficha_deja_placeholder_y_no_borra_rap_ni_actividades(): void
    {
        if (! Schema::hasTable('horarioMateria') || ! Schema::hasTable('materia') || ! Schema::hasTable('ficha')) {
            $this->markTestSkipped('Tablas requeridas no disponibles.');
        }

        $rap = DB::table('materia')->where('codigo', '593101')->first();
        $ficha = DB::table('ficha')->where('codigo', '3408813')->first();
        $this->assertNotNull($rap);
        $this->assertNotNull($ficha);

        $idRap = (int) $rap->id;
        $idCompetencia = (int) $rap->idMateriaPadre;
        $idFicha = (int) $ficha->id;
        $actividadesAntes = Schema::hasTable('actividades')
            ? (int) DB::table('actividades')->where('idMateria', $idRap)->count()
            : 0;

        // 373 es el gradoMateria de 593101 compartido por 3411909/3411925; 3408813 no lo usa.
        $gradoMateriaId = 373;
        $this->assertTrue(
            DB::table('gradoMateria')->where('id', $gradoMateriaId)->where('idMateria', $idRap)->exists()
        );

        DB::beginTransaction();
        try {
            $dummy = HorarioMateria::create([
                'idFicha' => $idFicha,
                'idGradoMateria' => $gradoMateriaId,
                'idDia' => 2,
                'horaInicial' => '03:17:00',
                'horaFinal' => '04:17:00',
                'fechaInicial' => '2099-01-06',
                'fechaFinal' => '2099-01-06',
                'estado' => EstadoHorarioMateria::ASIGNADO,
            ]);

            $otro = HorarioMateria::create([
                'idFicha' => $idFicha,
                'idGradoMateria' => $gradoMateriaId,
                'idDia' => 3,
                'horaInicial' => '03:17:00',
                'horaFinal' => '04:17:00',
                'fechaInicial' => '2099-01-07',
                'fechaFinal' => '2099-01-07',
                'estado' => EstadoHorarioMateria::ASIGNADO,
            ]);

            $this->assertFalse($dummy->esUltimoHorarioDeFicha());
            $dummy->eliminarSlotConservandoVinculoFicha();
            $this->assertNull(HorarioMateria::find($dummy->id), 'Un slot extra de la misma ficha sí se borra.');

            $otro->refresh();
            $this->assertTrue($otro->esUltimoHorarioDeFicha());
            $otro->eliminarSlotConservandoVinculoFicha();
            $otro = HorarioMateria::find($otro->id);
            $this->assertNotNull($otro, 'El último slot de la ficha debe quedar como placeholder, no borrarse.');
            $this->assertNull($otro->idDia);
            $this->assertNull($otro->horaInicial);
            $this->assertSame(EstadoHorarioMateria::PENDIENTE, $otro->estado);

            $this->assertNotNull(DB::table('materia')->where('id', $idRap)->first());
            $this->assertNotNull(DB::table('materia')->where('id', $idCompetencia)->first());
            $this->assertTrue(
                DB::table('horarioMateria')->where('idFicha', $idFicha)->where('idGradoMateria', $gradoMateriaId)->exists(),
                'Debe quedar al menos un vínculo horarioMateria de la ficha con el RAP.'
            );

            if (Schema::hasTable('actividades')) {
                $this->assertSame(
                    $actividadesAntes,
                    (int) DB::table('actividades')->where('idMateria', $idRap)->count()
                );
            }
        } finally {
            DB::rollBack();
        }

        $this->assertNotNull(DB::table('materia')->where('codigo', '593101')->first());
        $this->assertNotNull(DB::table('materia')->where('id', $idCompetencia)->first());
        $this->assertFalse(
            DB::table('horarioMateria')->where('horaInicial', '03:17:00')->where('fechaInicial', '2099-01-06')->exists(),
            'Los dummies de prueba no deben quedar persistidos.'
        );
    }

    public function test_otras_fichas_conservan_el_rap_tras_quitar_slot_en_una(): void
    {
        if (! Schema::hasTable('horarioMateria') || ! Schema::hasTable('ficha')) {
            $this->markTestSkipped('Tablas no disponibles.');
        }

        $fichaA = DB::table('ficha')->where('codigo', '3411909')->first();
        $fichaB = DB::table('ficha')->where('codigo', '3411925')->first();
        $this->assertNotNull($fichaA);
        $this->assertNotNull($fichaB);

        $enBAntes = (int) DB::table('horarioMateria')
            ->where('idGradoMateria', 373)
            ->where('idFicha', $fichaB->id)
            ->count();
        $this->assertGreaterThan(0, $enBAntes);

        DB::beginTransaction();
        try {
            $dummy = HorarioMateria::create([
                'idFicha' => (int) $fichaA->id,
                'idGradoMateria' => 373,
                'idDia' => 1,
                'horaInicial' => '03:18:00',
                'horaFinal' => '04:18:00',
                'fechaInicial' => '2099-02-03',
                'fechaFinal' => '2099-02-03',
                'estado' => EstadoHorarioMateria::ASIGNADO,
            ]);
            $dummy->eliminarSlotConservandoVinculoFicha();

            $this->assertSame(
                $enBAntes,
                (int) DB::table('horarioMateria')->where('idGradoMateria', 373)->where('idFicha', $fichaB->id)->count()
            );
            $this->assertNotNull(DB::table('materia')->where('codigo', '593101')->first());
        } finally {
            DB::rollBack();
        }
    }
}
