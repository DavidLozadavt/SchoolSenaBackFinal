<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TipoProducto extends Model
{
    use HasFactory;

    protected $table = "tipoProducto";
    public static $snakeAttributes = false;

    protected $fillable = [
        "id",
        "nombreClaseProducto",
        "descipcion"
    ];
        public function claseProducto()
    {
        return $this->belongsTo(ClaseProducto::class, 'idClaseProducto');
    }

    public function productos()
    {
        return $this->hasMany(Producto::class, 'idTipoProducto');
    }
}
