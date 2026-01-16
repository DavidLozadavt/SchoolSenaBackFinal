<?php

namespace App\Models;

use App\Models\Transporte\ConfiguracionVehiculo;
use App\Models\Transporte\Viaje;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Vehiculo extends Model
{
    use HasFactory;

    const ATTACHMENT_DEFAULT = "/default/auto.png";
    protected $guarded = ['id'];
    protected $table = "vehiculo";
    protected $appends = ['rutaUrl'];

    public function getRutaUrlAttribute()
    {
        if (
            isset($this->attributes['foto']) &&
            isset($this->attributes['foto'][0])
        ) {
            return url($this->attributes['foto']);
        }
        return url(self::ATTACHMENT_DEFAULT);
    }


    public function modelo(): BelongsTo
    {
        return $this->belongsTo(Modelo::class, 'idModelo');
    }

    public function marca(): BelongsTo
    {
        return $this->belongsTo(Marca::class, 'idMarca');
    }

    public function tipoVehiculo(): BelongsTo
    {
        return $this->belongsTo(TipoVehiculo::class, 'idTipo');
    }

    public function claseVehiculo(): BelongsTo
    {
        return $this->belongsTo(ClaseVehiculo::class, 'idClaseVehiculo');
    }

    public function viajes(): HasMany
    {
        return $this->hasMany(Viaje::class, 'idVehiculo');
    }

    public function estado(): BelongsTo
    {
        return $this->belongsTo(Status::class, 'idEstado');
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'idEmpresa');
    }

    public function asignacionPropietarios(): HasMany
    {
        return $this->hasMany(AsignacionPropietario::class, 'idVehiculo');
    }

    public function asignacionPropietario(): HasOne
    {
        return $this->hasOne(AsignacionPropietario::class, 'idVehiculo');
    }

    public function configuracionVehiculo(): HasOne
    {
        return $this->hasOne(ConfiguracionVehiculo::class, 'idVehiculo');
    }

    public function asignacionConductor(): HasOne
    {
        return $this->hasOne(AsignacionConductor::class, 'idVehiculo');
    }


    public function documentosVehiculo()
    {
        return $this->hasMany(DocumentoVehiculo::class, 'idVehiculo');
    }

    // public function marca()
    // {
    //     return $this->belongsTo(Marca::class, 'idMarca');
    // }

    // public function estado()
    // {
    //     return $this->belongsTo(Status::class, 'idEstado');
    // }

    
        public function revisionReciente()
        {
            return $this->hasOne(AsignacionDetalleRevisionVehiculo::class, 'idVehiculo')
                ->whereIn('estado', ['ACTIVO', 'PORREVISION'])
                ->where(function ($q) {
                    $q->where('fechaLimite', '>=', now())
                    ->orWhere('fechaRevision', '>=', now()->subDays(15));
                })
                ->latest('fechaRevision');
        }



}
