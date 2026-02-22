<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OpcionPregunta extends Model
{
    protected $table = 'opciones_pregunta';

    public $timestamps = false;

    protected $fillable = ['idPregunta', 'texto', 'esCorrecta'];

    protected $casts = ['esCorrecta' => 'boolean'];

    public function pregunta()
    {
        return $this->belongsTo(Pregunta::class, 'idPregunta');
    }
}
