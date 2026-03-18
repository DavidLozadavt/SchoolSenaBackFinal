<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Modelo para la tabla grupos (ambiente virtual).
 * Requiere: idTipoGrupo, idAsignacionPeriodoProgramaJornada (= ficha.id), idGradoMateria.
 * Espera los cambios del compañero (horarios, tipoGrupo).
 */
class GrupoFicha extends Model
{
    use HasFactory;

    protected $table = 'grupos';
    public static $snakeAttributes = false;

    protected $fillable = [
        'nombreGrupo',
        'estado',
        'descripcion',
        'cantidadParticipantes',
        'idTipoGrupo',
        'idAsignacionPeriodoProgramaJornada',
        'idGradoMateria',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function tipoGrupo()
    {
        return $this->belongsTo(TipoGrupo::class, 'idTipoGrupo');
    }

    public function gradoMateria()
    {
        return $this->belongsTo(GradoMateria::class, 'idGradoMateria');
    }
}
