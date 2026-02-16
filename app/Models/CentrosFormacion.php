<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CentrosFormacion extends Model
{
    use HasFactory;

    protected $table = 'centroFormacion';

    protected $fillable = [
        'nombre',
        'direccion',
        'telefono',
        'correo',
        'subdirector',
        'correoSubdirector',
        'idCiudad',
        'idEmpresa',
        'foto'
    ];

    const RUTA_FOTO = "foto";
    const RUTA_FOTO_DEFAULT = "/default/logoweb.png";

    protected $appends = ['rutaFotoUrl'];

    public function getRutaFotoUrlAttribute()
    {
        if (
            isset($this->attributes['foto']) &&
            isset($this->attributes['foto'][0])
        ) {
            return url($this->attributes['foto']);
        }
        return url(self::RUTA_FOTO_DEFAULT);
    }

    public function ciudad()
    {
        return $this->belongsTo(City::class, 'idCiudad');
    }

    public function empresa()
    {
        return $this->belongsTo(Company::class, 'idEmpresa');
    }
    public function sedes()
    {
        return $this->hasMany(Sede::class, 'idCentroFormacion');
    }
    public function usuario()
    {
        return $this->hasOne(User::class, 'idCentroFormacion');
    }
}
