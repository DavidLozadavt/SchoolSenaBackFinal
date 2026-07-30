<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HistorialHorarioMateria extends Model
{
    protected $table = 'historialHorarioMateria';

    protected $fillable = [
        'idHorarioMateria',
        'tipoAccion',
        'fechaFinalAnterior',
        'fechaFinalNueva',
        'idUsuario',
        'observacion',
    ];

    protected $casts = [
        'fechaFinalAnterior' => 'date',
        'fechaFinalNueva' => 'date',
    ];

    public function horarioMateria(): BelongsTo
    {
        return $this->belongsTo(HorarioMateria::class, 'idHorarioMateria');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'idUsuario');
    }
}
