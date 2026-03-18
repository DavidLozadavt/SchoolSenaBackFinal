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
        'diaSemana',
        'horaInicial',
        'horaFinal',
        'numeroHoras',
        'estado',
        'grupoJornada',
        'idEmpresa',
        'idTipoJornada',
    ];

    /**
     * Una jornada pertenece a un tipo de jornada
     */
    public function tipoJornada()
    {
        return $this->belongsTo(TipoJornada::class, 'idTipoJornada');
    }

    /**
     * Una jornada pertenece a una empresa (Company)
     */
    public function company()
    {
        return $this->belongsTo(Company::class, 'idEmpresa');
    }
}
