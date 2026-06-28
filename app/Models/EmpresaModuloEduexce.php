<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmpresaModuloEduexce extends Model
{
    protected $table = 'empresa_modulo_eduexce';

    protected $fillable = [
        'id_empresa',
        'id_institucion_eduexce',
        'activo',
        'fecha_vigencia_fin',
        'provisionado_at',
        'ultimo_error',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'fecha_vigencia_fin' => 'date',
        'provisionado_at' => 'datetime',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'id_empresa');
    }

    public function licenciaVigente(): bool
    {
        if (!$this->activo) {
            return false;
        }
        if ($this->fecha_vigencia_fin && $this->fecha_vigencia_fin->isPast()) {
            return false;
        }
        return true;
    }
}
