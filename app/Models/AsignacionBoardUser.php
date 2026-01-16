<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AsignacionBoardUser extends Model
{
    use HasFactory;

    protected $table = "asignacionBoardUsers"; 


    public function board()
    {
        return $this->belongsTo(Board::class, 'idBoard');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'idUser');
    }
}
