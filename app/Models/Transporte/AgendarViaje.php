<?php

namespace App\Models\Transporte;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AgendarViaje extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $table = 'agendarViajes';

    public function viaje(): BelongsTo
    {
        return $this->belongsTo(Viaje::class, 'idViaje');
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class,'idAgendaViaje');
    }

}
