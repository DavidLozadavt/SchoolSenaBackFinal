<?php declare(strict_types=1);

namespace App\Enums;

use BenSampo\Enum\Enum;

/**
 * @method static static MAYOR()
 * @method static static MENOR()
 * @method static static IGUAL()
 */
final class PayrollConfigurationStatus extends Enum
{
    const MAYOR = 'MAYOR';
    const MENOR = 'MENOR';
    const IGUAL = 'IGUAL';
}
