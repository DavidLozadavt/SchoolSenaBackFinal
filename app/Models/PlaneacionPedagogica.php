<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlaneacionPedagogica extends Model
{
    protected $table = 'planeacion_pedagogica';

    protected $fillable = [
        'idContrato',
        'idFicha',
        'idCompany',
        'semanaInicio',
        'semanaFin',
        'nivel',
        'grado',
        'periodo',
        'institucion',
        'docenteNombre',
        'proposito',
        'temaIntegrador',
        'metodologia',
        'estado',
        'reflexionDocente',
        'motivoIncompleta',
        'pdfPath',
        'enviadoAt',
        'coordinadorEmail',
    ];

    protected $casts = [
        'semanaInicio' => 'date',
        'semanaFin' => 'date',
        'enviadoAt' => 'datetime',
    ];

    public function clases(): HasMany
    {
        return $this->hasMany(PlaneacionPedagogicaClase::class, 'idPlaneacion')->orderBy('orden')->orderBy('idDia');
    }
}
