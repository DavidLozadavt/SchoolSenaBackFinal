<?php

declare(strict_types=1);

namespace App\Enums;

use BenSampo\Enum\Enum;

/**
 * @method static static COMISIONES()
 * @method static static SALARIOINTEGRAL()
 * @method static static NORMAL()
 */
final class TypePaymentMethodContract extends Enum
{
    const COMISIONES = 'COMISIONES';
    const SALARIOINTEGRAL = 'SALARIO INTEGRAL';
    const NORMAL = 'NORMAL';
}
