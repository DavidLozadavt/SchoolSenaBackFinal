<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AreaConocimiento extends Model
{
    use HasFactory;

    protected $table = 'area_conocimiento';

    protected $fillable = [
        'nombreAreaConocimiento'
    ];
}
