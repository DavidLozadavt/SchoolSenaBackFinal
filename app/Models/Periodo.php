<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Company;

class Periodo extends Model 
{
    use HasFactory;

    protected $table = 'periodo'; 

    protected $fillable = [
        'nombrePeriodo',
        'fechaInicial',
        'fechaFinal',
        'idEmpresa', 
    ];

    public function empresa()
    {
        return $this->belongsTo(Company::class, 'idEmpresa');
    }
}