<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
        'idTipoJornada'
    ];

    /**
     * Una jornada pertenece a un tipo de jornada
     */
    public function tipoJornada()
    {
        return $this->belongsTo(TipoJornada::class, 'idTipoJornada');
    }

    /**
     * Una jornada pertenece a un centro de formación
     */
    public function centroFormacion()
    {
        return $this->belongsTo(CentrosFormacion::class, 'idCentroFormacion');
    }

    /**
     * Una jornada tiene muchos días asignados
     */
    public function dias()
    {
        return $this->belongsToMany(Dia::class, 'asignacionDiaJornada', 'idJornada', 'idDia');
    }
}
