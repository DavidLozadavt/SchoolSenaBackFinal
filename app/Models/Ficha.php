<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ficha extends Model
{
    use HasFactory;

    protected $table = 'ficha';

    protected $fillable = [
        'idJornada',
        'idAsignacion',
        'codigo',
        'idInstructorLider',
        'documento',
        'idInfraestructura',
        'idSede',
        'idRegional',
        'porcentajeEjecucion',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relaciones
    |--------------------------------------------------------------------------
    */

    public function jornada()
    {
        return $this->belongsTo(Jornada::class, 'idJornada');
    }

    public function asignacion()
    {
        return $this->belongsTo(AperturarPrograma::class, 'idAsignacion');
    }

    public function aperturarPrograma()
    {
        return $this->belongsTo(AperturarPrograma::class, 'idAsignacion');
    }

    public function infraestructura()
    {
        return $this->belongsTo(Infraestructura::class, 'idInfraestructura');
    }

    public function sede()
    {
        return $this->belongsTo(Sede::class, 'idSede');
    }
    public function regional()
    {
        return $this->belongsTo(Company::class, 'idRegional');
    }

    public function instructorLider()
    {
        return $this->belongsTo(Contract::class, 'idInstructorLider');
    }

    public function horarios()
    {
        return $this->hasMany(HorarioMateria::class, 'idFicha', 'id');
    }
}
