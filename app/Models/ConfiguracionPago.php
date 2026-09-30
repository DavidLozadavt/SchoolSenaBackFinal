<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConfiguracionPago extends Model
{
    use HasFactory;

    protected $table = 'configuracionPago';
    protected $guarded = ['id'];


    
  public function asignacionProcesoPago()
    {
        return $this->hasOne(AsignacionProcesoPago::class, 'idConfiguracionPago');
    }

    public function configuracionPagoVigencias()
    {
        return $this->hasMany(ConfiguracionPagoVigencia::class, 'idConfiguracionPago');
    }

  
}
