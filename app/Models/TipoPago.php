<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TipoPago extends Model
{
    use HasFactory;
    public static $snakeAttributes = false;
    protected $table = "tipoPago";
    protected $fillable = [
        "detalleTipoPago"
    ];

    public $timestamps = false;

    const CREDITO = 1;
    const CONTADO = 2;
    const ESPECIE = 3;
}
