<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Pago extends Model
{
    use HasFactory;

    const RUTA_COMPROBANTE = "comprobante";
    const RUTA_COMPROBANTE_DEFAULT = "/default/user.svg";
    const PATH = 'comprobante_pago';

    protected $appends = ['rutaComprobanteUrl'];

    public static $snakeAttributes = false;

    protected $table = "pagos";
    
    protected $guarded = [];

    const numeroC = '261-620568-07';

    public function getRutaComprobanteUrlAttribute()
    {
        if (
            isset($this->attributes['rutaComprobante']) &&
            isset($this->attributes['rutaComprobante'][0])
        ) {
            return url($this->attributes['rutaComprobante']);
        }
        return url(self::RUTA_COMPROBANTE_DEFAULT);
    }
    

    public function transaccion()
    {
        return $this->belongsTo(Transaccion::class, 'idTransaccion');
    }

    public function estado()
    {
        return $this->belongsTo(Status::class, 'idEstado');
    }

    public function documentosPago()
    {
        return $this->hasMany(DocumentoPago::class, 'idPago');
    }
    
    public function documentoEstado()
    {
        return $this->hasOne(DocumentoEstado::class, 'idPago');
    }
    
    public function pagoCuenta()
    {
        return $this->hasMany(AgregarPagoCuenta::class, 'idPago');
    }
                              
}
