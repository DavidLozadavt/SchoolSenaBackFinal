<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HorarioCompartido extends Model
{
    use HasFactory;

    protected $table = 'horarioCompartido';

    protected $fillable = [
        'idHorarioMateria',
        'idHorarioMateriaSecundario',
        'idContratoSecundario',
        'fechaInicial',
        'fechaFinal',
        'observacion',
        'estado',
    ];

    public function horarioBase(): BelongsTo
    {
        return $this->belongsTo(HorarioMateria::class, 'idHorarioMateria', 'id');
    }

    public function horarioSecundario(): BelongsTo
    {
        return $this->belongsTo(HorarioMateria::class, 'idHorarioMateriaSecundario', 'id');
    }

    public function contratoSecundario(): BelongsTo
    {
        return $this->belongsTo(Contract::class, 'idContratoSecundario', 'id');
    }

    public function toAsignacionSesionApi(): array
    {
        return [
            'id'               => $this->id,
            'idHorarioMateria' => $this->idHorarioMateriaSecundario ?? $this->idHorarioMateria,
            'idContrato'       => $this->idContratoSecundario,
            'tipoAsignacion'   => 'HORARIO COMPARTIDO',
            'fechaInicio'      => $this->fechaInicial,
            'fechaFin'         => $this->fechaFinal,
            'observacion'      => $this->observacion,
            'estado'           => $this->estado,
            'contrato'         => $this->relationLoaded('contratoSecundario')
                ? $this->contratoSecundario
                : null,
        ];
    }
}
