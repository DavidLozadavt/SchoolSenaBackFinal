<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TipoCotizante extends Model
{
    use HasFactory;

    protected $table = "tipoCotizante";
    public static $snakeAttributes = false;

    public $timestamps = false;
}
