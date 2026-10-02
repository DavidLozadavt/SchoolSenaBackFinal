<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FormularioRespuesta extends Model
{
    use HasFactory;

    protected $table = 'formulario_respuestas';

    protected $guarded = ['id'];

    protected $casts = [
        'respuestas' => 'array',
    ];

    public function formulario()
    {
        return $this->belongsTo(Formulario::class, 'idFormulario');
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'idUser');
    }
}
