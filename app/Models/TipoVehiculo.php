<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TipoVehiculo extends Model
{
    use HasFactory;

    protected $table = "tipoVehiculo";
    protected $fillable = ['tipo', 'descripcion'];
    public $timestamps = false;
}
