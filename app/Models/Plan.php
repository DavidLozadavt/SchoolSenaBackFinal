<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    use HasFactory;

    protected $table = "plan";

    protected $fillable = [
        "nombrePlan",
        'descripcionPlan',
        'valor',
        'idEstado',
        'periodoMeses',
        'idProductoEmpresarial',
        'numeroUsuarios',
    ];

    public function estado()
    {
        return $this->belongsTo(Status::class, 'idEstado');
    }
}
