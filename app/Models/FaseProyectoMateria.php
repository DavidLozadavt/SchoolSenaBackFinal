<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FaseProyectoMateria extends Model
{
    use HasFactory;

    protected $table = 'faseProyectoMaterias';

    protected $fillable = [
        'idFaseProyectoRap',
        'idMateria',
    ];

    // Relación con FaseProyectoRap
    public function faseProyectoRap()
    {
        return $this->belongsTo(FaseProyectoRap::class, 'idFaseProyectoRap');
    }

    // Relación con Materia
    public function materia()
    {
        return $this->belongsTo(Materia::class, 'idMateria');
    }
}