<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChecklistItem extends Model
{
    use HasFactory;
    protected $table = "checklistItems";



    public function checklistCard()
    {
        return $this->belongsTo(ChecklistCard::class, 'idChecklistCard');
    }


    public function checkItemDetail()
    {
        return $this->hasOne(CheckItemDetail::class, 'idCheckListItem', 'id');
    }



    public function checkItemUser()
    {
        return $this->hasOne(AsignacionCheckItemUser::class, 'idCheckListItem', 'id');
    }


    public function commentCheckItems()
    {
        return $this->hasMany(CommentCheckItem::class, 'idChecklisteItem');
    }
}
