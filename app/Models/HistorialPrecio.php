<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HistorialPrecio extends Model
{
    use HasFactory;

    protected $table = "historialPrecios";
   protected $guarded = [];

   public static $snakeAttributes = false;



   public function producto()
   {
       return $this->belongsTo(Producto::class, 'idProducto');
   }


}
 