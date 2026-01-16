<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DocumentoPropietario extends Model
{
    use HasFactory;

    protected $table = "documentoPropietario";

    const RUTA_FILE = "/default/user.svg";

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
}
