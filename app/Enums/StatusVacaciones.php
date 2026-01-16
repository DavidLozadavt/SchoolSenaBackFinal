<?php declare(strict_types=1);

namespace App\Enums;

use BenSampo\Enum\Enum;

/**
 * @method static static LIQUIDADO()
 * @method static static ACEPTADO()
 * @method static static PENDIENTE()
 * @method static static POR_AUTORIZAR()
 */
final class StatusVacaciones extends Enum
{
    const LIQUIDADO = 'LIQUIDADO';
    const ACEPTADO = 'ACEPTADO';
    const PENDIENTE = 'PENDIENTE';
    const RECHAZADO = 'RECHAZADO';
    const FINALIZADO = 'FINALIZADO';
    const POR_AUTORIZAR = 'POR AUTORIZAR';
}
