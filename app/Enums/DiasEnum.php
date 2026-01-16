<?php

declare(strict_types=1);

namespace App\Enums;

use BenSampo\Enum\Enum;

/**
 * @method static static LUNES()
 * @method static static MARTES()
 * @method static static MIERCOLES()
 * @method static static JUEVES()
 * @method static static VIERNES()
 * @method static static SABADO()
 * @method static static DOMINGO()
 */
final class DiasEnum extends Enum
{
    const LUNES     = 'LUNES';
    const MARTES    = 'MARTES';
    const MIERCOLES = 'MIERCOLES';
    const JUEVES    = 'JUEVES';
    const VIERNES   = 'VIERNES';
    const SABADO    = 'SABADO';
    const DOMINGO   = 'DOMINGO';
}
