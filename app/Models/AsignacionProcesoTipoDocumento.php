<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AsignacionProcesoTipoDocumento extends Model
{

    use HasFactory;


    protected $fillable = [
        "idProceso"
    ];
    public $timestamps = false;

    protected $table = "asignacion_proceso_tipo_documento";
    public static $snakeAttributes = false;

    public function proceso()
    {
        return $this->belongsTo(Proceso::class, 'idProceso');
    }


    public function tipoDocumento()
    {
        return $this->belongsTo(TipoDocumento::class, 'idTipoDocumento');
    }
}
