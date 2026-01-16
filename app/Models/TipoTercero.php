<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TipoTercero extends Model
{
    use HasFactory;
    public static $snakeAttributes = false;
    protected $table = "tipoTercero";

    const CLIENTE = 1;
    const PROVEEDOR = 2;
    const PERSONA_NATURAL = 3;
    const SOCIO = 4;
    const PERSONA_NATURAL_ASOCIADO = 6;
    const PERSONA_JURIDICA_ASOCIADO = 7;

}
