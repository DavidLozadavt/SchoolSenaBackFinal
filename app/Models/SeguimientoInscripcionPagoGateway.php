<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeguimientoInscripcionPagoGateway extends Model
{
    protected $table = 'seguimiento_inscripcion_pago_gateway';

    protected $guarded = [];

    protected $casts = [
        'payload_request' => 'array',
        'payload_response' => 'array',
        'confirmed_at' => 'datetime',
        'monto' => 'float',
    ];

    public function seguimiento(): BelongsTo
    {
        return $this->belongsTo(SeguimientoInscripcion::class, 'idSeguimientoInscripcion');
    }

    public function factura(): BelongsTo
    {
        return $this->belongsTo(Factura::class, 'idFactura');
    }
}
