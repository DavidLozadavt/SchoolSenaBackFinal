<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CategoriaFormacion extends Model
{
    use HasFactory;

    public function solicitudes()
    {
        return $this->hasMany(SolicitudMateria::class, 'idCategoriaFormacion', 'id');
    }
}
