<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CompromisoActa extends Model
{
    use HasFactory;

    protected $table = 'compromisoActa';

    protected $fillable = [
        'idacta',
        'actividad',
        'fecha',
        'responsable',
        'firma',
    ];

    protected $casts = [
        'idacta' => 'integer',
        'actividad' => 'string',
        'fecha' => 'date',
        'responsable' => 'string',
        'firma' => 'string',
    ];

    public function acta()
    {
        return $this->belongsTo(Acta::class, 'idacta');
    }
}
