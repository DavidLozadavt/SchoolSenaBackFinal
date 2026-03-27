<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ActividadInstructor extends Model
{
    use HasFactory;

    protected $table = 'actividadesInstructores';

    protected $fillable = [
        'descripcion',
        'fechaInicial',
        'fechaFinal',
        'numeroHoras',
        'idRmi',
    ];

    protected $casts = [
        'fechaInicial' => 'date',
        'fechaFinal' => 'date',
    ];

    public function rmi()
    {
        return $this->belongsTo(Rmi::class, 'idRmi');
    }
}
