<?php declare(strict_types=1);

namespace App\Enums;

use BenSampo\Enum\Enum;

/**
 * @method static static ACEPTADO()
 * @method static static PENDIENTE()
 * @method static static RECHAZADO()
 * @method static static FINALIZADO()
 */
final class SolicitudIncLicPersonas extends Enum
{
    const ACEPTADO = 'ACEPTADO';
    const PENDIENTE = 'PENDIENTE';
    const RECHAZADO = 'RECHAZADO';
    const FINALIZADO = 'FINALIZADO';
}
