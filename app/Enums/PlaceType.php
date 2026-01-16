<?php

declare(strict_types=1);

namespace App\Enums;

use BenSampo\Enum\Enum;

/**
 * @method static static VEREDA()
 * @method static static CORREGIMIENTO()
 */
final class PlaceType extends Enum
{
    const VEREDA = 'VEREDA';
    const CORREGIMIENTO = 'CORREGIMIENTO';

    // const MUNICIPIO = 'municipio';
    // const DEPARTAMENTO = 'departamento';
    // const BARRIO = 'barrio';
    // const LOCALIDAD = 'localidad';
    // const CIUDAD = 'ciudad';
    // const PUEBLO = 'pueblo';
    // const ALDEA = 'aldea';
    // const ZONA_RURAL = 'zona_rural';
    // const AREA_METROPOLITANA = 'area_metropolitana';
    // const DISTRITO = 'distrito';
    // const REGION = 'region';
    // const SECTOR = 'sector';
    // const PARQUE = 'parque';
    // const PLAZA = 'plaza';
    // const CALLE = 'calle';
    // const CAMINO = 'camino';
    // const SENDERO = 'sendero';
    // const ESTACION = 'estacion';
}
