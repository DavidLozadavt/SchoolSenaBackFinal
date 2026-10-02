<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AgendaActa extends Model
{
    use HasFactory;

    protected $table = 'agendaActa';

    protected $fillable = [
        'idacta',
        'punto',
    ];

    protected $casts = [
        'idacta' => 'integer',
        'punto' => 'string',
    ];

    public function acta()
    {
        return $this->belongsTo(Acta::class, 'idacta');
    }
}
