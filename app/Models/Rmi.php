<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// App/Models/Rmi.php
class Rmi extends Model
{
    protected $table = 'rmi';

    protected $fillable = ['estado', 'periodo', 'observacion'];

    public function detalles()
    {
        return $this->hasMany(DetalleRmi::class, 'idRmi');
    }

    public function horarioMaterias()
    {
        return $this->belongsToMany(
            HorarioMateria::class,
            'detalleRmi',
            'idRmi',
            'idHorarioMateria'
        );
    }
}
