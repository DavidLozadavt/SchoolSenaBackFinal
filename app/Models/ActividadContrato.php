<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ActividadContrato extends Model
{
    use HasFactory;

    protected $table = 'actividadesContrato';

    protected $fillable = [
        'obligaciones',
        'accionesRealizadas',
        'evidencias',
        'idContrato'
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Relación con Contrato
     */
    public function contrato()
    {
        return $this->belongsTo(Contract::class, 'idContrato', 'id');
    }
}