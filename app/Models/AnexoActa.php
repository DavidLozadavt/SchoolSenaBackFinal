<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class AnexoActa extends Model
{
    use HasFactory;

    protected $table = 'anexosActa';

    protected $fillable = [
        'idacta',
        'nombre',
        'archivo',
        'descripcion',
    ];

    protected $appends = ['rutaArchivoUrl'];

    public function getRutaArchivoUrlAttribute()
    {
        if (!$this->archivo) return null;

        // Si ya es una URL completa o ya tiene el prefijo /storage, lo retornamos tal cual
        if (str_starts_with($this->archivo, 'http') || str_starts_with($this->archivo, '/storage')) {
            return $this->archivo;
        }

        return Storage::url($this->archivo);
    }

    public function acta()
    {
        return $this->belongsTo(Acta::class, 'idacta');
    }
}