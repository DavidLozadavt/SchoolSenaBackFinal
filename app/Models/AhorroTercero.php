<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AhorroTercero extends Model
{
    use HasFactory;


    protected $table = 'ahorroTercero';


    public function tercero()
    {
        return $this->belongsTo(Tercero::class, 'idTercero');
    }

}
