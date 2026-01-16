<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OtraDeduccion extends Model
{
    use HasFactory;

    protected $table = "otrasDeducciones";

        protected $fillable = [
        "observacon",
        "estado"
    ];

    public function contrato()
    {
        return $this->belongsTo(Contract::class, 'idContrato');
    }


    public function tipoConcepto()
    {
        return $this->belongsTo(TipoConcepto::class, 'idTipoConcepto');
    }


    const RUTA_FOTO = "persona";
    const RUTA_FOTO_DEFAULT = "/default/user.svg";

    protected $appends = ['archivoUrl'];


    public function getArchivoUrlAttribute()
    {
        if (
            isset($this->attributes['urlArchivo']) &&
            isset($this->attributes['urlArchivo'][0])
        ) {
            return url($this->attributes['urlArchivo']);
        }
        return url(self::RUTA_FOTO_DEFAULT);
    }
}
