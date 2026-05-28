<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AsignacionSesion extends Model
{
    use HasFactory;

    protected $table = 'asignacionsesion';

    public $timestamps = false;

    protected $fillable = [
        'idContrato',
        'tipoAsignacion',
        'fechaInicio',
        'fechaFin',
        'idHorarioMateria',
        'observacion',
    ];

    public function contrato(): BelongsTo
    {
        return $this->belongsTo(Contract::class, 'idContrato', 'id');
    }

    public function horario(): BelongsTo
    {
        return $this->belongsTo(HorarioMateria::class, 'idHorarioMateria', 'id');
    }

    public function toAsignacionSesionApi(): array
    {
        return [
            'id'               => $this->id,
            'idHorarioMateria' => $this->idHorarioMateria,
            'idContrato'       => $this->idContrato,
            'tipoAsignacion'   => $this->tipoAsignacion,
            'fechaInicio'      => $this->fechaInicio,
            'fechaFin'         => $this->fechaFin,
            'observacion'      => $this->observacion,
            'contrato'         => $this->relationLoaded('contrato')
                ? $this->contrato
                : null,
        ];
    }
}
