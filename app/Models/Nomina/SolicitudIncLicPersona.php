<?php

namespace App\Models\Nomina;

use App\Models\Contract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Contracts\Database\Eloquent\Builder;

class SolicitudIncLicPersona extends Model
{
    use HasFactory;

    protected $guarded = ['id'];
    protected $table = 'solicitudIncLicPersonas';

    const RUTA_FOTO_DEFAULT = "default/sede.png";

    protected $appends = ['rutaSoporte'];

    public function getRutaSoporteAttribute()
    {
        $defaultUrl = url('storage/' . self::RUTA_FOTO_DEFAULT);

        if (isset($this->attributes['urlSoporte'])) {
            if ($this->attributes['urlSoporte'] === self::RUTA_FOTO_DEFAULT) {
                return url($this->attributes['urlSoporte']);
            }

            return url('storage/' . $this->attributes['urlSoporte']);
        }

        return $defaultUrl;
    }

    public function contrato(): BelongsTo
    {
        return $this->belongsTo(Contract::class, 'idContrato');
    }

    public function observaciones(): HasMany
    {
        return $this->hasMany(ObservacionSolicitudIncLicPer::class, 'idSolicitud');
    }

    public function tipoIncapacidad(): BelongsTo
    {
        return $this->belongsTo(TipoIncapacidad::class, 'idTipoIncapacidad');
    }

    public function scopeFilterAdvanceSolicitudIncLic(
        Builder $query,
        string $search = null,
        string $fechaSolicitud = null,
        string $fechaInicial = null,
        string $fechaFinal = null,
        string $idTipoIncapacidad = null,
        string|int $numDias = null,
        string|int $valor = null,
        string $estado = null,
    ): Builder {
        return $query
            ->when($search, function ($q) use ($search) {
                $q->whereHas('contrato.persona', function ($query) use ($search) {
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
            ->when($fechaInicial, fn($q) => $q->where('fechaInicial', $fechaInicial))
            ->when($fechaFinal, fn($q) => $q->where('fechaFinal', $fechaFinal))
            ->when($idTipoIncapacidad, fn($q) => $q->where('idTipoIncapacidad', $idTipoIncapacidad))
            ->when($numDias, fn($q) => $q->where('numDias', $numDias))
            ->when($valor, fn($q) => $q->where('valor', $valor))
            ->when($estado, fn($q) => $q->where('estado', $estado));
    }


    const ACEPTADO = 'ACEPTADO';
    const PENDIENTE = 'PENDIENTE';
    const RECHAZADO = 'RECHAZADO';
    const FINALIZADO = 'FINALIZADO';
}
