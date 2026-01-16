<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ContratoCliente extends Model
{
    use HasFactory;

    protected $table = "contratoCliente";

    const RUTA_CONTRATO_CLIENTE = "contratoCliente";
    const RUTA_CONTRATO_CLIENTE_DEFAULT = "/default/user.svg";
    const PATH = 'contrato_cliente';

    protected $appends = ['rutaContratoUrl'];


    public function getRutaContratoUrlAttribute()
    {
        if (
            isset($this->attributes['rutaContrato']) &&
            isset($this->attributes['rutaContrato'][0])
        ) {
            return url($this->attributes['rutaContrato']);
        }
        return url(self::RUTA_CONTRATO_CLIENTE_DEFAULT);
    }


    public function transacciones()
    {
        return $this->belongsToMany(Transaccion::class, 'asignacion_contrato_cliente_transaccion', 'idContrato', 'idTransaccion');
    }

    public function tercero()
    {
        return $this->belongsTo(Tercero::class, 'idTercero');
    }
}
