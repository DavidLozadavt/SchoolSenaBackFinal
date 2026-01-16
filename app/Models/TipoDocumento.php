<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TipoDocumento extends Model
{
    use HasFactory;

    public static $snakeAttributes = false;
    protected $table = "tipoDocumento";
    protected $fillable = [
        "tituloDocumento",
        "descripcion",
        "idEstado",
        "idProceso"
    ];


    public function estado()
    {
        return $this->belongsTo(Status::class, 'idEstado');
    }

    public function tiposDocumentos()
    {
        return $this->hasMany(DocumentoContrato::class, 'idTipoDocumento');
    }

    public function asignaciones()
    {
        return $this->hasMany(AsignacionProcesoTipoDocumento::class, 'idTipoDocumento');
    }

}
