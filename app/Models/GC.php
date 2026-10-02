<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GC extends Model
{
    protected $table = 'gC';

    protected $fillable = [
        'idContrato',
        'idRmi',
        'estado',
        'observacion',
    ];

    protected $casts = [
        'idContrato'  => 'integer',
        'idRmi'       => 'integer',
        'estado'      => 'string',
        'observacion' => 'string',
        'created_at'  => 'datetime',
        'updated_at'  => 'datetime',
    ];

    // 🔗 Relaciones

    public function contrato(): BelongsTo
    {
        return $this->belongsTo(Contract::class, 'idContrato', 'id');
    }

    public function rmi(): BelongsTo
    {
        return $this->belongsTo(Rmi::class, 'idRmi', 'id');
    }

    public function documentosGC(): HasMany
    {
        return $this->hasMany(DocumentoGC::class, 'idGC', 'id');
    }

    // 🔍 Scopes de estado

    public function scopePendiente($query)
    {
        return $query->where('estado', 'PENDIENTE');
    }

    public function scopeAceptado($query)
    {
        return $query->where('estado', 'ACEPTADO');
    }

    public function scopeRechazado($query)
    {
        return $query->where('estado', 'RECHAZADO');
    }
}