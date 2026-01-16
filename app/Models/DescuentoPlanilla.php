<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DescuentoPlanilla extends Model
{
    protected $table = 'descuentosPlanilla';

    protected $guarded = [
        'id',
        'created_at',
        'updated_at'
    ];

    protected $casts = [
        'valor' => 'decimal:2',
        'porcentaje' => 'double',
  
    ];

    public function empresa()
    {
        return $this->belongsTo(Company::class, 'idCompany');
    }
}