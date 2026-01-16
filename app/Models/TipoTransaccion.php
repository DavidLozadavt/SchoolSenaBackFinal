<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TipoTransaccion extends Model
{
    use HasFactory;
    public static $snakeAttributes = false;
    protected $table = "tipoTransaccion";
    protected $fillable = [
        "detalle",
        "descripcion"
    ];

    public $timestamps = false;

    const AFILIACION = 3;
    const SERVICIO = 4;
    const APORTE = 6;
    const NOMINA = 7;
    const LIQUIDACION = 8;
    const GASTOS = 9;
    const VENTA = 1;
}
