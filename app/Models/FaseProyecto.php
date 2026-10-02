<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FaseProyecto extends Model
{
    use HasFactory;

    protected $table = 'faseProyecto';

    protected $fillable = [
        'descripcionFase',
        'idProyectoFormativo',
    ];

    public function proyectoFormativo(): BelongsTo
    {
        return $this->belongsTo(ProyectoFormativo::class, 'idProyectoFormativo');
    }
    public function actividades(): HasMany
    {
        return $this->hasMany(ActividadProyecto::class, 'idFaseProyecto');
    }
    public function materias(): BelongsToMany
    {
        return $this->belongsToMany(
            Materia::class,
            'faseProyectoRap',
            'idFaseProyecto',
            'idMateria'
        )->withTimestamps();
    }
}
