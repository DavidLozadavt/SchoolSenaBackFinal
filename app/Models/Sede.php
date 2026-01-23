<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Sede extends Model
{
    use HasFactory;

    protected $table = 'sedes';
    public const RUTA_FOTO_DEFAULT = 'sedes/default.png';

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
    ];

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
}
