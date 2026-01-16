<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AporteSocio extends Model
{
    use HasFactory;

    
    const RUTA_DOCUMENTO = "aportesSocios/documentos";
    const RUTA_DOCUMENTO_DEFAULT = "/default/user.svg";
    const PATH = 'aportesSocios';

    public static $snakeAttributes = false;
    protected $table = "aporteSocios";

    protected $appends = ['rutaDocumentoUrl'];


    public function getRutaDocumentoUrlAttribute()
    {
        if (
            isset($this->attributes['documentoAdicional']) &&
            isset($this->attributes['documentoAdicional'][0])
        ) {
            return url($this->attributes['documentoAdicional']);
        }
        return url(self::RUTA_DOCUMENTO_DEFAULT);
    }
}
