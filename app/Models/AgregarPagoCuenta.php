<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AgregarPagoCuenta extends Model
{
    use HasFactory;

    protected $table = "agregarPagoCuenta";
    public static $snakeAttributes = false;
    public $timestamps = false;

    const DEBITO = 'D';
    const CREDITO = 'C'; 


    public function pago()
    {
        return $this->belongsTo(Pago::class, 'idPago');
    }

}
