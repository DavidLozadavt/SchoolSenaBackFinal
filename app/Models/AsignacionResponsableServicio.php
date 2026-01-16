<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AsignacionResponsableServicio extends Model
{
    use HasFactory;
    protected $guarded = [];
    protected $table = "asignacionResponsableServicio";

    // Relación con la tabla agenda
    public function agenda()
    {
        return $this->belongsTo(Agenda::class, 'idAgenda');
    }

    // Relación con la tabla persona (responsable)
    public function responsable()
    {
        return $this->belongsTo(Person::class, 'idResponsable');
    }

    // Relación con la tabla servicios
    public function servicio()
    {
        return $this->belongsTo(Servicio::class, 'idServicio');
    }

    // Relación con la tabla tercero (cliente)
    public function cliente()
    {
        return $this->belongsTo(Tercero::class, 'idCliente');
    }

    public function escenario()
    {
        return $this->belongsTo(Escenario::class, 'idEscenario');
    }

}