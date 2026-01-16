<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AsignacionPagoAdicional extends Model
{
    use HasFactory;

    protected $table = "asignacionPagosAdicionales"; 
    public $timestamps = false;
}
