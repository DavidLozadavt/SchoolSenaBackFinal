<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TipoPregunta extends Model
{
    protected $table = 'tipo_preguntas';

    public $timestamps = false;

    protected $fillable = ['tipoPregunta'];
}
