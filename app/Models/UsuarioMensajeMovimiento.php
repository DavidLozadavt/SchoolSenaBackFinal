<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Movimiento de mensajes de un usuario (historial append-only).
 *
 * Complementa —sin reemplazar— a `UsuarioMensajesSaldo` (saldo actual) y a
 * `AuditoriaPlanMensaje` (auditoría de solicitudes de la Fase 1).
 */
class UsuarioMensajeMovimiento extends Model
{
    use HasFactory;

    public const GRATUITO_INICIAL = 'GRATUITO_INICIAL';
    public const RECARGA          = 'RECARGA';
    public const CONSUMO          = 'CONSUMO';
    public const BONIFICACION     = 'BONIFICACION';
    public const AJUSTE_MANUAL    = 'AJUSTE_MANUAL';
    public const REVERSO          = 'REVERSO';

    public const TIPOS = [
        self::GRATUITO_INICIAL,
        self::RECARGA,
        self::CONSUMO,
        self::BONIFICACION,
        self::AJUSTE_MANUAL,
        self::REVERSO,
    ];

    protected $table = 'usuarioMensajesMovimientos';

    protected $fillable = [
        'userId',
        'tipoMovimiento',
        'cantidad',
        'saldoAnterior',
        'saldoNuevo',
        'descripcion',
        'referencia',
    ];

    protected $casts = [
        'cantidad'       => 'integer',
        'saldoAnterior' => 'integer',
        'saldoNuevo'    => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'userId');
    }
}
