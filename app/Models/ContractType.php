<?php

namespace App\Models;

use App\Traits\FilterCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ContractType extends Model
{
    use HasFactory, FilterCompany;

    protected $table = "tipoContrato";
    public $timestamps = false;
    public static $snakeAttributes = false;
}
