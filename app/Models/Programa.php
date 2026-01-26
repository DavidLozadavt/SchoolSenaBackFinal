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
    /**
     * Relación con TipoGrado (opcional - puede no existir la columna idTipoGrado en la tabla)
     * Si la columna no existe, esta relación retornará null
     */
    public function tipoGrado(): BelongsTo
    {
        return $this->belongsTo(TipoGrado::class, 'idTipoGrado', 'id');
    }

    /**
     * Tipos de documento autorizados/activos para este programa.
     * Tabla pivot: asignacion_programa_tipo_documento (con campo activo).
     */
    public function tiposDocumento()
    {
        return $this->belongsToMany(
            TipoDocumento::class,
            'asignacion_programa_tipo_documento',
            'idPrograma',
            'idTipoDocumento'
        )->withPivot('activo')->withTimestamps();
    }
}
