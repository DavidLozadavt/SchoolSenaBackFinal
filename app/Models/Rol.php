<?php

namespace App\Models;

use App\Traits\FilterCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Models\Role;

class Rol extends Role
{
    use HasFactory, FilterCompany;

    protected $guarded = ['id'];

    protected $table = 'roles';

    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

     public function salario()
    {
        return $this->hasOne(Salario::class, 'rol_id');
    }
}
