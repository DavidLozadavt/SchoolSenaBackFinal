<?php

namespace App\Models;

use App\Models\Nomina\Sede;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Almacen extends Model
{
    use HasFactory;

    protected $table = "almacen";
    public static $snakeAttributes = false;


    protected $fillable = [
        "nombreAlmacen",
    ];

    //donde esta el producto
    public function distribucionProductos()
    {
        return $this->hasMany(DistribucionProducto::class, 'idAlmacenDestino');
    }


    public function sede()
    {
        return $this->belongsTo(Sede::class, 'idSede');
    }

}
