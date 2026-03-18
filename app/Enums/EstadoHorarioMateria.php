<?php

namespace App\Enums;

use BenSampo\Enum\Enum;

final class EstadoHorarioMateria extends Enum
{
    const PENDIENTE = "PENDIENTE";
    const ASIGNADO = 'ASIGNADO';
    const FINALIZADO = 'FINALIZADO';
    const INTERRUMPIDO = 'INTERRUMPIDO';
    const EVALUADO = 'EVALUADO';

    /* 
    ALTER TABLE db_sena_school.horarioMateria MODIFY COLUMN estado enum('PENDIENTE','ASIGNADO','FINALIZADO','INTERRUMPIDO','EVALUADO') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'PENDIENTE' NOT NULL;
    */

}
