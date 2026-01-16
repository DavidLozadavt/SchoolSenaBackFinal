<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TipoGrado extends Model
{
    protected $table = 'tipoGrado';

    protected $fillable = ['nombreTipoGrado'];

    /**
     * Atributos que deben ocultarse para las serializaciones JSON.
     */
    protected $hidden = [
        'created_at',
        'updated_at',
    ];
}