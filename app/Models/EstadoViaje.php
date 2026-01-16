<?php

namespace App\Models;

use App\Models\Transporte\Viaje;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EstadoViaje extends Model
{
    use HasFactory;
    protected $table = "estadoViaje";

    public $timestamps = false;
    protected $guarded = [];

    public function viaje()
    {
        return $this->belongsTo(Viaje::class, 'idViaje');
    }
    
    public function user()
    {
        return $this->belongsTo(User::class, 'idUser');
    }
}
