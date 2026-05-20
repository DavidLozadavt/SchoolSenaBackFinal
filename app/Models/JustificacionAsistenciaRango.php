<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class JustificacionAsistenciaRango extends Model
{
    use HasFactory;

    protected $table = 'justificacionAsistenciaRangos';
    protected $guarded = ['id'];

    protected $appends = ['urlDocumento'];

    public function getUrlDocumentoAttribute()
    {
        $archivo = $this->attributes['archivoSoporte'] ?? null;

        if (!$archivo) {
            return null;
        }

        if (
            str_starts_with($archivo, 'http://') ||
            str_starts_with($archivo, 'https://')
        ) {
            return $archivo;
        }

        $archivo = str_replace('/storage/', '', $archivo);
        $archivo = str_replace('storage/', '', $archivo);
        $archivo = ltrim($archivo, '/');

        return Storage::disk('public')->url($archivo);
    }

    public function personaAprendiz()
    {
        return $this->belongsTo(Person::class, 'idPersonaAprendiz');
    }

    public function matricula()
    {
        return $this->belongsTo(Matricula::class, 'idMatricula');
    }

    public function personaAutoriza()
    {
        return $this->belongsTo(Person::class, 'idPersonaAutoriza');
    }

    public function detalles()
    {
        return $this->hasMany(JustificacionAsistenciaRangoDetalle::class, 'idJustificacionAsistenciaRango');
    }
}