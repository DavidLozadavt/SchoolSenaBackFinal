<?php

namespace App\Models\Transporte;

use App\Models\FacturaElectronica;
use App\Models\Ruta;
use App\Models\Tercero;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Ticket extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $table = 'tickets';

    public function viaje(): BelongsTo
    {
        return $this->belongsTo(Viaje::class, 'idViaje');
    }

    public function tercero(): BelongsTo
    {
        return $this->belongsTo(Tercero::class,'idTercero');
    }

    public function configuracionVehiculo(): BelongsTo
    {
        return $this->belongsTo(ConfiguracionVehiculo::class,'idConfiguracionVehiculo');
    }

    public function agendaViaje(): BelongsTo
    {
        return $this->belongsTo(AgendarViaje::class,'idAgendaViaje');
    }

    public function ruta()
    {
        return $this->belongsTo(Ruta::class, 'idRuta');
    }

    public function facturaElectronica()
    {
        return $this->hasOne(FacturaElectronica::class, 'ticket_id');
    }  

}
