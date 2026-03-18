<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Asistencia extends Model
{
    use HasFactory;

    protected $guarded = ['id'];
    protected $table = 'asistencia';

    protected $casts = [
        'horaLLegada' => 'datetime',
        'asistio' => 'boolean',
    ];

    public function sesionMateria(): BelongsTo
    {
        return $this->belongsTo(SesionMateria::class, 'idSesionMateria');
    }

    public function matriculaAcademica(): BelongsTo
    {
        return $this->belongsTo(MatriculaAcademica::class, 'idMatriculaAcademica');
    }

    public function justificacion(): HasOne
    {
        return $this->hasOne(JustificacionInasistencia::class, 'idAsistencia');
    }
}