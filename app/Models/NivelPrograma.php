<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NivelPrograma extends Model
{
    protected $table = 'nivelPrograma';

    protected $fillable = [
        'idPrograma',
        'nombre',
        'tipoNivel',
        'orden',
    ];

    public function programa(): BelongsTo
    {
        return $this->belongsTo(Programa::class, 'idPrograma');
    }
}
