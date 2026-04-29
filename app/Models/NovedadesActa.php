<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NovedadesActa extends Model
{
    use HasFactory;

    protected $table = 'novedadesActa';

    protected $fillable = [
        'idacta',
        'idmatriculaAcademica',
        'observacion',
    ];

    protected $casts = [
        'idacta' => 'integer',
        'idmatriculaAcademica' => 'integer',
        'observacion' => 'string',
    ];

    // Relaciones
    public function acta()
    {
        return $this->belongsTo(Acta::class, 'idacta');
    }

    public function matriculaAcademica()
    {
        return $this->belongsTo(MatriculaAcademica::class, 'idmatriculaAcademica');
    }
}