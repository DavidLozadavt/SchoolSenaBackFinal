<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Nomina\Sede;


class AsignacionPeriodoPrograma extends Model
{
    // MySQL puede convertir nombres a minúsculas, usar el nombre exacto de la BD
    protected $table = 'asignacionperiodoprograma'; 
    protected $guarded = ['id']; 
    /** * Relaciones con tablas existentes 
     */

    public function periodo(): BelongsTo
    {
        return $this->belongsTo(Periodo::class, 'idPeriodo');
    }

    public function programa(): BelongsTo
    {
        return $this->belongsTo(Programa::class, 'idPrograma');
    }

    public function sede(): BelongsTo
    {
        // Relaciona con el modelo Sede, usando la columna idSede
        return $this->belongsTo(Sede::class, 'idSede');
    }
public function jornadas()
    {
       
        return $this->belongsToMany(Jornada::class, 'asignacionJornada', 'idAsignacion', 'idJornada')
                    ->withPivot('id');
    }
}