<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EjecucionItem extends Model
{
    protected $table = 'ejecucionItem';

    public $timestamps = false;

    protected $fillable = [
        'idHermano',
        'idItem',
        'recibido',
        'fecha_scan',
    ];

    protected $casts = [
        'id' => 'integer',
        'idHermano' => 'integer',
        'idItem' => 'integer',
        'recibido' => 'boolean',
        'fecha_scan' => 'datetime',
    ];

    public function hermano()
    {
        return $this->belongsTo(Hermano::class, 'idHermano');
    }

    public function item()
    {
        return $this->belongsTo(Item::class, 'idItem');
    }
}
