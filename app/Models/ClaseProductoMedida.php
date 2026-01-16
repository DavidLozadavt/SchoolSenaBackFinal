<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClaseProductoMedida extends Model
{
    use HasFactory;

    protected $table = "claseProductoMedida";
    public static $snakeAttributes = false;
    public $timestamps = false;


    public function medida()
    {
        return $this->belongsTo(Medida::class, 'idMedida');
    }
}
