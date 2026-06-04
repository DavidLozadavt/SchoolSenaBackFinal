<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeguimientoInscripcionComprobante extends Model
{
    protected $table = 'seguimiento_inscripcion_comprobante';

    protected $guarded = [];

    protected $casts = [
        'fecha_carga' => 'datetime',
        'fecha_revision' => 'datetime',
    ];

    protected $appends = ['urlArchivo'];

    public function getUrlArchivoAttribute(): ?string
    {
        if (empty($this->ruta_archivo)) {
            return null;
        }

        return str_starts_with($this->ruta_archivo, 'http')
            ? $this->ruta_archivo
            : url($this->ruta_archivo);
    }

    public function seguimiento(): BelongsTo
    {
        return $this->belongsTo(SeguimientoInscripcion::class, 'idSeguimientoInscripcion');
    }

    public function factura(): BelongsTo
    {
        return $this->belongsTo(Factura::class, 'idFactura');
    }

    public function pago(): BelongsTo
    {
        return $this->belongsTo(Pago::class, 'idPago');
    }
}
