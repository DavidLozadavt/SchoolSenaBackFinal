<?php

namespace App\Models;

use App\Models\Nomina\Sede;
use App\Traits\SaveFile;
use App\Traits\UtilNotification;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PuntoVenta extends Model
{
    use HasFactory,  SaveFile;
    public static $snakeAttributes = false;
    protected $table = 'puntoVenta';
    protected $guarded = [];
    const PATH = "puntoVenta";
    const RUTA_FOTO_DEFAULT = "default/pos.png";

   

    public function cajas()
    {
        return $this->hasMany(Caja::class, 'idPuntoDeVenta');
    }
    
    public function sedes()
    {
        return $this->hasMany(Sede::class, 'idSede');
    }


    public function sede()
    {
        return $this->belongsTo(Sede::class, 'idSede');
    }

    public function getimagenUrlAttribute()
    {
        if (
            isset($this->attributes['imagenUrl']) &&
            isset($this->attributes['imagenUrl'][0])
        ) {
            return url($this->attributes['imagenUrl']);
        }
        return url(self::RUTA_FOTO_DEFAULT);
    }
    public function savePuntoVentaImage($request)
    {
        $default = 'default/pos.png';
        if (isset($this->attributes['imagenUrl'])) {
            $default = $this->attributes['imagenUrl'];
        }
        $this->attributes['imagenUrl'] = $this->storeFile(
            $request,
            'imagenUrl',
            self::PATH,
            $default
        );
        return $this->attributes['imagenUrl'];
    }
    protected $appends = ['imagenUrl'];
}
