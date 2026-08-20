<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DocumentoSeguimiento extends Model
{
    use HasFactory;

    protected $table = 'documentoSeguimiento';

    // La migración no define timestamps()
    public $timestamps = false;

    protected $fillable = [
        'documentoUrl',
        'nombre_documento',
        'estado',
        'observacion',
        'idseguimiento',
    ];

    protected $appends = ['documentoUrlPublica'];

    public const ESTADOS = ['PENDIENTE', 'APROBADO', 'RECHAZADO'];

    // ─── Accessor de URL pública ──────────────────────────────────────────────

    public function getDocumentoUrlPublicaAttribute()
    {
        if (!empty($this->attributes['documentoUrl'])) {
            return url('storage/' . $this->attributes['documentoUrl']);
        }
        return null;
    }

    // ─── Relaciones ───────────────────────────────────────────────────────────

    public function seguimiento()
    {
        return $this->belongsTo(SeguimientoAprendiz::class, 'idseguimiento');
    }
}