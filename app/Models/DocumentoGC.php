<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentoGC extends Model
{
    protected $table = 'documentoGC';

    protected $fillable = [
        'idGC',
        'nombreDocumento',
        'estado',
        'observacion',
        'urlDocumento',
    ];

    protected $casts = [
        'idGC' => 'integer',
        'nombreDocumento' => 'string',
        'estado' => 'string',
        'observacion' => 'string',
        'urlDocumento' => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $appends = ['urlDocumentoUrl'];

    // 🔗 Relaciones

    public function gc(): BelongsTo
    {
        return $this->belongsTo(GC::class, 'idGC', 'id');
    }

    // 🌐 Accessors de URL

    public function getUrlDocumentoUrlAttribute(): ?string
    {
        if (!empty($this->attributes['urlDocumento'])) {
            return url($this->attributes['urlDocumento']);
        }
        return null;
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