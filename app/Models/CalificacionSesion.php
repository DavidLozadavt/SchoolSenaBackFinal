<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CalificacionSesion extends Model
{
    use HasFactory;

    protected $table = 'calificacionSesiones';

    protected $fillable = [
        'idSesionMateria',
        'idMatriculaAcademica',
        'estrellas',
        'comentarios',
    ];

    public function sesionMateria(): BelongsTo
    {
        return $this->belongsTo(SesionMateria::class, 'idSesionMateria', 'id');
    }

    public function matriculaAcademica(): BelongsTo
    {
        return $this->belongsTo(MatriculaAcademica::class, 'idMatriculaAcademica', 'id');
    }
}
