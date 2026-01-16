<?php

namespace App\Models\Transporte;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ObservacionViaje extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $table = 'observacionViajes';

    public function viaje(): BelongsTo
    {
        return $this->belongsTo(Viaje::class, 'idViaje');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'idUser');
    }

}
