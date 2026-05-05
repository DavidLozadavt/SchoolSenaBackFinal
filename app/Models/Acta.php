<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Acta extends Model
{
    use HasFactory;

    protected $table = 'acta';

    protected $fillable = [
        'nombre',
        'fecha',
        'horaInicio',
        'horaFin',
        'tipoActa',
        'observacion',
        'lugar',
        'direccion',
        'idCiudad',
        'idFicha',
        'idContrato',
    ];

     /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'nombre' => 'string',
        'fecha' => 'date',
        'horaInicio' => 'string',
        'horaFin' => 'string',
        'tipoActa' => 'string',
        'observacion' => 'string',
        'lugar' => 'string',
        'direccion' => 'string',
        'idCiudad' => 'integer',
        'idFicha' => 'integer',
        'idContrato' => 'integer',
    ];

    // Relaciones
    public function ciudad()
    {
        return $this->belongsTo(City::class, 'idCiudad');
    }

    public function ficha()
    {
        return $this->belongsTo(Ficha::class, 'idFicha');
    }

    public function contrato()
    {
        return $this->belongsTo(Contract::class, 'idContrato');
    }

    public function novedades()
    {
        return $this->hasMany(NovedadesActa::class, 'idacta');
    }

    public function agenda()
    {
        return $this->hasMany(AgendaActa::class, 'idacta');
    }

    public function objetivos()
    {
        return $this->hasMany(ObjetivoActa::class, 'idacta');
    }

    public function asistencias()
    {
        return $this->hasMany(AsistenciaActa::class, 'idActa');
    }
}