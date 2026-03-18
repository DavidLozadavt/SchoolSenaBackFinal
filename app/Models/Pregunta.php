<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Pregunta extends Model
{
    protected $table = 'preguntas';

    public $timestamps = false;

    protected $fillable = ['descripcion', 'puntaje', 'idTipoPregunta', 'idActividad', 'urlDocumento'];

    public function tipoPregunta()
    {
        return $this->belongsTo(TipoPregunta::class, 'idTipoPregunta');
    }

    public function actividad()
    {
        return $this->belongsTo(Actividad::class, 'idActividad');
    }

    public function opciones()
    {
        return $this->hasMany(OpcionPregunta::class, 'idPregunta');
    }

    public function respuestas()
    {
        return $this->hasMany(Respuesta::class, 'idPregunta');
    }
}
