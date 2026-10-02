<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FormularioPregunta extends Model
{
    use HasFactory;

    protected $table = 'formulario_preguntas';

    protected $guarded = ['id'];

    protected $casts = [
        'esObligatoria' => 'boolean',
        'configuracion' => 'array',
    ];

    public function formulario()
    {
        return $this->belongsTo(Formulario::class, 'idFormulario');
    }

    public function opciones()
    {
        return $this->hasMany(FormularioOpcion::class, 'idFormularioPregunta')->orderBy('orden');
    }
}
