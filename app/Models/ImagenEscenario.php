<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ImagenEscenario extends Model
{
    use HasFactory;

    protected $table = 'imagenEscenario';

    protected $guarded = [];

    const PATH = "escenario/multiple";
    const RUTA_FOTO_DEFAULT = "/default/escenarios.jpg";

    public static $snakeAttributes = false;
    public $timestamps = false;
    public function escenario()
    {
        return $this->belongsTo(Escenario::class, 'idEscenario');
    }

    public function getUrlImageAttribute()
    {
        if (
            isset($this->attributes['urlImage']) &&
            !empty($this->attributes['urlImage'])
        ) {
            return url($this->attributes['urlImage']);
        }
        return url(self::RUTA_FOTO_DEFAULT);
    }
    public function getUrlVideoAttribute()
    {
        if (
            isset($this->attributes['urlVideo']) &&
            !empty($this->attributes['urlVideo'])
        ) {
            return url($this->attributes['urlVideo']);
        }
        return url(self::RUTA_FOTO_DEFAULT);
    }


    public function scopeImagenes($query)
    {
        return $query->where('tipo', 'IMAGEN');
    }

    public function scopeVideos($query)
    {
        return $query->where('tipo', 'VIDEO');
    }

}


