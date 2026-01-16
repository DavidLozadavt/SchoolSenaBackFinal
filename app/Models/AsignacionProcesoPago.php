<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AsignacionProcesoPago extends Model
{
    use HasFactory;

    protected $table = 'asignacionProcesoPagos';

    public function proceso()
    {
        return $this->belongsTo(Proceso::class, 'idProceso');
    }


    public function configuracionPago()
    {
        return $this->belongsTo(ConfiguracionPago::class, 'idConfiguracionPago');
    }
}
