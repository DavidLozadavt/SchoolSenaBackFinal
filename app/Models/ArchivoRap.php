<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ArchivoRap extends Model
{
    use HasFactory;

    protected $table  = 'archivosRap';

    protected $guarded = ['id'];

}
