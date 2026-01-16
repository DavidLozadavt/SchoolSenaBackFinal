<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class DetalleProducto extends Model
{
    use HasFactory;
    protected $guarded = ['id'];

    protected $table = "detalleProducto";




    public function detalleFacturas()
    {
        return $this->hasMany(DetalleFactura::class, 'idDetalleProducto');
    }

    public function detalleFactura(): HasOne
    {
        return $this->hasOne(DetalleFactura::class, 'idDetalleProducto', 'id');
    }


    public function shoppingCarts()
    {
        return $this->hasMany(ShoppingCart::class, 'idDetalleProducto');
    }



}
