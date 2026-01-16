<?php

declare(strict_types=1);

namespace App\Enums;

use BenSampo\Enum\Enum;

/**
 * @method static static DIURNA()
 * @method static static NOCTURNA()
 * @method static static FESTIVA()
 * @method static static FESTIVA_NOCTURA()
 * @method static static DOMINICAL()
 * @method static static DOMINICAL_NOCTURNA()
 */
final class OverTimeType extends Enum
{
    const DIURNA = 'DIURNA';
    const NOCTURNA = 'NOCTURNA';
    const FESTIVA = 'FESTIVA';
    const FESTIVA_NOCTURA = 'FESTIVA NOCTURA';
    const DOMINICAL = 'DOMINICAL';
    const DOMINICAL_NOCTURNA = 'DOMINICAL NOCTURNA';
}
