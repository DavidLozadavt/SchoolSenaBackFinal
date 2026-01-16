<?php

namespace App\Models\Transporte;

use App\Models\Contract;
use App\Models\Ruta;
use App\Models\Vehiculo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Viaje extends Model
{
    use HasFactory;

    protected $guarded = ['id'];



    public function ruta(): BelongsTo
    {
        return $this->belongsTo(Ruta::class, 'idRuta');
    }

    public function conductor(): BelongsTo
    {
        return $this->belongsTo(Contract::class, 'idConductor');
    }
    public function conductorAuxiliar(): BelongsTo
    {
        return $this->belongsTo(Contract::class, 'idConductorAuxiliar');
    }
    public function vehiculo(): BelongsTo
    {
        return $this->belongsTo(Vehiculo::class, 'idVehiculo');
    }

    public function observaciones(): HasMany
    {
        return $this->hasMany(ObservacionViaje::class, 'idViaje');
    }

    public function documentosAlcoholemia(): HasMany
    {
        return $this->hasMany(DocumentoAlcoholemia::class, 'idViaje');
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'idViaje');
    }

    // public function agendarViajes(): HasMany
    // {
    //     return $this->hasMany(AgendarViaje::class, 'idViaje');
    // }
    public function agendarViajes()
    {
        return $this->hasOne(AgendarViaje::class, 'idViaje');
    }
}
