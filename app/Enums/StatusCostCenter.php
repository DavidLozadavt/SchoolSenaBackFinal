<?php declare(strict_types=1);

namespace App\Enums;

use BenSampo\Enum\Enum;

/**
 * @method static static ENCURSO()
 * @method static static FINALIZADO()
 */
final class StatusCostCenter extends Enum
{
    const ENCURSO    = 'ENCURSO';
    const FINALIZADO = 'FINALIZADO';
}
