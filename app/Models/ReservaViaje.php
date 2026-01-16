<?php

namespace App\Models;

use App\Models\Transporte\AgendarViaje;
use App\Models\Transporte\Viaje;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class ReservaViaje extends Model
{
    use HasFactory;

    protected $table = 'reservaViajes';

    protected $guarded = [];

    protected $casts = [
        'cantidad' => 'integer',
        'valor' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $appends = ['url_qr']; 

 
    public function viaje()
    {
        return $this->belongsTo(Viaje::class, 'idViaje');
    }

    public function tercero()
    {
        return $this->belongsTo(Tercero::class, 'idTercero');
    }

    public function agendaViaje()
    {
        return $this->belongsTo(AgendarViaje::class, 'idAgendaViaje');
    }

    public function ruta()
    {
        return $this->belongsTo(Ruta::class, 'idRuta');
    }

    
    public function scopePorEstado($query, $estado)
    {
        return $query->where('estado', $estado);
    }

    public function scopeReservadas($query)
    {
        return $query->where('estado', 'RESERVADO');
    }

    public function scopeVendidas($query)
    {
        return $query->where('estado', 'VENDIDO');
    }

    public function scopePorDespachar($query)
    {
        return $query->where('estado', 'PORDESPACHAR');
    }

  
    public function getUrlQrAttribute()
    {
        if (!empty($this->qrPath) && Storage::disk('public')->exists($this->qrPath)) {
            return asset('storage/' . $this->qrPath);
        }

        return asset('default/qr-placeholder.png');
    }

   
    public function getUrlBuscarCodigoAttribute()
    {
        return route('reservas.buscar-codigo', $this->codigo);
    }
}
