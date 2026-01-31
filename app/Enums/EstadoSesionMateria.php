<?php declare(strict_types=1);

namespace App\Enums;

use BenSampo\Enum\Enum;

/**
 * @method static static APLICA()
 * @method static static NOAPLICA()
 */
final class EstadoSesionMateria extends Enum
{
    const APLICA = 'APLICA';
    const NOAPLICA = 'NO APLICA';
}
