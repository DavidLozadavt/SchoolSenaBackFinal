<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClaseProducto extends Model
{
    use HasFactory;

    protected $table = "claseProducto";
    public static $snakeAttributes = false;
  
}
