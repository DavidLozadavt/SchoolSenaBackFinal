<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlaneacionPedagogicaClase extends Model
{
    protected $table = 'planeacion_pedagogica_clase';

    protected $fillable = [
        'idPlaneacion',
        'idHorarioMateria',
        'idDia',
        'diaNombre',
        'asignatura',
        'horaInicial',
        'horaFinal',
        'tema',
        'aprendizajeEsperado',
        'preguntaProblematizadora',
        'saberesPrevios',
        'estandar',
        'dba',
        'competencia',
        'evidencia',
        'criterios',
        'instrumento',
        'recursos',
        'refuerzo',
        'profundizacion',
        'actividadPractica',
        'secuencia',
        'tallerTitulo',
        'tallerContenido',
        'tallerEstrategia',
        'tallerEntregables',
        'tallerInicio',
        'tallerFin',
        'idActividad',
        'idMateria',
        'ejecutada',
        'orden',
    ];

    protected $casts = [
        'secuencia' => 'array',
        'ejecutada' => 'boolean',
        'tallerInicio' => 'datetime',
        'tallerFin' => 'datetime',
    ];

    public function planeacion(): BelongsTo
    {
        return $this->belongsTo(PlaneacionPedagogica::class, 'idPlaneacion');
    }
}
