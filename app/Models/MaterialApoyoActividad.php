<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MaterialApoyoActividad extends Model
{
    protected $table = 'materialApoyoActividad';

    protected $fillable = ['descripcion', 'titulo', 'urlDocumento', 'urlAdicional', 'idMateria', 'idFicha'];

    public function materia()
    {
        return $this->belongsTo(Materia::class, 'idMateria');
    }

    public function ficha()
    {
        return $this->belongsTo(Ficha::class, 'idFicha');
    }
}
