<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TipoFormacion extends Model
{
    protected $table = 'tipoFormacion';
    
    protected $hidden = ['created_at', 'updated_at', 'activo']; 
}