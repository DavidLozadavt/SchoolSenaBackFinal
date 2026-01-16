<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PrestacionServicioTransaccion extends Model
{
    use HasFactory;

    protected $table = 'prestacionServicioTransaccion';

    public $timestamps = false;


    public function transaccion()
    {
        return $this->belongsTo(Transaccion::class, 'idTransaccion');
    }

    public function prestacion()
    {
        return $this->belongsTo(PrestacionServicio::class, 'idPrestacionServicio');
    }
}
