<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Jornada extends Model
{
    use HasFactory;

    protected $table = 'jornadas';

    protected $fillable = [
        'nombreJornada',
        'descripcion',
        'horaInicial',
        'horaFinal',
        'numeroHoras',
        'estado',
        'idCentroFormacion',
        'idTipoJornada',
    ];

    /**
     * Una jornada pertenece a un tipo de jornada
     */
    public function tipoJornada(): BelongsTo
    {
        return $this->belongsTo(TipoJornada::class, 'idTipoJornada');
    }

    public function centroFormacion(): BelongsTo
    {
        return $this->belongsTo(CentrosFormacion::class, 'idCentroFormacion');
    }

    public function dias(): BelongsToMany
    {
        return $this->belongsToMany(Dia::class, 'asignaciondiajornada', 'idJornada', 'idDia');
    }
}
