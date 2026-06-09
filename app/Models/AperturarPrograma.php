<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AperturarPrograma extends Model
{
    use HasFactory;

    // MySQL puede convertir nombres a minúsculas, usar el nombre exacto de la BD
    protected $table = 'aperturarprograma';

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    protected $fillable = [
        'nombre',
        'observacion',
        'idPeriodo',
        'idPrograma',
        'estado',
        'idJornada',
        'idSede',
        'idTipoGrado',
        'pension',
        'diaCobro',
        'fechaInicialClases',
        'fechaFinalClases',
        'fechaInicialInscripciones',
        'fechaFinalInscripciones',
        'fechaInicialMatriculas',
        'fechaFinalMatriculas',
        'fechaInicialPlanMejoramiento',
        'fechaFinalPlanMejoramiento',
        'porcentajeMoraMatricula',
        'valorPension',
        'diasMoraMatricula',
        'porcentajeMoraPension',
        'tipoCalificacion',
        'diasMoraPension'
    ];

    protected $casts = [
        'nombre' => 'string',
        'pension' => 'boolean',
        'fechaInicialClases' => 'date',
        'fechaFinalClases' => 'date',
        'fechaInicialInscripciones' => 'date',
        'fechaFinalInscripciones' => 'date',
        'fechaInicialMatriculas' => 'date',
        'fechaFinalMatriculas' => 'date',
        'fechaInicialPlanMejoramiento' => 'date',
        'fechaFinalPlanMejoramiento' => 'date',
        'valorPension' => 'decimal:2'
    ];

    /* =========================
     * Relaciones
     * ========================= */

    public function periodo()
    {
        return $this->belongsTo(Periodo::class, 'idPeriodo');
    }

    public function programa()
    {
        return $this->belongsTo(Programa::class, 'idPrograma');
    }

    public function sede()
    {
        return $this->belongsTo(Sede::class, 'idSede');
    }

    public function fichas()
    {
        return $this->hasMany(Ficha::class, 'idAsignacion', 'id');
    }

    public function jornada()
    {
        return $this->belongsTo(Jornada::class, 'idJornada');
    }
    public function grado()
    {
        return $this->belongsTo(TipoGrado::class, 'idTipoGrado');
    }
    public function cortes()
    {
        return $this->hasMany(Corte::class, 'idApertura');
    }
}
