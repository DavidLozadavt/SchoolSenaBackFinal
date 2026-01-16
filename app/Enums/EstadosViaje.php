<?php

declare(strict_types=1);

namespace App\Enums;

use BenSampo\Enum\Enum;

/**
 * @method static static PENDIENTE()
 * @method static static AGENDADO()
 * @method static static EN VIAJE()
 * @method static static FINALIZADO()
 * @method static static PLANILLA()
 * @method static static CANCELADO()
 * @method static static INTERRUMPIDO()
 */
final class EstadosViaje extends Enum
{
    const PENDIENTE  = 'PENDIENTE';
    const AGENDADO  = 'AGENDADO';
    const EN_VIAJE   = 'EN VIAJE';
    const FINALIZADO = 'FINALIZADO';
    const PLANILLA   = 'PLANILLA';
    const CANCELADO  = 'CANCELADO';
    const INTERRUMPIDO  = 'INTERRUMPIDO';

}
