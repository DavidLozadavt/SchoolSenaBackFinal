<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConfiguracionRepeatCard extends Model
{
    use HasFactory;

    protected $table = "configuracionRepeatCard";

    
    protected $fillable = [
        "configuracion", 
        "value"
    ];
    
}
