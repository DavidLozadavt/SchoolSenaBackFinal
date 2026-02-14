<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Sede extends Model
{
    use HasFactory;

    protected $table = 'sedes';

    protected $fillable = [
        'nombre',
        'jefeInmediato',
        'descripcion',
        'urlImagen',
        'idEmpresa',
        'idCiudad',
        'direccion',
        'email',
        'telefono',
        'celular',
        'idResponsable',
        'idCentroFormacion'
    ];

    const RUTA_FOTO = "urlImagen";
    const RUTA_FOTO_DEFAULT = "/default/logoweb.png";

    protected $appends = ['rutaFotoUrl'];

    public function getRutaFotoUrlAttribute()
    {
        if (
            isset($this->attributes['urlImagen']) &&
            isset($this->attributes['urlImagen'][0])
        ) {
            return url($this->attributes['urlImagen']);
        }
        return url(self::RUTA_FOTO_DEFAULT);
    }

    /**
     * RELACIONES
     */

    // Sede pertenece a una Empresa
    public function empresa()
    {
        return $this->belongsTo(Company::class, 'idEmpresa');
    }

    // Sede pertenece a una Ciudad (nullable)
    public function ciudad()
    {
        return $this->belongsTo(City::class, 'idCiudad');
    }

    // Responsable (usuario)
    public function responsable()
    {
        return $this->belongsTo(User::class, 'idResponsable');
    }
    // CEntro de formación al que le pertenece esa sede
    public function centroFormacion()
    {
        return $this->belongsTo(CentrosFormacion::class, 'idCentroFormacion');
    }
    public function fichas()
    {
        return $this->hasMany(Ficha::class, 'idSede');
    }
     public function ambientes()
    {
        return $this->hasMany(Infraestructura::class, 'idSede');
    }
}
