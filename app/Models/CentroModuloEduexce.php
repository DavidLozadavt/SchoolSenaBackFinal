<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CentroModuloEduexce extends Model
{
    protected $table = 'centro_modulo_eduexce';

    protected $fillable = [
        'id_empresa',
        'id_centro_formacion',
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

    public function centroFormacion(): BelongsTo
    {
        return $this->belongsTo(CentrosFormacion::class, 'id_centro_formacion');
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
