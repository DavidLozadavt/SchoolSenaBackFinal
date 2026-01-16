<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Source extends Model
{
    use HasFactory;

    protected $table = 'source';

    public $timestamps = false;

    protected $fillable = [
        "usc",
        "psc",
        "h",
        "port",
        "nbd",
        "nombre"
    ];
}
