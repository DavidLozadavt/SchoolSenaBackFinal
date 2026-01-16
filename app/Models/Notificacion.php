<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Notificacion extends Model
{
    use HasFactory;
    public static $snakeAttributes = false;
    protected $table = "notificacion";
    protected $fillable = [
        "asunto",
        "idpersonaReceptor",
        "mensaje",
        "idtipoNotificacion"
    ];

    public function estado()
    {
        return $this->belongsTo(Status::class, 'estado_id');
    }

    public function personaReceptor()
    {
        return $this->belongsTo(Person::class, 'idUsuarioReceptor');
    }

    public function personaRemitente()
    {
        return $this->belongsTo(Person::class, 'idUsuarioRemitente');
    }

    public function empresa()
    {
        return $this->belongsTo(Company::class, 'idEmpresa');
    }

    public function tipoNotificacion()
    {
        return $this->belongsTo(TipoNotificacion::class, 'idTipoNotificacion');
    }

    public $timestamps = false;
}
