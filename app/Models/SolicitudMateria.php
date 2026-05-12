<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SolicitudMateria extends Model
{
    use HasFactory;

    public function materia()
    {
        return $this->belongsTo(Materia::class, 'idMateria');
    }

    public function categoriaFormacion()
    {
        return $this->belongsTo(CategoriaFormacion::class, 'idCategoriaFormacion');
    }

    public function contrato()
    {
        return $this->belongsTo(Contract::class, 'idContrato');
    }

    public function ficha(){
        return $this->belongsTo(Ficha::class, 'idFicha');
    }
}
