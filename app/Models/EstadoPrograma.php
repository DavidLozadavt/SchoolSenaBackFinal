<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EstadoPrograma extends Model
{
    protected $table = 'estadoPrograma';
    
    protected $hidden = ['created_at', 'updated_at', 'activo'];
}
