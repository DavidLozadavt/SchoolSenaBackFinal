<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ActividadProyecto extends Model
{
    use HasFactory;

    protected $table = 'actividadesProyecto';

    protected $fillable = [
        'descripcionActividad',
        'idFaseProyecto',
    ];

    public function faseProyecto(): BelongsTo
    {
        return $this->belongsTo(FaseProyecto::class, 'idFaseProyecto');
    }
    public function faseProyectoRaps(): HasMany
    {
        return $this->hasMany(FaseProyectoRap::class, 'idActividadProyecto');
    }
}