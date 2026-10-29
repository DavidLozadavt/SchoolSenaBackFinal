<?php

namespace Tests\Unit;

use App\Support\MaterialCompetenciaReglas;
use PHPUnit\Framework\TestCase;

class MaterialCompetenciaReglasTest extends TestCase
{
    public function test_identifica_rap_y_competencia(): void
    {
        $this->assertTrue(MaterialCompetenciaReglas::esRap(12));
        $this->assertFalse(MaterialCompetenciaReglas::esRap(null));
        $this->assertFalse(MaterialCompetenciaReglas::esRap(0));
        $this->assertTrue(MaterialCompetenciaReglas::esCompetencia(null));
        $this->assertTrue(MaterialCompetenciaReglas::esCompetencia(0));
        $this->assertFalse(MaterialCompetenciaReglas::esCompetencia(12));
    }

    public function test_competencia_de_un_rap_es_el_padre(): void
    {
        $this->assertSame(71, MaterialCompetenciaReglas::idCompetenciaDeMateria(74, 71));
        $this->assertSame(71, MaterialCompetenciaReglas::idCompetenciaDeMateria(71, null));
        $this->assertNull(MaterialCompetenciaReglas::idCompetenciaDeMateria(null, null));
    }

    public function test_material_de_otro_rap_es_elegible_si_comparte_competencia(): void
    {
        $idCompetencia = 71;
        $this->assertTrue(MaterialCompetenciaReglas::materialElegibleParaCompetencia($idCompetencia, $idCompetencia));
        $this->assertFalse(MaterialCompetenciaReglas::materialElegibleParaCompetencia(80, $idCompetencia));
        $this->assertFalse(MaterialCompetenciaReglas::materialElegibleParaCompetencia(null, $idCompetencia));
    }

    public function test_no_mezcla_competencias_distintas(): void
    {
        $this->assertFalse(MaterialCompetenciaReglas::materialElegibleParaCompetencia(10, 11));
    }

    public function test_no_duplica_la_misma_asociacion_de_apoyo(): void
    {
        $this->assertTrue(MaterialCompetenciaReglas::esMismaAsociacion(
            MaterialCompetenciaReglas::ORIGEN_MATERIAL_APOYO, 5, 99,
            MaterialCompetenciaReglas::ORIGEN_MATERIAL_APOYO, 8, 99
        ));
        $this->assertFalse(MaterialCompetenciaReglas::esMismaAsociacion(
            MaterialCompetenciaReglas::ORIGEN_MATERIAL_APOYO, 5, 99,
            MaterialCompetenciaReglas::ORIGEN_MATERIAL_APOYO, 5, 100
        ));
    }

    public function test_documento_de_actividad_se_deduplica_por_actividad(): void
    {
        $this->assertTrue(MaterialCompetenciaReglas::esMismaAsociacion(
            MaterialCompetenciaReglas::ORIGEN_ACTIVIDAD, 7, null,
            MaterialCompetenciaReglas::ORIGEN_ACTIVIDAD, 7, null
        ));
        $this->assertFalse(MaterialCompetenciaReglas::esMismaAsociacion(
            MaterialCompetenciaReglas::ORIGEN_ACTIVIDAD, 7, null,
            MaterialCompetenciaReglas::ORIGEN_ACTIVIDAD, 8, null
        ));
    }

    public function test_subida_directa_no_genera_clave_de_incorporacion(): void
    {
        $this->assertNull(MaterialCompetenciaReglas::claveIncorporacion(MaterialCompetenciaReglas::ORIGEN_DIRECTO, null, null));
        $this->assertTrue(MaterialCompetenciaReglas::origenValido(MaterialCompetenciaReglas::ORIGEN_DIRECTO));
        $this->assertFalse(MaterialCompetenciaReglas::origenValido('BIBLIOTECA'));
    }

    public function test_documento_de_actividad_y_material_de_apoyo_no_colisionan(): void
    {
        $this->assertFalse(MaterialCompetenciaReglas::esMismaAsociacion(
            MaterialCompetenciaReglas::ORIGEN_ACTIVIDAD, 7, null,
            MaterialCompetenciaReglas::ORIGEN_MATERIAL_APOYO, 7, 99
        ));
    }

    public function test_documentos_distintos_de_la_misma_actividad_tienen_clave_distinta(): void
    {
        $docActividad = MaterialCompetenciaReglas::claveIncorporacion(MaterialCompetenciaReglas::ORIGEN_ACTIVIDAD, 7, null);
        $apoyoA = MaterialCompetenciaReglas::claveIncorporacion(MaterialCompetenciaReglas::ORIGEN_MATERIAL_APOYO, 7, 99);
        $apoyoB = MaterialCompetenciaReglas::claveIncorporacion(MaterialCompetenciaReglas::ORIGEN_MATERIAL_APOYO, 7, 100);
        $this->assertNotSame($docActividad, $apoyoA);
        $this->assertNotSame($apoyoA, $apoyoB);
        $this->assertNotNull($docActividad);
        $this->assertNotNull($apoyoA);
        $this->assertNotNull($apoyoB);
    }

    public function test_solo_la_subida_directa_puede_trasladarse_de_competencia(): void
    {
        $this->assertTrue(MaterialCompetenciaReglas::puedeTrasladarOrigen(MaterialCompetenciaReglas::ORIGEN_DIRECTO));
        $this->assertFalse(MaterialCompetenciaReglas::puedeTrasladarOrigen(MaterialCompetenciaReglas::ORIGEN_ACTIVIDAD));
        $this->assertFalse(MaterialCompetenciaReglas::puedeTrasladarOrigen(MaterialCompetenciaReglas::ORIGEN_MATERIAL_APOYO));
        $this->assertNull(MaterialCompetenciaReglas::motivoBloqueoTraslado(MaterialCompetenciaReglas::ORIGEN_DIRECTO));
        $this->assertNotEmpty(MaterialCompetenciaReglas::motivoBloqueoTraslado(MaterialCompetenciaReglas::ORIGEN_ACTIVIDAD));
        $this->assertNotEmpty(MaterialCompetenciaReglas::motivoBloqueoTraslado(MaterialCompetenciaReglas::ORIGEN_MATERIAL_APOYO));
    }
}
