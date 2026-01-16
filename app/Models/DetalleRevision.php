<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DetalleRevision extends Model
{
    use HasFactory;

    protected $table = 'detalleRevision';

    protected $fillable = [
        'nombre',
        'tipoDetalle',
        'idCompany',
    ];

    protected $casts = [
        'fechaRevision' => 'datetime',
    ];

    /**
     * Relación con Empresa
     */
    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'idCompany');
    }

    /**
     * Relación con AsignacionDetalleRevisionVehiculo
     */
    public function asignaciones(): HasMany
    {
        return $this->hasMany(AsignacionDetalleRevisionVehiculo::class, 'idDetalle');
    }
}