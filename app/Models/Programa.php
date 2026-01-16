<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Programa extends Model
{
    use HasFactory;

    protected $table = 'programa';

    protected $fillable = [
        'nombrePrograma',
        'codigoPrograma',
        'descripcionPrograma',
        'idNivelEducativo',
        'idTipoFormacion',
        'idEstadoPrograma',
        'idCompany'
    ];

    public function nivel()
    {
        return $this->belongsTo(NivelEducativo::class, 'idNivelEducativo');
    }

    public function tipoFormacion()
    {
        return $this->belongsTo(TipoFormacion::class, 'idTipoFormacion');
    }

    public function estado()
    {
        return $this->belongsTo(EstadoPrograma::class, 'idEstadoPrograma');
    }
    public function tipoGrado(): BelongsTo
    {
        
        return $this->belongsTo(TipoGrado::class, 'idTipoGrado', 'id');
    }
}
