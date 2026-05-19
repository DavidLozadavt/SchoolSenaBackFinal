<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CategoriaFormacion extends Model
{
    use HasFactory;

    protected $table = 'categoriaFormacion';

    public function solicitudes()
    {
        return $this->hasMany(SolicitudMateria::class, 'idCategoriaFormacion', 'id');
    }

    public function contratos()
    {
        return $this->hasMany(Contract::class, 'idCategoriaFormacion', 'id');
    }

    public function materias()
    {
        return $this->hasMany(Materia::class, 'idCategoriaFormacion', 'id');
    }
}
