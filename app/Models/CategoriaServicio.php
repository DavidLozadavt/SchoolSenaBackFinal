<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CategoriaServicio extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $table = 'categoriaServicios';


    public function servicios()
    {
        return $this->hasMany(Servicio::class, 'idCategoriaServicio');
    }

}
