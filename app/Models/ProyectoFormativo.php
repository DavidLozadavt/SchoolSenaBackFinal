<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProyectoFormativo extends Model
{
    protected $table = 'proyectoFormativo';

    protected $fillable = [
        'nombreProyecto',
        'version',
        'estado',
        'idPrograma',
        'documento'
    ];

    const RUTA_DOCUMENTO = "proyectoFormativo/documento";

    protected $appends = ['rutaDocumentoUrl'];

    public function getRutaDocumentoUrlAttribute()
    {
        if (!empty($this->attributes['documento'])) {
            return url('storage/' . $this->attributes['documento']);
        }
        return null;
    }

    // Relación con Programa
    public function programa()
    {
        return $this->belongsTo(Programa::class, 'idPrograma');
    }
    public function fases(): HasMany
    {
        return $this->hasMany(FaseProyecto::class, 'idProyectoFormativo');
    }
}
