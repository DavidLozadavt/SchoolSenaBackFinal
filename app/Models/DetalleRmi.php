<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// App/Models/DetalleRmi.php
class DetalleRmi extends Model
{
    protected $table = 'detalleRmi';

    protected $fillable = ['idRmi', 'idHorarioMateria', 'estado', 'observacion', 'estadoAsociacion', 'archivoPago', 'estadoInforme', 'urlInforme', 'numeroPlanilla'];

    protected $appends = ['archivoPagoUrl', 'urlInformeUrl'];

    public function getArchivoPagoUrlAttribute()
    {
        if (!empty($this->attributes['archivoPago'])) {
            return url($this->attributes['archivoPago']);
        }
        return null;
    }

    public function getUrlInformeUrlAttribute()
    {
        if (!empty($this->attributes['urlInforme'])) {
            return url($this->attributes['urlInforme']);
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
