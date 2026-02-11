<?php declare(strict_types=1);

namespace App\Enums;

use BenSampo\Enum\Enum;

/**
 * @method static static APROBADO()
 * @method static static POR_EVALUAR()
 * @method static static INTERRUMPIDO()
 */
final class EstadoSeguimientoMateria extends Enum
{
    const APROBADO = 'APROBADO';
    const POR_EVALUAR = 'POR EVALUAR';
    const INTERRUMPIDO = 'INTERRUMPIDO';
}
