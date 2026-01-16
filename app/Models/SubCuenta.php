<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SubCuenta extends Model
{
    use HasFactory;

    protected $table = "subcuenta";
    public static $snakeAttributes = false;
    public $timestamps = false;


    public function cuenta()
    {
        return $this->belongsTo(Cuenta::class, 'cuenta_id');
    }
}
