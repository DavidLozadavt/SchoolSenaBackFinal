<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Espejo local de una plantilla de Meta (WhatsApp Cloud API).
 *
 * No reemplaza a `WhatsappPlantilla` (tabla `whatsappPlantillas`), que sigue
 * intacta y en uso por el flujo actual de envío de campañas.
 */
class WhatsappPlantillaMeta extends Model
{
    use HasFactory;

    public const ESTADOS = ['APPROVED', 'PENDING', 'REJECTED', 'PAUSED', 'DISABLED', 'IN_REVIEW'];

    public const CATEGORIAS = ['UTILITY', 'MARKETING', 'AUTHENTICATION'];

    public static $snakeAttributes = false;

    protected $table = 'whatsappPlantillasMeta';

    protected $fillable = [
        'metaTemplateId',
        'nombre',
        'categoria',
        'idioma',
        'estadoMeta',
        'contenido',
        'encabezado',
        'pie',
        'variablesEjemplo',
        'botones',
        'respuestaMeta',
        'motivoRechazo',
        'creadoPorUserId',
        'creadoPorNombre',
        'fechaAprobacion',
        'ultimaSincronizacion',
    ];

    protected $casts = [
        'variablesEjemplo'     => 'array',
        'botones'              => 'array',
        'respuestaMeta'        => 'array',
        'fechaAprobacion'      => 'datetime',
        'ultimaSincronizacion' => 'datetime',
    ];

    public function scopeAprobadas($query)
    {
        return $query->where('estadoMeta', 'APPROVED');
    }
}
