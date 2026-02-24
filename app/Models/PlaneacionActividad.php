<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlaneacionActividad extends Model
{
    protected $table = 'planeacionactividades';

    protected $fillable = ['idActividad', 'idMateria', 'idPlaneacion'];

    public function actividad()
    {
        return $this->belongsTo(Actividad::class, 'idActividad');
    }

    public function materia()
    {
        return $this->belongsTo(Materia::class, 'idMateria');
    }
}
