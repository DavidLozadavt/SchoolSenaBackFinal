<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Reemplazo extends Model
{
    use HasFactory;

    protected $table = 'reemplazo';


    public function contratoReemplazo()
    {
        return $this->belongsTo(Contract::class, 'idContratoRemplazo');
    }

    public function contratoTrabajador()
    {
        return $this->belongsTo(Contract::class, 'idContratoTrabajador');
    }


    public function cargo()
    {
        return $this->belongsTo(Rol::class, 'idCargo');
    }


    public function pago()
    {
        return $this->hasOne(PagoReemplazo::class, 'idReemplazo');
    }
}
