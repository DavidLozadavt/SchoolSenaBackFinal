<?php

namespace App\Models;

use App\Traits\SaveFile;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DocumentoContrato extends Model
{
    use HasFactory, SaveFile;


    const RUTA_DOCUMENTO = "documento";
    const RUTA_DOCUMENTO_DEFAULT = "/default/user.svg";
    const PATH = 'documento_contrato';

    protected $hidden = [
        'company_id'
    ];

    protected $table = 'documento_contratos';
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
        return url(self::RUTA_DOCUMENTO_DEFAULT);
    }

    public function contrato()
    {
        return $this->belongsTo(Contract::class, 'idContrato');
    }
    
    public function AsignacionTipoDocumentoProceso()
    {
        return $this->belongsTo(AsignacionProcesoTipoDocumento::class, 'idAsignacionTipoDocumentoProceso');
    }

    public function estado()
    {
        return $this->belongsTo(Status::class, 'idEstado');
    }

}
