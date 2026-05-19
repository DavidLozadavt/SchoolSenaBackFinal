<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SolicitudMateria extends Model
{
    use HasFactory;

    protected $table = 'solicitudMateria';

    protected $fillable = [
        'observacion',
        'idMateria',
        'idFicha',
        'idContrato',
        'fechaInicio',
        'fechaFin',
        'estado',
        'idCompany',
        'idSolicitante'
    ];

    public function materia()
    {
        return $this->belongsTo(Materia::class, 'idMateria');
    }

    public function contrato()
    {
        return $this->belongsTo(Contract::class, 'idContrato');
    }

    public function ficha(){
        return $this->belongsTo(Ficha::class, 'idFicha');
    }
    
    public function solicitante(){
        return $this->belongsTo(Contract::class, 'idSolicitante');
    }
}
