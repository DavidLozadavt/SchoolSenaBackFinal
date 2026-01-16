<?php

namespace App\Models\Nomina;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Contracts\Database\Eloquent\Builder;

class SolicitudVacacion extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $table = 'solicitudVacaciones';

    public function vacaciones(): HasMany
    {
        return $this->hasMany(Vacacion::class, 'idSolicitud');
    }

    public function observaciones(): HasMany
    {
        return $this->hasMany(ObservacionSolicitudVacacion::class, 'idSolicitud');
    }

    /**
     * Filter data request vacations
     * @param \Illuminate\Contracts\Database\Eloquent\Builder $query
     * @param string $search
     * @param string|int $fechaSolicitud
     * @param string $fechaLiquidacion
     * @param string $fechaEjecucion
     * @param string $estado
     * @param string $periodos
     * @param string $numDias
     * @param string $valor
     * @param string $fechaFinal
     * @return Builder|\Illuminate\Database\Eloquent\Builder
     */
    public function scopeFilterAdvanceSolicitudVacacion(
        Builder $query,
        string $search = null,
        string|int $fechaSolicitud = null,
        string $fechaLiquidacion = null,
        string $fechaEjecucion = null,
        string $estado = null,
        string $periodos = null,
        string $numDias = null,
        string $valor = null,
        string $fechaFinal = null
    ): Builder {
        return $query
            ->when($search, function ($q) use ($search) {
                $q->whereHas('vacaciones.contrato.persona', function ($query) use ($search) {
                    $query->where(function ($subQuery) use ($search) {
                        $subQuery->whereRaw("CONCAT(persona.nombre1, ' ', COALESCE(persona.apellido1, ''), ' ', persona.email) LIKE ?", ["%{$search}%"])
                            ->orWhere('persona.email', 'like', "%{$search}%")
                            ->orWhere('persona.nombre1', 'like', "%{$search}%")
                            ->orWhere('persona.apellido1', 'like', "%{$search}%")
                            ->orWhere('persona.identificacion', 'like', "%{$search}%"); // 🔥 Se agregó identificación
                    });
                });
            })
            ->when($fechaSolicitud, fn($q) => $q->where('fechaSolicitud', $fechaSolicitud))
            ->when($fechaLiquidacion, fn($q) => $q->where('fechaLiquidacion', $fechaLiquidacion))
            ->when($fechaEjecucion, fn($q) => $q->where('fechaEjecucion', $fechaEjecucion))
            ->when($estado, fn($q) => $q->where('estado', $estado))
            ->when($periodos, fn($q) => $q->where('periodos', $periodos))
            ->when($numDias, fn($q) => $q->where('numDias', $numDias))
            ->when($valor, fn($q) => $q->where('valor', $valor))
            ->when($fechaFinal, fn($q) => $q->where('fechaFinal', $fechaFinal));
    }
}
