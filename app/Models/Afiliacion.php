<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Afiliacion extends Model
{
    use HasFactory;

    protected $table = "afiliacion";



    public function vehiculo()
    {
        return $this->belongsToMany(Vehiculo::class, 'asignacionPropietario', 'idAfiliacion', 'idVehiculo')
            ->distinct();
    }


    public function conductor()
    {
        return $this->belongsToMany(Person::class, 'asignacionConductor', 'idAfiliacion', 'idConductor');
    }

    public function propietario()
    {
        return $this->belongsToMany(Person::class, 'asignacionPropietario', 'idAfiliacion', 'idPropietario')->withPivot('porcentaje', 'administrador');
    }

    public function afiliacionEstado()
    {
        return $this->hasOne(AfiliacionEstado::class, 'idAfiliacion');
    }

    public function tipoAfiliacion()
    {
        return $this->belongsToMany(TipoAfiliacion::class, 'asignacionTipoAfiliacion', 'idAfiliacion', 'idTipoAfiliacion');
    }
}
