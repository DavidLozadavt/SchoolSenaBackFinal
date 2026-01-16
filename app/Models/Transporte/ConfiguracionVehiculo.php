<?php

namespace App\Models\Transporte;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConfiguracionVehiculo extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $table = 'configuracionVehiculos';
    

}
