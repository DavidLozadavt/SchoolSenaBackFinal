<?php

namespace App\Models;

use App\Models\Transporte\Viaje;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AsignacionDetalleRevisionVehiculo extends Model
{
    use HasFactory;

    protected $table = 'asignacionDetalleRevisionVehiculo';

    protected $guarded = [];

    protected $casts = [
        'fechaRevision' => 'datetime',
    ];

    /**
     * Relación con DetalleRevision
     */
    public function detalleRevision(): BelongsTo
    {
        return $this->belongsTo(DetalleRevision::class, 'idDetalle');
    }

    /**
     * Relación con Vehiculo
     */
    public function vehiculo(): BelongsTo
    {
        return $this->belongsTo(Vehiculo::class, 'idVehiculo');
    }

    /**
     * Relación con Usuario
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'idUser');
    }

    /**
     * Relación con Viaje
     */
    public function viaje(): BelongsTo
    {
        return $this->belongsTo(Viaje::class, 'idViaje');
    }

    /**
     * Scope para filtrar por estado
     */
    public function scopeActivo($query)
    {
        return $query->where('estado', 'ACTIVO');
    }

    public function scopePendiente($query)
    {
        return $query->where('estado', 'PENDIENTE');
    }

    public function scopeInactivo($query)
    {
        return $query->where('estado', 'INACTIVO');
    }
}