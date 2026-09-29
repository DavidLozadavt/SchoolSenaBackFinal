<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Transacción de pago en Wompi asociada a la compra de un plan de mensajes.
 */
class WompiTransaccion extends Model
{
    use HasFactory;

    public const PENDING  = 'PENDING';
    public const APPROVED = 'APPROVED';
    public const DECLINED = 'DECLINED';
    public const VOIDED   = 'VOIDED';
    public const ERROR    = 'ERROR';

    public const ESTADOS = [self::PENDING, self::APPROVED, self::DECLINED, self::VOIDED, self::ERROR];

    public static $snakeAttributes = false;

    protected $table = 'wompiTransacciones';

    protected $fillable = [
        'reference',
        'userId',
        'companyId',
        'planId',
        'solicitudId',
        'transactionId',
        'paymentMethod',
        'paymentMethodType',
        'amountInCents',
        'amount',
        'currency',
        'status',
        'statusMessage',
        'customerEmail',
        'fechaPago',
        'respuestaWompi',
        'origenActualizacion',
        // Idempotencia y validaciones de seguridad (Mejoras 1 y 2).
        'procesadaEn',
        'validacionFallida',
        'motivoValidacion',
    ];

    protected $casts = [
        'amountInCents' => 'integer',
        'amount'          => 'decimal:2',
        'fechaPago'        => 'datetime',
        'respuestaWompi'    => 'array',
        'procesadaEn'       => 'datetime',
        'validacionFallida' => 'boolean',
    ];

    /** ¿Ya se procesó (generó solicitud)? Base de la idempotencia. */
    public function yaProcesada(): bool
    {
        return $this->procesadaEn !== null;
    }

    public function plan()
    {
        return $this->belongsTo(MensajesPlan::class, 'planId');
    }

    public function solicitud()
    {
        return $this->belongsTo(SolicitudPlanMensaje::class, 'solicitudId');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'userId');
    }

    public function estaAprobada(): bool
    {
        return $this->status === self::APPROVED;
    }
}
