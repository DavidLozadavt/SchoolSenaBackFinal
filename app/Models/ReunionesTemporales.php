<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReunionesTemporales extends Model
{
    use HasFactory;

 protected $fillable = ['codigo', 'room_name', 'nombre', 'created_by', 'expires_at', 'start_at'];
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'expires_at' => 'datetime',
        'start_at' => 'datetime'
    ];

    protected $table = 'reuniones_temporales';
}
