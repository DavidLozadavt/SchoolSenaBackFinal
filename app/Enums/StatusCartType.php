<?php

declare(strict_types=1);

namespace App\Enums;

use BenSampo\Enum\Enum;

/**
 * @method static static PENDIENTE()
 * @method static static PAGO()
 * @method static static ENVIADO()
 * @method static static FINALIZADO()
 * @method static static RECHAZADO()
 * @method static static GARANTIA()
 */
final class StatusCartType extends Enum
{
    const PENDIENTE = 'PENDIENTE';
    const PAGO = 'PAGO';
    const ENVIADO = 'ENVIADO';
    const FINALIZADO = 'FINALIZADO';
    const RECHAZADO = 'RECHAZADO';
    const GARANTIA = 'GARANTIA';
}
