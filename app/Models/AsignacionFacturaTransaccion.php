<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AsignacionFacturaTransaccion extends Model
{
    use HasFactory;
    protected $table = "asignacion_factura_transaccion";
    public static $snakeAttributes = false;
    public $timestamps = false;

    protected $guarded = [];
}
