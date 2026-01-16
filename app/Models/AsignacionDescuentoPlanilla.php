<?php

namespace App\Models;

use App\Models\Transporte\Viaje;
use Illuminate\Database\Eloquent\Model;

class AsignacionDescuentoPlanilla extends Model
{
    protected $table = 'asignacionDescuentoPlanilla';

    protected $guarded = [
        'id',
        'created_at',
        'updated_at'
    ];

    protected $casts = [
        'idDescuento' => 'integer',
        'idViaje' => 'integer',
        'idTaquillero' => 'integer',
        'fecha' => 'datetime'
    ];

    public function descuento()
    {
        return $this->belongsTo(DescuentoPlanilla::class, 'idDescuento');
    }

    public function viaje()
    {
        return $this->belongsTo(Viaje::class, 'idViaje');
    }

    public function taquillero()
    {
        return $this->belongsTo(User::class, 'idTaquillero');
    }
}