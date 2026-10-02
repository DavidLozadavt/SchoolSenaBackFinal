<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FormularioOpcion extends Model
{
    use HasFactory;

    protected $table = 'formulario_opciones';

    protected $guarded = ['id'];

    public function pregunta()
    {
        return $this->belongsTo(FormularioPregunta::class, 'idFormularioPregunta');
    }
}
