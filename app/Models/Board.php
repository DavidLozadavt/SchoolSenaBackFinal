<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Board extends Model
{
    use HasFactory;


    protected $table = "board"; 


    public function imageBoard()
    {
        return $this->belongsTo(ImageBoard::class, 'idImagenBoard');
    }

    public function listsTask()
    {
        return $this->hasMany(ListTask::class, 'idBoard');
    }

}
