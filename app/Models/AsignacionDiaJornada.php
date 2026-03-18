<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AsignacionDiaJornada extends Model
{
    use HasFactory;

    protected $table = 'asignacionDiaJornada';

    protected $fillable = [
        'idDia',
        'idJornada',
    ];

    public $timestamps = false;

    /**
     * Relación con el modelo Dia
     */
    public function dia()
    {
        return $this->belongsTo(Dia::class, 'idDia');
    }

    /**
     * Relación con el modelo Jornada
     */
    public function jornada()
    {
        return $this->belongsTo(Jornada::class, 'idJornada');
    }
}
