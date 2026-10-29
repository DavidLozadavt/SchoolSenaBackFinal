<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MaterialCompetenciaRecurso extends Model
{
    public const TIPO_DOCUMENTO = 'DOCUMENTO';

    public const TIPO_MULTIMEDIA = 'MULTIMEDIA';

    public const TIPO_ENLACE = 'ENLACE';

    /** @var list<string> */
    public const TIPOS = [
        self::TIPO_DOCUMENTO,
        self::TIPO_MULTIMEDIA,
        self::TIPO_ENLACE,
    ];

    protected $table = 'materialCompetenciaRecurso';

    protected $fillable = [
        'idMaterialCompetencia',
        'tipo',
        'titulo',
        'descripcion',
        'ruta',
        'formato',
        'orden',
    ];

    protected $casts = [
        'orden' => 'integer',
    ];

    public function material()
    {
        return $this->belongsTo(MaterialCompetencia::class, 'idMaterialCompetencia');
    }

    public function esArchivoLocal(): bool
    {
        $ruta = (string) $this->ruta;
        if ($ruta === '') {
            return false;
        }

        return ! str_starts_with($ruta, 'http://') && ! str_starts_with($ruta, 'https://');
    }
}
