<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DocumentoVehiculo extends Model
{
    use HasFactory;

    protected $table = "documentoVehiculo";


    const RUTA_FILE = "/default/auto.png";


    protected $appends = ['rutaUrl'];

    public function getRutaUrlAttribute()
    {
        if (
            isset($this->attributes['ruta']) &&
            isset($this->attributes['ruta'][0])
        ) {
            return url($this->attributes['ruta']);
        }
        return url(self::RUTA_FILE);
    }


    public function tipoDocumento()
    {
        return $this->belongsTo(TipoDocumento::class, 'idTipoDocumento');
    }


    public function estado()
    {
        return $this->belongsTo(TipoDocumento::class, 'idTipoDocumento');
    }
}
