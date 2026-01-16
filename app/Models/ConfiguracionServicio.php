<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;


class ConfiguracionServicio extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $table = 'configuracionServicio';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'idUser');
    }

    public function catalogoServicios(): HasMany
    {
        return $this->hasMany(CatalogoServicio::class, 'idConfiguracionServicios');
    }


    public function servicio(): BelongsTo
    {
        return $this->belongsTo(Servicio::class, 'idServicio');
    }


    public function calificacionServicios(): HasMany
    {
        return $this->hasMany(CalificacionServicio::class, 'idConfiguracionServicio');
    }


    public function getPromedioCalificacionesAttribute()
    {
        $promedio = $this->calificacionServicios->avg('calificacion');
        return $promedio ? round($promedio, 1) : null;
    }
}
