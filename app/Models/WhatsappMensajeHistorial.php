<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsappMensajeHistorial extends Model
{
    protected $table = 'whatsapp_mensajes_historial';

    protected $guarded = ['id'];

    protected $casts = [
        'fecha_envio' => 'datetime',
        'fecha_entregado' => 'datetime',
        'fecha_leido' => 'datetime',
        'fecha_error' => 'datetime',
        'esMigrado' => 'boolean',
    ];

    public function aspirante()
    {
        return $this->belongsTo(SeguimientoAspirante::class, 'seguimientoAspiranteId');
    }
}
