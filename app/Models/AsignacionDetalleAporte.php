<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AsignacionDetalleAporte extends Model
{
    use HasFactory;
    protected $table = "asignacion_detalle_aporte";
    public static $snakeAttributes = false;
    public $timestamps = false;
}
