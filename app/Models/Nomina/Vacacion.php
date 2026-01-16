<?php

namespace App\Models\Nomina;

use App\Models\Contract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Vacacion extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = ['id'];

    protected $table = 'vacaciones';

    public function contrato(): BelongsTo
    {
        return $this->belongsTo(Contract::class, 'idContrato');
    }

    public function solicitud(): BelongsTo
    {
        return $this->belongsTo(SolicitudVacacion::class, 'idSolicitud');
    }

    public function scopeFilterAdvanceVacacion(
        \Illuminate\Database\Eloquent\Builder $query,
        string|int $periodo = null,
        string $idSolicitud = null,
        string $idContrato = null,
        string $estado = null
    ): Builder {
        return $query
            ->when($periodo, function ($q) use ($periodo) {
                $q->where('periodo', $periodo);
            })
            ->when(
                $idSolicitud,
                fn($q) => $q->where('idSolicitud', $idSolicitud)
            )
            ->when(
                $idContrato,
                fn($q) => $q->where('idContrato', $idContrato)
            )
            ->when(
                $estado,
                fn($q) => $q->where('estado', $estado)
            );
    }
}
