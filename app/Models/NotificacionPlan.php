<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Notificación in-app del módulo de Planes de Mensajes.
 */
class NotificacionPlan extends Model
{
    use HasFactory;

    public const PAGO_APROBADO        = 'PAGO_APROBADO';
    public const SOLICITUD_ENVIADA    = 'SOLICITUD_ENVIADA';
    public const SOLICITUD_APROBADA   = 'SOLICITUD_APROBADA';
    public const SOLICITUD_RECHAZADA  = 'SOLICITUD_RECHAZADA';
    public const PLAN_ACTIVADO        = 'PLAN_ACTIVADO';
    public const MENSAJES_ACREDITADOS = 'MENSAJES_ACREDITADOS';
    public const SALDO_INSUFICIENTE   = 'SALDO_INSUFICIENTE';

    public static $snakeAttributes = false;

    protected $table = 'notificacionesPlanes';

    protected $fillable = [
        'userId',
        'tipo',
        'titulo',
        'mensaje',
        'nivel',
        'solicitudId',
        'transaccionId',
        'datos',
        'leidaEn',
    ];

    protected $casts = [
        'datos'   => 'array',
        'leidaEn' => 'datetime',
    ];

    public function scopeNoLeidas($query)
    {
        return $query->whereNull('leidaEn');
    }
}
