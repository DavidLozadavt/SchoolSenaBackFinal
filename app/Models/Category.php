<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use HasFactory;

    protected $fillable = [
        'nombre', 
        'descripcion', 
        'url',
    ];


    protected $table = "categoria";

    const RUTA_CATEGORY = "categoria";
    const RUTA_CATEGORY_DEFAULT = "/default/category.png";


    protected $appends = ['rutaUrl'];

    public function getRutaUrlAttribute()
    {
        if (
            isset($this->attributes['url']) &&
            isset($this->attributes['url'][0])
        ) {
            return url($this->attributes['url']);
        }
        return url(self::RUTA_CATEGORY_DEFAULT);
    }


    public function productos()
    {
        return $this->hasMany(Producto::class, 'idCategoria');
    }
}
