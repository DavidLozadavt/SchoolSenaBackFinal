<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AlmacenDocumento extends Model
{
    use HasFactory;

    protected $table = "almacen_documentos";
    public static $snakeAttributes = false;

    
}
