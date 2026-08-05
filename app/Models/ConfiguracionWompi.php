<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Configuración global del sistema de pagos (módulo "Configuración de Pagos").
 *
 * Fila única, tabla `configuracionPagosSistema`. No tiene relación con el
 * modelo `ConfiguracionPago` (tabla `configuracionPago`), que pertenece a otro
 * módulo y no se toca.
 *
 * Las llaves sensibles usan el cast `encrypted`: se almacenan cifradas con la
 * APP_KEY y NUNCA se exponen en las respuestas JSON (van en `$hidden`); el
 * módulo solo informa si están presentes.
 */
class ConfiguracionWompi extends Model
{
    use HasFactory;

    public const MODO_SANDBOX    = 'SANDBOX';
    public const MODO_PRODUCCION = 'PRODUCCION';

    public static $snakeAttributes = false;

    protected $table = 'configuracionWompi';

    protected $fillable = [
        'modo',
        'proveedor',
        'moneda',
        'ivaPorcentaje',
        'mensajesGratuitos',
        'activo',
        'horasMaxAprobacion',
        'urlRetorno',
        'urlWebhook',
        'publicKey',
        'privateKey',
        'integritySecret',
        'eventsSecret',
        'usarLlavesPropias',
        'actualizadoPor',
    ];

    protected $casts = [
        'activo'             => 'boolean',
        'usarLlavesPropias'  => 'boolean',
        'ivaPorcentaje'      => 'decimal:2',
        'mensajesGratuitos'  => 'integer',
        'horasMaxAprobacion' => 'integer',
        // Cifrado en reposo con la APP_KEY de Laravel.
        'publicKey'          => 'encrypted',
        'privateKey'         => 'encrypted',
        'integritySecret'    => 'encrypted',
        'eventsSecret'       => 'encrypted',
    ];

    /** Las llaves jamás salen en las respuestas de la API. */
    protected $hidden = [
        'publicKey',
        'privateKey',
        'integritySecret',
        'eventsSecret',
    ];

    /**
     * Devuelve (creando si hace falta) la fila única de configuración.
     */
    public static function vigente(): self
    {
        $configuracion = static::query()->orderBy('id')->first();

        return $configuracion ?: static::create([
            'modo'      => self::MODO_SANDBOX,
            'proveedor' => 'WOMPI',
            'moneda'    => 'COP',
        ]);
    }

    public function esProduccion(): bool
    {
        return $this->modo === self::MODO_PRODUCCION;
    }
}
