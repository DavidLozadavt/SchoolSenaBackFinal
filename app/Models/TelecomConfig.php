<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Configuración local de WhatsApp Cloud API (Meta).
 *
 * Módulo totalmente autónomo: las credenciales se administran desde este mismo backend
 * y no se reutiliza ninguna configuración de proyectos externos.
 */
class TelecomConfig extends Model
{
    use HasFactory;

    public static $snakeAttributes = false;

    protected $table = 'telecomConfigs';

    protected $fillable = [
        'provider',
        'whatsappEnabled',
        'nombre',
        'accessToken',
        'phoneNumberId',
        'businessAccountId',
        'appId',
        'verifyToken',
        'appSecret',
        'webhookUrl',
        'graphVersion',
        'activo',
        'idFormularioInscripcion',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'whatsappEnabled' => 'boolean',
    ];

    /**
     * Oculta el token en las respuestas JSON para no exponer credenciales.
     */
    protected $hidden = [
        'accessToken',
        'appSecret',
    ];

    /**
     * Devuelve la configuración activa vigente (la más reciente marcada como activa).
     */
    public static function activa(): ?self
    {
        return static::where('activo', true)->latest('id')->first();
    }
}
