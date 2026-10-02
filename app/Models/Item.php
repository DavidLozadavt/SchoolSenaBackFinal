<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Item extends Model
{
    protected $table = 'item';

    protected $fillable = [
        'nombreItem',
        'seleccionar',
        'descripcion',   // 🔥 nuevo
        'hora_inicio',   // 🔥 nuevo
        'hora_fin',      // 🔥 nuevo
        'idEvento',      // 🔗 Relación con Evento
    ];

    protected $casts = [
        'id' => 'integer',
        'seleccionar' => 'boolean',
        'hora_inicio' => 'datetime',
        'hora_fin' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function hermanos()
    {
        return $this->belongsToMany(
            Hermano::class,
            'ejecucionitem',
            'idItem',
            'idHermano'
        )->withPivot(['recibido', 'fecha_scan']);
    }

    public function ejecuciones()
    {
        return $this->hasMany(EjecucionItem::class, 'idItem');
    }

    public function evento()
    {
        return $this->belongsTo(Evento::class, 'idEvento');
    }
}
