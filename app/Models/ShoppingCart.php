<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShoppingCart extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $table = "shoppingCart";


    public function tercero()
    {
        return $this->belongsTo(Tercero::class, 'idTercero');
    }

    public function asignaciones()
    {
        return $this->hasMany(AsignacionCarritoProducto::class, 'idShoppingCart');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'idUser');
    }
}
