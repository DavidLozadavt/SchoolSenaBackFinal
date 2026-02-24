<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MatriculaAcademica extends Model
{
    use HasFactory;

    protected $guarded = ['id'];
    protected $table = 'matriculaAcademica';

    public function ficha(): BelongsTo
    {
        return $this->belongsTo(Ficha::class, 'idFicha');
    }

    public function matricula(): BelongsTo
    {
        return $this->belongsTo(Matricula::class, 'idMatricula');
    }

    public function materia(): BelongsTo
    {
        return $this->belongsTo(Materia::class, 'idMateria');
    }

    public function evaluador(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'idEvaluador');
    }

    public function asistencias(): HasMany
    {
        return $this->hasMany(Asistencia::class, 'idMatriculaAcademica');
    }
}