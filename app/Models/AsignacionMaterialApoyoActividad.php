<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Relación única: actividad ↔ materialApoyoActividad (material propio de la actividad).
 */
class AsignacionMaterialApoyoActividad extends Model
{
    protected $table = 'asignacionMaterialApoyoActividad';

    protected $fillable = ['idActividad', 'idMaterialApoyo'];

    public function actividad()
    {
        return $this->belongsTo(Actividad::class, 'idActividad');
    }

    public function materialApoyo()
    {
        return $this->belongsTo(MaterialApoyoActividad::class, 'idMaterialApoyo');
    }
}
