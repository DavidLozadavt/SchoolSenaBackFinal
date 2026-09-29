<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Registro append-only de auditoría de planes/saldos de mensajes.
 */
class AuditoriaPlanMensaje extends Model
{
    use HasFactory;

    public const SOLICITUD_CREADA     = 'SOLICITUD_CREADA';
    public const SOLICITUD_APROBADA   = 'SOLICITUD_APROBADA';
    public const SOLICITUD_RECHAZADA  = 'SOLICITUD_RECHAZADA';
    public const MENSAJES_CONSUMIDOS  = 'MENSAJES_CONSUMIDOS';
    public const PAGO_APROBADO             = 'PAGO_APROBADO';
    public const PAGO_VALIDACION_FALLIDA   = 'PAGO_VALIDACION_FALLIDA';
    public const PAGO_ESTADO_ACTUALIZADO   = 'PAGO_ESTADO_ACTUALIZADO';
    public const PLAN_CREADO               = 'PLAN_CREADO';
    public const PLAN_ACTUALIZADO          = 'PLAN_ACTUALIZADO';

    public static $snakeAttributes = false;

    protected $table = 'auditoriaPlanesMensajes';

    protected $fillable = [
        'userId',
        'solicitudId',
        'accion',
        'descripcion',
        'mensajesAntes',
        'mensajesDespues',
        'realizadoPor',
        'detalle',
        // Contexto ampliado (Mejora 5).
        'planId',
        'planNombre',
        'cantidadMensajes',
        'referenciaPago',
        'transactionId',
        'estadoAnterior',
        'estadoNuevo',
        'fechaPago',
        'fechaAprobacion',
        'ip',
        'userAgent',
        'observaciones',
    ];

    protected $casts = [
        'detalle'         => 'array',
        'fechaPago'       => 'datetime',
        'fechaAprobacion' => 'datetime',
    ];
}
