<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PortafolioCategoria extends Model
{
    protected $table = 'portafolioCategorias';
    protected $fillable = ['nombre', 'slug', 'idCategoriaPadre', 'orden', 'activo', 'idContrato'];

    public function hijos()
    {
        return $this->hasMany(self::class, 'idCategoriaPadre')->where('activo', true)->orderBy('orden');
    }

    public function padre()
    {
        return $this->belongsTo(self::class, 'idCategoriaPadre');
    }
}