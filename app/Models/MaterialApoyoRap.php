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
        'urlVideo',
        'idMateria',
        'idRap',
    ];

    public function materia()
    {
        return $this->belongsTo(Materia::class, 'idMateria');
    }

    public function rap()
    {
        return $this->belongsTo(Materia::class, 'idRap');
    }
}
