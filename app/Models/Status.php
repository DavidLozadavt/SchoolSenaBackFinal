<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Status extends Model
{
    use HasFactory;

    protected $table = "estado";

    const ID_ACTIVE = 1;
    const ID_INACTIVO = 2;
    const ID_PENDIENTE = 4;
    const ID_APROBADO = 5;
    const ID_REPROBADO = 7;
    const ID_EN_ESPERA = 11;
    const ID_PENDIENTE_ADICIONAL = 12;
    const ID_INTERRUMPIDO = 13;
    const ID_ADICION_CONTRATO = 14;


    public function estado()
    {
        return $this->hasOne(Pago::class, 'idEstado');
    }
    
}
