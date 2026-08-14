<?php

namespace App\Models\ciadet;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Archivos extends Model
{
    use HasFactory;

    protected $table = 'archivos';

    public $timestamps = false;

    // Clave primaria compuesta – Eloquent no la soporta nativamente,
    // se declara incrementing = false para evitar que asuma auto-increment.
    public $incrementing = false;

    protected $fillable = [
        'id',
        'urlArchivo',
        'nombreArchivo',
        'aprobada',
        'carpetas_viajeras_id',
    ];

    protected $casts = [
        'aprobada' => 'boolean',
    ];

    protected $appends = ['rutaArchivoUrl'];

    const RUTA_ARCHIVO = 'ciadet/carpetas_viajeras';

    // ─── Accessor de URL pública ──────────────────────────────────────────────

    public function getRutaArchivoUrlAttribute()
    {
        if (!empty($this->attributes['urlArchivo'])) {
            return url('storage/' . $this->attributes['urlArchivo']);
        }
        return null;
    }

    // ─── Relaciones ───────────────────────────────────────────────────────────

    public function carpetaViajera()
    {
        return $this->belongsTo(CarpetasViajeras::class, 'carpetas_viajeras_id');
    }
}
