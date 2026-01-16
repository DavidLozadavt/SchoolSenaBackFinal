<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TipoNotificacion extends Model
{
    use HasFactory;
    const ID_ACTIVO = 1;
    const ID_INACTIVO = 2;

    protected $table = "tipoNotificacion";
}
