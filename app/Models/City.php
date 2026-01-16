<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class City extends Model
{
    use HasFactory;

    protected $table = 'ciudad';
    public static $snakeAttributes = false;

    public function departamento(){
        return $this->belongsTo(Country::class, 'iddepartamento', 'id');
    } 
   

}
