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
