<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Trabajador extends Model
{
    use HasFactory;

    public static $snakeAttributes = false;
    public $timestamps = true;
    protected $table = "trabajadores";
    protected $guarded = [];
    protected $hidden = [
        'created_at',
        'updated_at'
    ];
}
