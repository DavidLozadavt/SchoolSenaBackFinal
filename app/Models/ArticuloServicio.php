<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ArticuloServicio extends Model
{
    use HasFactory;

    protected $table = "articuloServicio";


    public function tipoArticulo()
    {
        return $this->belongsTo(tipoArticulo::class, 'idTipoArticulo');
    }

    public function multimedias()
    {
        return $this->hasMany(MultimediaArticulos::class, 'idArticuloServicio');
    }
        
}
