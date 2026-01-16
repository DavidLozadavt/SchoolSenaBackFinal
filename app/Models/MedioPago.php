<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MedioPago extends Model
{
    use HasFactory;
    public static $snakeAttributes = false;
    protected $table = "medioPago";
    protected $fillable = [
        "detalleMedioPago"
    ];

    const EFECTIVO = 1;
    const TRANSFERENCIA = 4;
    const ESPECIE = 5;

    public $timestamps = false;
}
