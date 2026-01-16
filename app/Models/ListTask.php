<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ListTask extends Model
{
    use HasFactory;

    protected $table = "list";

    protected $fillable = [
        "nombreList"
    ];

    public function cards()
    {
        return $this->hasMany(Card::class, 'idList');
    }

    public function board()
    {
        return $this->belongsTo(Board::class, 'idBoard');
    }
}
