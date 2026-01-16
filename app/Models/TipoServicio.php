<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TipoServicio extends Model
{
    use HasFactory;

    protected $table = "tipoServicio";

    protected $fillable = ['id', 'nombreTipoServicio', 'idClaseServicio', 'descripcion'];

    
    public function claseServicio()
    {
        return $this->belongsTo(ClaseServicio::class, 'idClaseServicio');
    }
}
