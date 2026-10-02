<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NovedadesAprendiz extends Model
{
    use HasFactory;

    protected $table = 'novedadesAprendiz';
    public static $snakeAttributes = false;

    protected $fillable = [
        'idusuario',
        'idmatricula',
        'estado',
        'cambio',
        'observacion'
    ];

    public function usuario()
    {
        return $this->belongsTo(User::class, 'idusuario');
    }

    public function matricula()
    {
        return $this->belongsTo(Matricula::class, 'idmatricula');
    }
}
