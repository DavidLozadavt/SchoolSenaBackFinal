<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ObjetivoActa extends Model
{
    use HasFactory;

    protected $table = 'objetivoActa';

    protected $fillable = [
        'idacta',
        'objetivo',
    ];

    protected $casts = [
        'idacta' => 'integer',
        'objetivo' => 'string',
    ];

    public function acta()
    {
        return $this->belongsTo(Acta::class, 'idacta');
    }
}
