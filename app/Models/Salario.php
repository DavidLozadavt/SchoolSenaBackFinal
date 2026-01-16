<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Salario extends Model
{
    use HasFactory;

    protected $table = 'salario';
    public $timestamps = false;
    public static $snakeAttributes = false;



    public function rol()
    {
        return $this->belongsTo(Rol::class, 'rol_id');
    }

    public function salario()
    {
        return $this->hasOne(Contract::class, 'salario_id');
    }

}
