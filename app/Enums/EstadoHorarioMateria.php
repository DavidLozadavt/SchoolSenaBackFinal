<?php

namespace App\Enums;

use BenSampo\Enum\Enum;

final class EstadoHorarioMateria extends Enum
{
    const PENDIENTE = "PENDIENTE";
    const ASIGNADO = 'ASIGNADO';
    const FINALIZADO = 'FINALIZADO';
    const INTERRUMPIDO = 'INTERRUMPIDO';

}
