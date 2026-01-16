<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AsignacionTransaccionPago extends Model
{

    protected $table = "asignacion_transaccion_pago"; 
    public $timestamps = false;
    use HasFactory;

    const RUTA_ABONO_COMPROBANTE = "abonoComprobante";
    const RUTA_ABONO_COMPROBANTE_DEFAULT = "/default/user.svg";

    protected $appends = ['rutaUrlComprobanteAbono'];

    public function getRutaUrlComprobanteAbonolAttribute()
    {
        if (
            isset($this->attributes['urlComprobante']) &&
            isset($this->attributes['urlComprobante'][0])
        ) {
            return url($this->attributes['urlComprobante']);
        }
        return url(self::RUTA_ABONO_COMPROBANTE_DEFAULT);
    }
}
