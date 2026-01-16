<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tercero extends Model
{
    use HasFactory;

    const RUTA_RUT = "rutTercero";
    const RUTA_RUT_DEFAULT = "/default/user.svg";
    const PATH = 'comprobante_pago';

    protected $fillable = [
        "identificacion",
        "nombre",
        "fechaNac",
        "direccion",
        "email",
        "telefono",
        'idTipoTercero',
        'idCompany',

    ];

    protected $appends = ['rutaRutUrl'];

    public static $snakeAttributes = false;
    protected $table = "tercero";



    public function getRutaRutUrlAttribute()
    {
        if (
            isset($this->attributes['rutDocumento']) &&
            isset($this->attributes['rutDocumento'][0])
        ) {
            return url($this->attributes['rutDocumento']);
        }
        return url(self::RUTA_RUT_DEFAULT);
    }


    public function facturas()
    {
        return $this->hasMany(Factura::class, 'idTercero');
    }

    public function tipos()
    {
        return $this->belongsToMany(
            TipoTercero::class,
            'asignacionTerceroTipoTercero',
            'idTercero',
            'idTipoTercero'
        );
    }
}
