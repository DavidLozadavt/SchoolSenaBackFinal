<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SolicitudProducto extends Model
{
    use HasFactory;


    protected $table = 'solicitudProducto';
    public $timestamps = false;



    public function estado()
    {
        return $this->belongsTo(Status::class, 'idEstado');
    }

    
    public function productoEmpresarial()
    {
        return $this->belongsTo(ProductoEmpresarial::class, 'idProductoEmpresarial');
    }

   
    public function plan()
    {
        return $this->belongsTo(Plan::class, 'idPlan');
    }


    public function tercero()
    {
        return $this->belongsTo(Tercero::class, 'idTercero');
    }
    
    
}
