<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AsignacionTerceroTipoTercero extends Model
{
    use HasFactory;

    protected $table = 'asignacionTerceroTipoTercero';

    protected $fillable = [
        'idTercero',
        'idTipoTercero',
    ];


    public function tercero()
    {
        return $this->belongsTo(Tercero::class, 'idTercero');
    }

    public function tipo()
    {
        return $this->belongsTo(TipoTercero::class, 'idTipoTercero');
    }
}
