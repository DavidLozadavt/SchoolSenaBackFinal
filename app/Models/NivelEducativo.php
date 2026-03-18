<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NivelEducativo extends Model
{
    protected $table = 'nivelEducativo';
    
    protected $fillable = ['nombreNivel', 'activo'];
    
    protected $hidden = ['created_at', 'updated_at', 'activo']; 

    public function areas()
    {
        return $this->hasMany(AreaConocimiento::class, 'idNivelEducativo');
    }
}
