<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Respuesta extends Model
{
    protected $table = 'respuestas';

    public $timestamps = false;

    protected $fillable = ['descripcionRespuesta', 'chkCorrecta', 'puntaje', 'idPregunta'];

    protected $casts = ['chkCorrecta' => 'boolean'];

    public function pregunta()
    {
        return $this->belongsTo(Pregunta::class, 'idPregunta');
    }
}
