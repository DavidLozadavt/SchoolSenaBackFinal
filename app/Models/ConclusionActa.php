<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConclusionActa extends Model
{
    use HasFactory;

    protected $table = 'conclusionActa';

    protected $fillable = [
        'idacta',
        'conclusion',
    ];

    protected $casts = [
        'idacta' => 'integer',
        'conclusion' => 'string',
    ];

    public function acta()
    {
        return $this->belongsTo(Acta::class, 'idacta');
    }
}
