<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Red extends Model
{
    use HasFactory;

    protected $table = 'red';
    protected $fillable = [
        'nombre',
        'descripcion',
        'foto'
    ];

    const RUTA_FOTO = "foto";
    const RUTA_FOTO_DEFAULT = "/default/logoweb.png";

    protected $appends = ['foto'];

    public function geFotoAttribute()
    {
        if (
            isset($this->attributes['foto']) &&
            isset($this->attributes['foto'][0])
        ) {
            return url($this->attributes['foto']);
        }
        return url(self::RUTA_FOTO_DEFAULT);
    }
    public function programas() 
    {
        return $this->hasMany(Programa::class, 'idRed');
    }
}
