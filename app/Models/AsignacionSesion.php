<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AsignacionSesion extends Model
{
    use HasFactory;

    protected $table = 'reemplazo';

    public $timestamps = true;

    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';

    protected $fillable = [
        'fechaInicial',
        'fechaFinal',
        'fechaInicio',
        'fechaFin',
        'idContratoRemplazo',
        'idContrato',
        'idContratoTrabajador',
        'idHorarioMateria',
        'observacion',
        'estado',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope('horario', function (Builder $query) {
            $query->whereNotNull('idHorarioMateria');
        });
    }

    public function getIdContratoAttribute(): ?int
    {
        $value = $this->attributes['idContratoRemplazo'] ?? null;

        return $value !== null ? (int) $value : null;
    }

    public function setIdContratoAttribute($value): void
    {
        $this->attributes['idContratoRemplazo'] = $value;
    }

    public function getFechaInicioAttribute(): ?string
    {
        return $this->attributes['fechaInicial'] ?? null;
    }

    public function setFechaInicioAttribute($value): void
    {
        $this->attributes['fechaInicial'] = $value;
    }

    public function getFechaFinAttribute(): ?string
    {
        return $this->attributes['fechaFinal'] ?? null;
    }

    public function setFechaFinAttribute($value): void
    {
        $this->attributes['fechaFinal'] = $value;
    }

    public function getTipoAsignacionAttribute(): string
    {
        return 'REEMPLAZO';
    }

    public function contrato(): BelongsTo
    {
        return $this->belongsTo(Contract::class, 'idContratoRemplazo', 'id');
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
            'tipoAsignacion'   => 'REEMPLAZO',
            'fechaInicio'      => $this->fechaInicio,
            'fechaFin'         => $this->fechaFin,
            'observacion'      => $this->observacion,
            'estado'           => $this->estado,
            'contrato'         => $this->relationLoaded('contrato')
                ? $this->contrato
                : null,
        ];
    }
}
