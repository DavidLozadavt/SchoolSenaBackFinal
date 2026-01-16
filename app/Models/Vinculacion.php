<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Vinculacion extends Model
{
    use HasFactory;
    public $timestamps = false;
    protected $table = "vinculacion";


    public function tercero()
    {
        return $this->belongsTo(Tercero::class, 'idTercero');
    }
}
