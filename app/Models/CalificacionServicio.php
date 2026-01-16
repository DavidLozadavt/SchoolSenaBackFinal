<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CalificacionServicio extends Model
{
    use HasFactory;

    protected $table = 'calificacionServicio';


    public function user()
    {
        return $this->belongsTo(User::class, 'idUser');
    }
}
