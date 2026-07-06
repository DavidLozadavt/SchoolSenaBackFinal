<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PortafolioFicha extends Model
{
    use HasFactory;

    protected $table = 'portafolioFichas';

    protected $fillable = [
        'descripcion',
        'idPortafolio',
        'idFicha',
    ];

    public function portafolio()
    {
        return $this->belongsTo(Portafolio::class, 'idPortafolio');
    }

    public function ficha()
    {
        return $this->belongsTo(Ficha::class, 'idFicha');
    }

    public function portafolioDocumentos()
    {
        return $this->hasMany(PortafolioDocumento::class, 'idPortafolioFichas');
    }
}