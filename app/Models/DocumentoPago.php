<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DocumentoPago extends Model
{
    use HasFactory;


    const RUTA_DOCUMENTO_PAGOS = "documento_pagos";
    const RUTA_DOCUMENTO_PAGOS_DEFAULT = "/default/user.svg";
    const PATH = 'documentos_pagos';



    protected $table = 'documento_pago';
    public $timestamps = false;
    public static $snakeAttributes = false;


    protected $appends = ['rutaFileUrl'];


    public function getRutaFileUrlAttribute()
    {
        if (
            isset($this->attributes['ruta']) &&
            isset($this->attributes['ruta'][0])
        ) {
            return url($this->attributes['ruta']);
        }
        return url(self::RUTA_DOCUMENTO_PAGOS_DEFAULT);
    }

    public function pago()
    {
        return $this->belongsTo(Pago::class, 'idPago');
    }
    
    public function AsignacionTipoDocumentoProceso()
    {
        return $this->belongsTo(AsignacionProcesoTipoDocumento::class, 'idAsignacionTipoDocumentoProceso');
    }

    public function estado()
    {
        return $this->belongsTo(Status::class, 'idEstado');
    }

    public function documentosEstado()
    {
        return $this->hasMany(DocumentoEstado::class, 'idDocumento');
    }
}
