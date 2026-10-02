<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Material de apoyo ligado a actividades (vía asignacionMaterialApoyoActividad).
 * No usar para material de consulta por RAP (tabla materialApoyoRap).
 */
class MaterialApoyoActividad extends Model
{
    protected $table = 'materialApoyoActividad';

    protected $fillable = ['descripcion', 'titulo', 'urlDocumento', 'urlAdicional', 'idMateria'];

    public function materia()
    {
        return $this->belongsTo(Materia::class, 'idMateria');
    }
}
