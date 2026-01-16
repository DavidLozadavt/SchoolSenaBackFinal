<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ResponsableServicio extends Model
{
    use HasFactory;

    protected $fillable = [

        'porcentajeGanancia',
        'descripcion', // ya agregado

    ];

    protected $table = "responsableServicio";

    public function persona()
    {
        return $this->belongsTo(Person::class, 'idPersona');
    }

    public function prestaciones()
    {
        return $this->hasMany(PrestacionServicio::class, 'idResponsable');
    }

    public function servicios()
    {
        return $this->belongsToMany(
            Servicio::class,
            'prestador_servicios',
            'responsable_servicio_id',
            'servicio_id'
        )
            ->withPivot('estado') 
            ->withTimestamps();
    }
}
