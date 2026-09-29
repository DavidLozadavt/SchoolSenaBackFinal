<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Portafolio extends Model
{
    use HasFactory;

    protected $table = 'portafolio';

    protected $fillable = [
        'descripcion',
        'idContrato',
    ];

    public function contrato()
    {
        return $this->belongsTo(Contract::class, 'idContrato');
    }

    public function portafolioFichas()
    {
        return $this->hasMany(PortafolioFicha::class, 'idPortafolio');
    }
}