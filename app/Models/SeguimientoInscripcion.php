<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SeguimientoInscripcion extends Model
{
    protected $table = 'seguimiento_inscripcion';

    protected $guarded = [];

    protected $casts = [
        'token_expires_at' => 'datetime',
        'fecha_limite_pago' => 'date',
        'fecha_correo_enviado' => 'datetime',
    ];

    public function factura(): BelongsTo
    {
        return $this->belongsTo(Factura::class, 'idFactura');
    }

    public function matricula(): BelongsTo
    {
        return $this->belongsTo(Matricula::class, 'idMatricula');
    }

    public function persona(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'idPersona');
    }

    public function tercero(): BelongsTo
    {
        return $this->belongsTo(Tercero::class, 'idTercero');
    }

    public function proceso(): BelongsTo
    {
        return $this->belongsTo(Proceso::class, 'idProceso');
    }

    public function comprobantes(): HasMany
    {
        return $this->hasMany(SeguimientoInscripcionComprobante::class, 'idSeguimientoInscripcion');
    }

    public function pagosGateway(): HasMany
    {
        return $this->hasMany(SeguimientoInscripcionPagoGateway::class, 'idSeguimientoInscripcion');
    }
}
