<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Bonificacion extends Model
{
    use HasFactory;

    protected $table = "bonificacion";

    public function contrato()
    {
        return $this->belongsTo(Contract::class, 'idContrato');
    }

}
