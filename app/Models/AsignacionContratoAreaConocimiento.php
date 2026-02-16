<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AsignacionContratoAreaConocimiento extends Model
{
    use HasFactory;

    protected $table = "asignacion_contrato_area_conocimiento";

    public function areaConocimiento()
    {
        return $this->belongsTo(AreaConocimiento::class, 'idAreaConcimiento');
    }

    public function contrato()
    {
        return $this->belongsTo(Contract::class, 'idContrato');
    }
}
