<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AsignacionPropietario extends Model
{
    use HasFactory;

    protected $table = "asignacionPropietario";

    public function afiliacion(): BelongsTo
    {
        return $this->belongsTo(Afiliacion::class, 'idAfiliacion');
    }

    public function propietario(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'idPropietario');
    }

    public function vehiculo(): BelongsTo
    {
        return $this->belongsTo(Vehiculo::class, 'idVehiculo');
    }
}
