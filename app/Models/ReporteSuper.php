<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReporteSuper extends Model
{
    use HasFactory;

    protected $table = "reporteSuper";


    public function responsable()
    {
        return $this->belongsTo(User::class, 'idUsuario');
    }


    public function vehiculoAux()
    {
        return $this->belongsTo(VehiculoAuxiliar::class, 'idVehiculo');
    }



    public function personaAux()
    {
        return $this->belongsTo(PersonaAuxiliar::class, 'idPersona');
    }


    public function empresa()
    {
        return $this->belongsTo(Company::class, 'idEmpresa');
    }
}
