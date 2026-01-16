<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CheckItemDetail extends Model
{
    use HasFactory;

    protected $table = "checkItemDetail"; 


    public function checklistItem()
    {
        return $this->belongsTo(ChecklistItem::class, 'idCheckListItem', 'id');
    }
}
