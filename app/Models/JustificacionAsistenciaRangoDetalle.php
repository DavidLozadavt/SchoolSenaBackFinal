<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JustificacionAsistenciaRangoDetalle extends Model
{
    use HasFactory;

    protected $table = 'justificacion_asistencia_rango_detalles';

    protected $guarded = ['id'];

    public function rango()
    {
        return $this->belongsTo(JustificacionAsistenciaRango::class, 'idJustificacionAsistenciaRango');
    }

    public function horarioMateria()
    {
        return $this->belongsTo(HorarioMateria::class, 'idHorarioMateria');
    }

    public function ficha()
    {
        return $this->belongsTo(Ficha::class, 'idFicha');
    }

    public function personaInstructor()
    {
        return $this->belongsTo(Person::class, 'idPersonaInstructor');
    }

    public function contratoInstructor()
    {
        return $this->belongsTo(Contract::class, 'idContratoInstructor');
    }

    public function personaAutoriza()
    {
        return $this->belongsTo(Person::class, 'idPersonaAutoriza');
    }
}
