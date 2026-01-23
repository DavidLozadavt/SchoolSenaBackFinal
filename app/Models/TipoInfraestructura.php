<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class TipoInfraestructura extends Model
{
    use HasFactory;

    protected $table = 'tiposinfraestructura';

    protected $primaryKey = 'id';

    protected $fillable = [
        'nombre',
    ];

    /**
     * Un tipo de infraestructura puede tener muchas infraestructuras
     */
    public function infraestructuras()
    {
        return $this->hasMany(Infraestructura::class, 'idTipoInfraestructura');
    }
}
