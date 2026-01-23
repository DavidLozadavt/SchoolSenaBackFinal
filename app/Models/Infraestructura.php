<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Nomina\Sede;

class Infraestructura extends Model
{
    use HasFactory;

    protected $table = 'infraestructura';

    protected $primaryKey = 'id';

    protected $fillable = [
        'nombreInfraestructura',
        'capacidad',
        'idSede',
        'idTipoInfraestructura', 
    ];

    /**
     * Relación con SedeSchool
     */
    public function sede()
    {
        return $this->belongsTo(Sede::class, 'idSede');
    }

    /**
     * Relación con TipoInfraestructura
     */
    public function tipoInfraestructura()
    {
        return $this->belongsTo(TipoInfraestructura::class, 'idTipoInfraestructura');
    }
}
