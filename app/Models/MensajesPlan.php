<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Catálogo de planes de mensajes de WhatsApp.
 */
class MensajesPlan extends Model
{
    use HasFactory;

    public static $snakeAttributes = false;

    protected $table = 'mensajesPlanes';

    protected $fillable = [
        'nombre',
        'cantidadMensajes',
        'precio',
        'descripcion',
        'activo',
        // Presentación (Mejora 4).
        'orden',
        'recomendado',
        'color',
        'etiqueta',
    ];

    protected $casts = [
        'activo'           => 'boolean',
        'recomendado'      => 'boolean',
        'orden'            => 'integer',
        'cantidadMensajes' => 'integer',
        'precio'           => 'decimal:2',
    ];

    public function solicitudes()
    {
        return $this->hasMany(SolicitudPlanMensaje::class, 'planId');
    }

    /**
     * ¿El plan tiene compras asociadas? Si las tiene no puede eliminarse,
     * únicamente desactivarse.
     */
    public function tieneCompras(): bool
    {
        return SolicitudPlanMensaje::where('planId', $this->id)->exists()
            || WompiTransaccion::where('planId', $this->id)->exists();
    }

    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }
}
