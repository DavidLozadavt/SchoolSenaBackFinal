<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MaterialApoyoRap extends Model
{
    protected $table = 'materialApoyoRap';

    protected $fillable = [
        'descripcion',
        'titulo',
        'urlDocumento',
        'urlAdicional',
        'idMateria',
        'idRap',
        'idFicha',
        'idPersona',
        'activo',
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
