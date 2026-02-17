<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Grado extends Model
{
    protected $table = 'grado';

    protected $fillable = ['numeroGrado', 'nombreGrado', 'idTipoGrado'];

    public function tipoGrado()
    {
        return $this->belongsTo(TipoGrado::class, 'idTipoGrado');
    }

    public function gradoMateria(){
        return $this->hasMany(GradoPrograma::class, 'idGrado');
    }
    
    /**
     * Alias más descriptivo para gradoMateria
     */
    public function gradoProgramas(): HasMany
    {
        return $this->hasMany(GradoPrograma::class, 'idGrado');
    }

    /**
     * Un Grado puede estar en muchos Programas (relación many-to-many)
     */
    public function programas(): BelongsToMany
    {
        return $this->belongsToMany(
            Programa::class,
            'gradoprograma',  // Tabla pivot
            'idGrado',        // FK de este modelo en la tabla pivot
            'idPrograma'      // FK del modelo relacionado en la tabla pivot
        )->withPivot('cupos', 'fechaInicio', 'fechaFin', 'estado')
         ->withTimestamps();
    }
}