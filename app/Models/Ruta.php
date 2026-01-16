<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Ruta extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    public function ciudadOrigen(): BelongsTo
    {
        return $this->belongsTo(City::class, 'idCiudadOrigen');
    }

    public function ciudadDestino(): BelongsTo
    {
        return $this->belongsTo(City::class, 'idCiudadDestino');
    }

    public function rutaVuelta(): BelongsTo
    {
        return $this->belongsTo(Ruta::class, 'idRutaVuelta');
    }

    public function rutaIda(): HasOne
    {
        return $this->hasOne(Ruta::class, 'idRutaVuelta');
    }

    public function rutaPadre(): BelongsTo
    {
        return $this->belongsTo(Ruta::class, 'idRutaPadre');
    }

    public function rutasHijas(): HasMany
    {
        return $this->hasMany(Ruta::class, 'idRutaPadre')->with('lugar');

    }
    public function lugar(): BelongsTo
    {
        return $this->belongsTo(Lugar::class, 'idLugar');
    }
    


}
