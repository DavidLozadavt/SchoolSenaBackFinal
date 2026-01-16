<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EstadoSolicitud extends Model
{
    use HasFactory;


    protected $fillable = [
        "observacion",
        'idDistribucionProducto',
        'estado',
        'fechaInicial',
        'fechaFinal',
        'idResponsableOrigen',
        'idResponsableDestino',

    ];

    protected $table = 'estadoSolicitud';
    public static $snakeAttributes = false;


    public function distribucionProducto()
    {
        return $this->belongsTo(DistribucionProducto::class, 'idDistribucionProducto');
    }
}
