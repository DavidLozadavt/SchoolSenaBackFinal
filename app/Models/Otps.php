<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Otps extends Model
{
    // OPCIÓN 1: Deshabilitar timestamps automáticos (RECOMENDADO)
    public $timestamps = false;
    
    protected $table = 'otps';

    protected $fillable = [
        'identifier',
        'token',
        'validity',
        'valid',
        'created_at' // Agregar esto si manejas created_at manualmente
    ];
    
    protected $casts = [
        'valid' => 'boolean',
        'created_at' => 'datetime',
    ];

    public function isExpired(): bool
    {
        if (!$this->created_at) {
            return true; 
        }
        
        return Carbon::now()->diffInMinutes($this->created_at) > $this->validity;
    }
}