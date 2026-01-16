<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ObservacionAfiliacion extends Model
{
    use HasFactory;

     protected $table = "observacionesAfiliacion";



      const RUTA_FILE = "/default/user.svg";

 
    protected $appends = ['urlObservacion'];

    public function getUrlObservacionAttribute()
    {
        if (
            isset($this->attributes['rutaUrl']) &&
            isset($this->attributes['rutaUrl'][0])
        ) {
            return url($this->attributes['rutaUrl']);
        }
        return url(self::RUTA_FILE);
    }
}
