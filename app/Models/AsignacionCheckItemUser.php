<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AsignacionCheckItemUser extends Model
{
    use HasFactory;


    protected $table = "asignacionCheckItemUser"; 

    public function user()
    {
        return $this->belongsTo(User::class, 'idUser');
    }

    public function checklistItem()
    {
        return $this->belongsTo(ChecklistItem::class, 'idCheckListItem', 'id');
    }
    
    
}
