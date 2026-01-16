<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Card extends Model
{
    use HasFactory;

    protected $table = "cards"; 


    public function listTask()
    {
        return $this->belongsTo(ListTask::class, 'idList');
    }


    public function files()
    {
        return $this->hasMany(AttachmentsCard::class, 'idCard');
    }

    
    public function checkList()
    {
        return $this->hasMany(ChecklistCard::class, 'idCard');
    }

    public function members()
    {
        return $this->hasMany(AsignacionCardUser::class, 'idCard');
    }

    public function cardDetails()
    {
        return $this->hasMany(CardDetail::class, 'idCard');
    }
}
