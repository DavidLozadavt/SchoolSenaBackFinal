<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChecklistCard extends Model
{
    use HasFactory;

    protected $table = "checklistsCard";


    public function items()
    {
        return $this->hasMany(ChecklistItem::class, 'idChecklistCard');
    }
}
