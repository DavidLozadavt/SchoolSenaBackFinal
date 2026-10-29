<?php

namespace App\Support;

/**
 * Reglas puras de Material de competencia (sin I/O).
 * RAP = materia con idMateriaPadre > 0. Competencia = materia raíz (sin padre).
 * El RAP de una actividad es actividades.idMateria.
 */
class MaterialCompetenciaReglas
{
    public const ORIGEN_DIRECTO = 'DIRECTO';
    public const ORIGEN_ACTIVIDAD = 'ACTIVIDAD';
    public const ORIGEN_MATERIAL_APOYO = 'MATERIAL_APOYO';

    /** @var list<string> */
    public const ORIGENES = [
        self::ORIGEN_DIRECTO,
        self::ORIGEN_ACTIVIDAD,
        self::ORIGEN_MATERIAL_APOYO,
    ];

    public static function esRap(?int $idMateriaPadre): bool
    {
        return $idMateriaPadre !== null && $idMateriaPadre > 0;
    }

    public static function esCompetencia(?int $idMateriaPadre): bool
    {
        return $idMateriaPadre === null || $idMateriaPadre <= 0;
    }

    /**
     * Competencia dueña de una materia: el padre si es RAP, o ella misma si es competencia.
     */
    public static function idCompetenciaDeMateria(?int $idMateria, ?int $idMateriaPadre): ?int
    {
        if (self::esRap($idMateriaPadre)) {
            return (int) $idMateriaPadre;
        }
        if ($idMateria !== null && $idMateria > 0 && self::esCompetencia($idMateriaPadre)) {
            return $idMateria;
        }

        return null;
    }

    public static function rapPerteneceACompetencia(?int $idRap, ?int $idMateriaPadre, int $idCompetencia): bool
    {
        if ($idCompetencia <= 0 || $idRap === null || $idRap <= 0 || ! self::esRap($idMateriaPadre)) {
            return false;
        }

        return (int) $idMateriaPadre === $idCompetencia;
    }

    public static function origenValido(?string $origen): bool
    {
        return in_array((string) $origen, self::ORIGENES, true);
    }

    /**
     * Un recurso de actividad es elegible si su RAP pertenece a la competencia destino.
     */
    public static function materialElegibleParaCompetencia(?int $idPadreRapActividad, int $idCompetenciaDestino): bool
    {
        return $idCompetenciaDestino > 0
            && $idPadreRapActividad !== null
            && $idPadreRapActividad > 0
            && $idPadreRapActividad === $idCompetenciaDestino;
    }

    /**
     * Clave de unicidad de incorporación (no aplica a subidas DIRECTO).
     */
    public static function claveIncorporacion(string $origen, ?int $idActividad, ?int $idMaterialApoyoActividad): ?string
    {
        if ($origen === self::ORIGEN_MATERIAL_APOYO) {
            $id = (int) $idMaterialApoyoActividad;

            return $id > 0 ? $origen.':apoyo:'.$id : null;
        }
        if ($origen === self::ORIGEN_ACTIVIDAD) {
            $id = (int) $idActividad;

            return $id > 0 ? $origen.':actividad:'.$id : null;
        }

        return null;
    }

    public static function puedeTrasladarOrigen(?string $origen): bool
    {
        return $origen === self::ORIGEN_DIRECTO;
    }

    public static function motivoBloqueoTraslado(?string $origen): ?string
    {
        if ($origen === self::ORIGEN_ACTIVIDAD) {
            return 'Este material está vinculado a una actividad. No se puede mover a otra competencia porque la actividad, sus evidencias y calificaciones permanecerían en el RAP original.';
        }
        if ($origen === self::ORIGEN_MATERIAL_APOYO) {
            return 'Este material está vinculado a una actividad. No se puede mover a otra competencia porque el material de apoyo debe permanecer con su actividad original.';
        }

        return null;
    }

    public static function esMismaAsociacion(
        string $origenA,
        ?int $idActividadA,
        ?int $idApoyoA,
        string $origenB,
        ?int $idActividadB,
        ?int $idApoyoB
    ): bool {
        $claveA = self::claveIncorporacion($origenA, $idActividadA, $idApoyoA);
        $claveB = self::claveIncorporacion($origenB, $idActividadB, $idApoyoB);

        return $claveA !== null && $claveA === $claveB;
    }
}
