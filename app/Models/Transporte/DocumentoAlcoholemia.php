<?php

namespace App\Models\Transporte;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentoAlcoholemia extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $table = 'documentosAlcoholemia';

    protected $appends = ['documentoUrl'];

    public function getDocumentoUrlAttribute(): string|null
    {
        if (isset($this->attributes['documento'])) {
            return url('storage/' . $this->attributes['documento']);
        }
        return null;
    }

    public function viaje(): BelongsTo
    {
        return $this->belongsTo(Viaje::class, 'idViaje');
    }
}
