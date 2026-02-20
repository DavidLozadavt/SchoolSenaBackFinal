<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Programa extends Model
{
    use HasFactory;

    protected $table = 'programa';

    protected $fillable = [
        'nombrePrograma',
        'codigoPrograma',
        'descripcionPrograma',
        'documento',
        'idNivelEducativo',
        'idTipoFormacion',
        'idEstadoPrograma',
        'idCompany',
        'idRed'
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

    public function aperturarProgramas(): HasMany
    {
        return $this->hasMany(AperturarPrograma::class, 'idPrograma');
    }

    public function agregarMateriaPrograma(): HasMany
    {
        return $this->hasMany(AgregarMateriaPrograma::class, 'idPrograma');
    }

    // 🔥 NUEVA RELACIÓN: Programa tiene muchos GradoPrograma
    public function gradoProgramas(): HasMany
    {
        return $this->hasMany(GradoPrograma::class, 'idPrograma');
    }

    // 🔥 NUEVA RELACIÓN: Acceso directo a los grados a través de la tabla pivot
    public function grados(): BelongsToMany
    {
        return $this->belongsToMany(
            Grado::class,
            'gradoPrograma',  // Tabla pivot
            'idPrograma',     // FK en la tabla pivot para este modelo
            'idGrado'         // FK en la tabla pivot para el modelo relacionado
        )->withPivot('cupos', 'fechaInicio', 'fechaFin', 'estado')
         ->withTimestamps();
    }
    public function red(){
        return $this->belongsTo(Red::class, 'idRed');
    }
}
