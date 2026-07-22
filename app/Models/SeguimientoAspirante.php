<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SeguimientoAspirante extends Model
{
    use HasFactory;

    public static $snakeAttributes = false;
    
    public $timestamps = true;
    
    protected $table = "seguimientoAspirantes";
    
    protected $guarded = [];
}
