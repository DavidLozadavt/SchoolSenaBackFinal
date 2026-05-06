<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AsignacionSesion extends Model
{
    use HasFactory;
    protected $table = 'asignacionSesion';
    public $timestamps = false;
    protected $fillable = ['tipoAsignacion', 'fechaInicio', 'fechaFin', 'idContrato', 'idHorarioMateria', 'observacion'];

    // relaciones
    public function contrato(): BelongsTo
    {
        return $this->belongsTo(Contract::class, 'idContrato', 'id');
    }

    public function horario(): BelongsTo
    {
        return $this->belongsTo(HorarioMateria::class, 'idHorarioMateria', 'id');
    }
}
