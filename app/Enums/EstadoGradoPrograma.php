<?php

namespace App\Enums;

use BenSampo\Enum\Enum;

final class EstadoGradoPrograma extends Enum
{
  const PENDIENTE  = "PENDIENTE";
  const EN_CURSO  = "EN CURSO";
  const CANCELADO  = "CANCELADO";
  const INTERRUMPIDO  = "INTERRUMPIDO";
  const FINALIZADO = "FINALIZADO";
}