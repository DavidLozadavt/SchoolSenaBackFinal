<?php

namespace App\Models\Nomina;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TarifaArl extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $table = 'tarifaArls';
}
