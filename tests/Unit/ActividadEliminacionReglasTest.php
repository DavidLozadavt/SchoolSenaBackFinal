<?php

namespace Tests\Unit;

use App\Support\ActividadEliminacionReglas;
use App\Support\MaterialCompetenciaReglas;
use PHPUnit\Framework\TestCase;

class ActividadEliminacionReglasTest extends TestCase
{
    public function test_solo_inactivo_sin_fichas_puede_eliminarse(): void
    {
        $this->assertNull(ActividadEliminacionReglas::motivoNoEliminar(true, 0));
        $this->assertSame(
            ActividadEliminacionReglas::MSG_NO_INACTIVA,
            ActividadEliminacionReglas::motivoNoEliminar(false, 0)
        );
        $this->assertSame(
            ActividadEliminacionReglas::MSG_ASIGNADA_FICHA,
            ActividadEliminacionReglas::motivoNoEliminar(true, 1)
        );
        $this->assertSame(
            ActividadEliminacionReglas::MSG_ASIGNADA_FICHA,
            ActividadEliminacionReglas::motivoNoEliminar(true, 3)
        );
        $this->assertSame(
            ActividadEliminacionReglas::MSG_ASIGNADA_FICHA,
            ActividadEliminacionReglas::motivoNoEliminar(false, 2)
        );
    }

    public function test_conserva_proteccion_de_asignacion_a_clase(): void
    {
        $this->assertSame(
            ActividadEliminacionReglas::MSG_ASIGNADA_CLASE,
            ActividadEliminacionReglas::motivoNoEliminar(true, 0, true)
        );
    }

    public function test_identifica_estado_inactivo(): void
    {
        $this->assertTrue(ActividadEliminacionReglas::esInactivo(2, 'ACTIVO'));
        $this->assertTrue(ActividadEliminacionReglas::esInactivo(1, 'INACTIVO'));
        $this->assertTrue(ActividadEliminacionReglas::esInactivo(null, 'inactivo'));
        $this->assertFalse(ActividadEliminacionReglas::esInactivo(1, 'ACTIVO'));
        $this->assertFalse(ActividadEliminacionReglas::esInactivo(null, null));
    }

    public function test_rap_pertenece_solo_a_su_competencia(): void
    {
        $this->assertTrue(MaterialCompetenciaReglas::rapPerteneceACompetencia(74, 71, 71));
        $this->assertFalse(MaterialCompetenciaReglas::rapPerteneceACompetencia(74, 71, 80));
        $this->assertFalse(MaterialCompetenciaReglas::rapPerteneceACompetencia(71, null, 71));
        $this->assertFalse(MaterialCompetenciaReglas::rapPerteneceACompetencia(74, 71, 0));
    }
}
