<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Ficha de programa (antes GradoPrograma). Tabla: gradoPrograma.
 * Se usa "Fichas" en la UI.
 */
class FichaPrograma extends Model
{
    protected $table = 'gradoPrograma';

    protected $fillable = [
        'idPrograma',
        'idGrado',
        'cupos',
    ];

    public function programa()
    {
        return $this->belongsTo(Programa::class, 'idPrograma');
    }

    public function grado()
    {
        return $this->belongsTo(Grado::class, 'idGrado');
    }

    public function materias()
    {
        return $this->hasMany(GradoMateria::class, 'idGradoPrograma');
    }

    /**
     * Tipos de documento asignados a esta ficha (toggle en "Asignar tipos de documentos").
     */
    public function tiposDocumento()
    {
        return $this->belongsToMany(
            TipoDocumento::class,
            'asignacion_ficha_tipo_documento',
            'idGradoPrograma',
            'idTipoDocumento'
        )->withPivot('activo')->withTimestamps();
    }
}
