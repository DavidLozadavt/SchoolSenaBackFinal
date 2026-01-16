<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AsignacionCarritoProducto extends Model
{
    use HasFactory;
    protected $guarded = ['id'];

    protected $table = "asignacionCarritoProductos";


    public function shoppingCart()
    {
        return $this->belongsTo(ShoppingCart::class, 'idShoppingCart');
    }

    public function producto()
    {
        return $this->belongsTo(Producto::class, 'idProducto');
    }

    public function detalleServicio()
    {
        return $this->belongsTo(DetalleServicio::class, 'idDetalleServicio');
    }
}
