<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Grupo extends Model
{
    use HasFactory;


    protected $table = "grupo";
    public static $snakeAttributes = false;
    public $timestamps = false;


     public function clase()
    {
        return $this->belongsTo(Clase::class, 'clase_id');
    }
   
}
