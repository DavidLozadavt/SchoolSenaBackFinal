<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PortafolioDocumento extends Model
{
    use HasFactory;

    protected $table = 'portafolioDocumentos';

    protected $appends = ['urlDocumentoUrl']; // Agrega el accessor a los atributos por defecto

    protected $fillable = [
        'descripcion',
        'urlDocumento',
        'idPortafolioFichas',
        'idCategoria',
    ];

    public function portafolioFicha()
    {
        return $this->belongsTo(PortafolioFicha::class, 'idPortafolioFichas');
    }

    /**
     * Accesor para obtener la URL completa del documento.
     *
     * @return string|null
     */
    public function getUrlDocumentoUrlAttribute()
    {
        if (!$this->urlDocumento)
            return null;

        if (str_starts_with($this->urlDocumento, 'http')) {
            return $this->urlDocumento;
        }

        return url($this->urlDocumento); // ya tiene storage/ incluido
    }

    public function categoria()
    {
        return $this->belongsTo(PortafolioCategoria::class, 'idCategoria');
    }
}