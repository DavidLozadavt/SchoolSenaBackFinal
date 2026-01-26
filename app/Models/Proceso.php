<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Proceso extends Model
{
    use HasFactory;
    public static $snakeAttributes = false;
    protected $table = "proceso";
    protected $fillable = [
        "nombreProceso",
        "descripcion"
    ];

    public $timestamps = false;


    public function asignaciones()
    {
        return $this->hasMany(AsignacionProcesoTipoDocumento::class, 'idProceso');
    }

    /**
     * Tipos de documento asignados a este proceso (toggle en "Asignar tipos de documentos").
     */
    public function tiposDocumento()
    {
        return $this->belongsToMany(
            TipoDocumento::class,
            'asignacion_proceso_tipo_documento',
            'idProceso',
            'idTipoDocumento'
        );
    }
}
