<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TipoConcepto extends Model
{
    use HasFactory;

    protected $table = "tipoConcepto";

    protected $guarded = ['id'];
}
