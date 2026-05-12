<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AsignacionCategoriaFormacionContrato extends Model
{
    use HasFactory;

    protected $table = 'asignacionCategoriaFormacionContratos';

    public function contrato()
    {
        return $this->belongsTo(Contract::class, 'idContrato');
    }

    public function categoriaFormacion()
    {
        return $this->belongsTo(CategoriaFormacion::class, 'idCategoriaFormacion');
    }
}
