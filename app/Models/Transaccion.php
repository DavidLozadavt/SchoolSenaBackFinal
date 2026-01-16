<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaccion extends Model
{
    use HasFactory;

    public static $snakeAttributes = false;

    protected $table = "transaccion";

    protected $guarded = [];

    public function pago()
    {
        return $this->hasMany(Pago::class, 'idTransaccion');
    }

    public function contratos()
    {
        return $this->belongsToMany(Contract::class, 'asignacion_contrato_transaccion', 'transaccion_id', 'contrato_id');
    }


    public function contratosCliente()
    {
        return $this->belongsToMany(ContratoCliente::class, 'asignacion_contrato_cliente_transaccion', 'idTransaccion', 'idContrato');
    }



    public function tipoPago()
    {
        return $this->belongsTo(TipoPago::class, 'idTipoPago');
    }


    public function caja()
    {
        return $this->belongsTo(Caja::class, 'idCaja');
    }

    public function facturas()
    {
        return $this->belongsToMany(Factura::class, 'asignacion_factura_transaccion', 'idTransaccion', 'idFactura');
    }


    public function prestaciones()
    {
        return $this->belongsToMany(PrestacionServicio::class, 'prestacionServicioTransaccion', 'idTransaccion', 'idPrestacionServicio');
    }
}
