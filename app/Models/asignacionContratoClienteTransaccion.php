<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class asignacionContratoClienteTransaccion extends Model
{
    use HasFactory;

    protected $table = "asignacion_contrato_cliente_transaccion";

    public $timestamps = false;


    public function transaccion()
    {
        return $this->belongsTo(Transaccion::class, 'idTransaccion');
    }
}
