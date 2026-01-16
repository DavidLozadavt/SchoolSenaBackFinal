<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cuenta extends Model
{
    use HasFactory;

    
    protected $table = "cuenta";
    public static $snakeAttributes = false;
    public $timestamps = false;


     public function grupo()
    {
        return $this->belongsTo(Grupo::class, 'grupo_id');
    }
}
