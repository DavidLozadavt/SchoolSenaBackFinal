<?php declare(strict_types=1);

namespace App\Enums;

use BenSampo\Enum\Enum;

/**
 * @method static static APROBADO()
 * @method static static PENDIENTE()
 * @method static static RECHAZADO()
 */
final class StatusType extends Enum
{
    const APROBADO = 'APROBADO';
    const PENDIENTE = 'PENDIENTE';
    const RECHAZADO = 'RECHAZADO';
}
