<?php

namespace App\Models;

use App\Support\MaterialCompetenciaReglas;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * Material general de una competencia (materia raíz).
 * DIRECTO guarda archivo propio. ACTIVIDAD / MATERIAL_APOYO reutilizan la referencia original.
 */
class MaterialCompetencia extends Model
{
    public const ORIGEN_DIRECTO = MaterialCompetenciaReglas::ORIGEN_DIRECTO;
    public const ORIGEN_ACTIVIDAD = MaterialCompetenciaReglas::ORIGEN_ACTIVIDAD;
    public const ORIGEN_MATERIAL_APOYO = MaterialCompetenciaReglas::ORIGEN_MATERIAL_APOYO;

    /** @var list<string> */
    public const ORIGENES = MaterialCompetenciaReglas::ORIGENES;

    /** @var list<string> */
    public const TIPOS_MATERIAL = [
        'GUIA_APRENDIZAJE',
        'MATERIAL_FORMACION',
        'TUTORIAL',
    ];

    protected $table = 'materialCompetencia';

    protected $fillable = [
        'idCompetencia',
        'idPersona',
        'idActividad',
        'idMaterialApoyoActividad',
        'origen',
        'claveOrigen',
        'titulo',
        'descripcion',
        'tipoMaterial',
        'urlDocumento',
        'urlAdicional',
        'urlVideo',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    public function competencia()
    {
        return $this->belongsTo(Materia::class, 'idCompetencia');
    }

    public function persona()
    {
        return $this->belongsTo(Person::class, 'idPersona');
    }

    public function actividad()
    {
        return $this->belongsTo(Actividad::class, 'idActividad');
    }

    public function materialApoyoActividad()
    {
        return $this->belongsTo(MaterialApoyoActividad::class, 'idMaterialApoyoActividad');
    }

    public function recursos()
    {
        return $this->hasMany(MaterialCompetenciaRecurso::class, 'idMaterialCompetencia')->orderBy('orden')->orderBy('id');
    }

    public function esDirecto(): bool
    {
        return $this->origen === self::ORIGEN_DIRECTO;
    }

    protected static function booted(): void
    {
        static::saving(function (MaterialCompetencia $material) {
            $idCompetencia = (int) ($material->idCompetencia ?? 0);
            $materia = $idCompetencia > 0 ? Materia::query()->find($idCompetencia) : null;
            if (! $materia || ! $materia->esCompetencia()) {
                throw ValidationException::withMessages([
                    'idCompetencia' => ['El material debe asociarse a una competencia, no a un RAP.'],
                ]);
            }

            $material->claveOrigen = MaterialCompetenciaReglas::claveIncorporacion(
                (string) $material->origen,
                $material->idActividad ? (int) $material->idActividad : null,
                $material->idMaterialApoyoActividad ? (int) $material->idMaterialApoyoActividad : null
            );
        });
    }
}
