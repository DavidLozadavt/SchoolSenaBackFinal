<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ArchivoContrato extends Model
{
    use HasFactory;

    const RUTA_ARCHIVOS_CONTRATO = "archivosContrato";
    const RUTA_ARCHIVOS_CONTRATO_DEFAULT = "/default/user.svg";
    const PATH = 'archivos_contrato';

    protected $appends = ['rutaArchivoContratoUrl'];

    public static $snakeAttributes = false;

    protected $table = "archivosContrato"; 

    protected $fillable = [
        "idEstado"
    ];

    public function getRutaArchivoContratoUrlAttribute()
    {
        if (
            isset($this->attributes['url']) &&
            isset($this->attributes['url'][0])
        ) {
            return url($this->attributes['url']);
        }
        return url(self::RUTA_ARCHIVOS_CONTRATO_DEFAULT);
    }

    public function contrato()
    {
        return $this->belongsTo(Contract::class, 'idContrato');
    }
}
