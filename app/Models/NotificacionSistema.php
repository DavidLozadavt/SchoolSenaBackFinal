<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NotificacionSistema extends Model
{
    use HasFactory;

    protected $table = 'notificacion';

    protected $fillable = [
        'fecha',
        'hora',
        'asunto',
        'mensaje',
        'estado_id',
        'idUsuarioReceptor',
        'idUsuarioRemitente',
        'idTipoNotificacion',
        'idEmpresa',
        'route'
    ];

    protected $casts = [
        'fecha' => 'date',
        'hora' => 'datetime:H:i:s'
    ];

    // Relaciones

    public function estado()
    {
        return $this->belongsTo(Status::class, 'estado_id');
    }

    public function usuarioReceptor()
    {
        return $this->belongsTo(User::class, 'idUsuarioReceptor');
    }

    public function usuarioRemitente()
    {
        return $this->belongsTo(User::class, 'idUsuarioRemitente');
    }

    public function empresa()
    {
        return $this->belongsTo(Company::class, 'idEmpresa');
    }

    public function tipoNotificacion()
    {
        return $this->belongsTo(TipoNotificacion::class, 'idTipoNotificacion');
    }
}