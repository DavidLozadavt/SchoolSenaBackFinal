<?php

namespace App\Models;

use App\Traits\UtilNotification;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Caja extends Model
{
    use HasFactory;

    protected $table = "caja";
    public $timestamps = false;

    protected $guarded = [];


    protected $casts = [
        'idUsuario' => 'integer',
        'idEstado' => 'integer',
        'idPuntoDeVenta' => 'integer',
    ];



    public function usuario()
    {
        return $this->belongsTo(User::class, 'idUsuario');
    }

    public function estado()
    {
        return $this->belongsTo(Status::class, 'idEstado');
    }


    public function puntoVenta()
    {
        return $this->belongsTo(PuntoVenta::class, 'idPuntoDeVenta');
    }


    public function notificar($idPersona, $mensaje, $metaData)
    {
        $this->sendNotification(TipoNotificacion::ID_ACTIVO, 'Nueva calificación', $idPersona, $mensaje, $metaData);
    }




}