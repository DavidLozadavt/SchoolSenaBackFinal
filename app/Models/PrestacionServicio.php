<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PrestacionServicio extends Model
{
    use HasFactory;

    protected $table = "prestacionServicios";
    protected $guarded = [];

  
    public function factura()
    {
        return $this->belongsTo(Factura::class, 'idFactura');
    }

    public function detalles()
    {
        return $this->hasMany(DetalleServicio::class, 'idPrestacionServicio');
    }

    public function responsable()
    {
        return $this->belongsTo(ResponsableServicio::class, 'idResponsable');
    }

    public function articuloServicio()
    {
        return $this->belongsTo(ArticuloServicio::class, 'idArticuloServicio');
    }
    
        public function transacciones()
    {
        return $this->belongsToMany(Transaccion::class, 'prestacionServicioTransaccion', 'idPrestacionServicio', 'idTransaccion');
    }

    public function escenario()
    {
        return $this->belongsTo(Escenario::class, 'idEscenario');
    }
    
}
