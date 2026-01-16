<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Proceso extends Model
{
    use HasFactory;
    public static $snakeAttributes = false;
    protected $table = "proceso";
    protected $fillable = [
        "nombreProceso",
        "descripcion"
    ];

    public $timestamps = false;


    public function asignaciones()
    {
        return $this->hasMany(AsignacionProcesoTipoDocumento::class, 'idProceso');
    }
}
