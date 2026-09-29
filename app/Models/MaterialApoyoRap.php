<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MaterialApoyoRap extends Model
{
    protected $table = 'materialApoyoRap';

    protected $fillable = [
        'idFicha',
        'idMateria',
        'idRap',
        'idPersona',
        'descripcion',
        'titulo',
        'tipoMaterial',
        'urlDocumento',
        'urlAdicional',
        'urlVideo',
        'activo',
    ];

    /** Valores admitidos en tipoMaterial (nullable para registros antiguos). */
    public const TIPOS_MATERIAL = [
        'GUIA_APRENDIZAJE',
        'MATERIAL_FORMACION',
        'TUTORIAL',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    public function ficha()
    {
        return $this->belongsTo(Ficha::class, 'idFicha');
    }

    public function materia()
    {
        return $this->belongsTo(Materia::class, 'idMateria');
    }

    public function rap()
    {
        return $this->belongsTo(Materia::class, 'idRap');
    }

    public function persona()
    {
        return $this->belongsTo(Person::class, 'idPersona');
    }
}
