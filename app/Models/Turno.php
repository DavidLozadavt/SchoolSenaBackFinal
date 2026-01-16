<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Turno extends Model
{
    use HasFactory;

    public static $snakeAttributes = false;
    protected $table = "turnos";
     public $timestamps = false;

    protected $guarded = ['id'];

    public function conductor()
    {
        return $this->belongsTo(Person::class, 'idConductor');
    }

    public function vehiculo()
    {
        return $this->belongsTo(Vehiculo::class, 'idVehiculo');
    }

}
