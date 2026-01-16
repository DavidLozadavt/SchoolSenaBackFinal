<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AsignacionCardUser extends Model
{
    use HasFactory;

    
    protected $table = "asignacionCardUsers"; 

    public function user()
    {
        return $this->belongsTo(User::class, 'idUser');
    }

    public function card()
    {
        return $this->belongsTo(Card::class , 'idCard');
    }
}
