<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AsignacionEscenarioServicio extends Model
{
    use HasFactory;

    protected $table = 'asignacionEscenarioServicio';

    protected $fillable = [
        'idServicio',
        'idEscenario'
    ];

    public function servicio()
    {
        return $this->belongsTo(Servicio::class, 'idServicio');
    }

    public function escenario()
    {
        return $this->belongsTo(Escenario::class, 'idEscenario');
    }
}
