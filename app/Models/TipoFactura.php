<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TipoFactura extends Model
{
    use HasFactory;

    protected $table = "tipoFactura";

    const VENTA = 1;
    const COMPRA = 2;
}
