<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Factura extends Model
{
    use HasFactory;

    protected $table = "factura";
    public static $snakeAttributes = false;
    protected $guarded = [];

    const RUTA_FACTURA_DEFAULT = "/default/user.svg";
    const RUTA_FACTURA = "facturas";
    const RUTA_FACTURA_RECIBO_PAGO = 'recibo_factura';
    const PATH = 'facturas';

    protected $appends = ['rutaFacturaUrl', 'reciboPagoFacturaUrl'];

    public function getRutaFacturaUrlAttribute()
    {
        if (
            isset($this->attributes['fotoFactura']) &&
            isset($this->attributes['fotoFactura'][0])
        ) {
            return url($this->attributes['fotoFactura']);
        }
        return url(self::RUTA_FACTURA_DEFAULT);
    }


    
    public function getReciboPagoFacturaUrlAttribute()
    {
        if (
            isset($this->attributes['reciboPagoFactura']) &&
            isset($this->attributes['reciboPagoFactura'][0])
        ) {
            return url($this->attributes['reciboPagoFactura']);
        }
        return url(self::RUTA_FACTURA_DEFAULT);
    }


    public function transacciones()
    {
        return $this->belongsToMany(Transaccion::class, 'asignacion_factura_transaccion', 'idFactura', 'idTransaccion');
    }


    public function tercero()
    {
        return $this->belongsTo(Tercero::class, 'idTercero');
    }

    public function detalles()
    {
        return $this->hasMany(DetalleFactura::class, 'idFactura');
    }


}
