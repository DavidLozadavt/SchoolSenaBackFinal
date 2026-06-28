<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PersonaEduexce extends Model
{
    protected $table = 'persona_eduexce';

    protected $fillable = [
        'id_persona',
        'id_empresa',
        'external_school_id',
        'id_usuario_eduexce',
        'estado',
        'ultimo_error',
        'habilitado_at',
        'last_sync_at',
    ];

    protected $casts = [
        'habilitado_at' => 'datetime',
        'last_sync_at' => 'datetime',
    ];

    public function persona(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'id_persona');
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'id_empresa');
    }
}
