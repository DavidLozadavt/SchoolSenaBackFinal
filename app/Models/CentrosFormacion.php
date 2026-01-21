<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CentrosFormacion extends Model
{
    use HasFactory;

    protected $table = 'centrosformacion';

    protected $fillable = [
        'nombre',
        'direccion',
        'telefono',
        'correo',
        'subdirector',
        'correosubdirector',
        'idCiudad',
        'idEmpresa'
    ];

    public function ciudad()
    {
        return $this->belongsTo(City::class, 'idCiudad');
    }

    public function empresa()
    {
        return $this->belongsTo(Company::class, 'idEmpresa');
    }
}
