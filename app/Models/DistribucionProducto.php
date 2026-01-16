<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DistribucionProducto extends Model
{
    use HasFactory;

    protected $table = "distribucionProductos";
    public static $snakeAttributes = false;

    // 🔹 Agregar los campos que se actualizarán masivamente
    protected $fillable = [
        'idProducto',
        'idAlmacenDestino',
        'idAlmacenOrigen',
        'idCompany',
        'cantidad',
        'estado',
        'fechaTraslado',
        'observacion'
    ];

    public function producto()
    {
        return $this->belongsTo(Producto::class, 'idProducto');
    }

    public function almacenOrigen()
    {
        return $this->belongsTo(Almacen::class, 'idAlmacenOrigen');
    }

    public function almacenDestino()
    {
        return $this->belongsTo(Almacen::class, 'idAlmacenDestino');
    }
}
