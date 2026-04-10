<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// App/Models/DetalleRmi.php
class DetalleRmi extends Model
{
    protected $table = 'detalleRmi';

    protected $fillable = ['idRmi', 'idHorarioMateria', 'estado', 'observacion', 'estadoAsociacion', 'archivoPago'];

    protected $appends = ['archivoPagoUrl'];

    public function getArchivoPagoUrlAttribute()
    {
        if (!empty($this->attributes['archivoPago'])) {
            return url($this->attributes['archivoPago']);
        }
        return null;
    }

    public function rmi()
    {
        return $this->belongsTo(Rmi::class, 'idRmi');
    }

    public function horarioMateria()
    {
        return $this->belongsTo(HorarioMateria::class, 'idHorarioMateria');
    }
}
