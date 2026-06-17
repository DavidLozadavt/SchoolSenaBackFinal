<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FaseProyectoRap extends Model
{
    protected $table = 'faseProyectoRap';

    protected $fillable = [
        'idFaseProyecto',
        'idMateria',
        'idActividadProyecto',
    ];

    public function fase(): BelongsTo
    {
        return $this->belongsTo(FaseProyecto::class, 'idFaseProyecto');
    }

    public function materia(): BelongsTo
    {
        return $this->belongsTo(Materia::class, 'idMateria');
    }
    // Relación con ActividadProyecto
    public function actividadProyecto(): BelongsTo
    {
        return $this->belongsTo(ActividadProyecto::class, 'idActividadProyecto');
    }
}