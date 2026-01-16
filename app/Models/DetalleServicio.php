<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DetalleServicio extends Model
{
    use HasFactory;

    protected $table = "detalleServicio";
    protected $guarded = [];
    
    public function servicio()
    {
        return $this->belongsTo(Servicio::class, 'idServicio');
    }

 
    public function prestacionServicio()
    {
        return $this->belongsTo(PrestacionServicio::class, 'idPrestacionServicio');
    }


    public function asignacionesCarrito()
    {
        return $this->hasMany(AsignacionCarritoProducto::class, 'idDetalleServicio');
    }
    

    //relacion que tiene la agenda
       public function asignacionResponsableServicio()
    {
        return $this->hasMany(AsignacionResponsableServicio::class, 'idDetalleServicio');
    }
}
