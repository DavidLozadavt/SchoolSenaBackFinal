<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Saldo de mensajes de WhatsApp de un usuario (1-1 con `usuario`).
 */
class UsuarioMensajesSaldo extends Model
{
    use HasFactory;

    public const MENSAJES_GRATUITOS = 10;

    public static $snakeAttributes = false;

    protected $table = 'usuarioMensajesSaldos';

    protected $fillable = [
        'userId',
        'mensajesGratuitos',
        'mensajesDisponibles',
        'mensajesConsumidos',
        'planActivoId',
        'fechaActivacionPlan',
    ];

    protected $casts = [
        'mensajesGratuitos'   => 'integer',
        'mensajesDisponibles' => 'integer',
        'mensajesConsumidos'  => 'integer',
        'fechaActivacionPlan' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'userId');
    }

    public function planActivo()
    {
        return $this->belongsTo(MensajesPlan::class, 'planActivoId');
    }
}
