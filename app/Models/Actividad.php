<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Actividad extends Model
{
    protected $table = 'actividades';

    public $timestamps = false;

    protected $fillable = [
        'tituloActividad',
        'descripcionActividad',
        'pathDocumentoActividad',
        'autor',
        'tipoActividad',
        'idMateria',
        'idEstado',
        'idCompany',
        'idPersona',
        'idClasificacion',
        'estrategia',
        'entregables',
    ];

    public function materia()
    {
        return $this->belongsTo(Materia::class, 'idMateria');
    }

    public function estado()
    {
        return $this->belongsTo(Status::class, 'idEstado');
    }

    public function company()
    {
        return $this->belongsTo(Company::class, 'idCompany');
    }

    public function persona()
    {
        return $this->belongsTo(Person::class, 'idPersona');
    }

    public function clasificacion()
    {
        return $this->belongsTo(ClasificacionActividad::class, 'idClasificacion');
    }

    public function materialesApoyo()
    {
        return $this->belongsToMany(MaterialApoyoActividad::class, 'asignacionMaterialApoyoActividad', 'idActividad', 'idMaterialApoyo')
            ->withTimestamps();
    }

    public function preguntas()
    {
        return $this->hasMany(Pregunta::class, 'idActividad');
    }
}
