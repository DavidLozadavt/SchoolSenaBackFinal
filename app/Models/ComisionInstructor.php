<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ComisionInstructor extends Model
{
    use HasFactory;

    protected $table = 'comisionesInstructores';

    protected $fillable = [
        'numeroViaje',
        'lugarDesplazamiento',
        'fechaInicialDesplazamiento',
        'fechaFinalDesplazamiento',
        'idContrato',
        'idRmi',
        'item'
    ];

    protected $casts = [
        'fechaInicialDesplazamiento' => 'date',
        'fechaFinalDesplazamiento' => 'date',
    ];

    public function contrato()
    {
        return $this->belongsTo(Contract::class, 'idContrato');
    }

    public function rmi()
    {
        return $this->belongsTo(Rmi::class, 'idRmi');
    }
}
