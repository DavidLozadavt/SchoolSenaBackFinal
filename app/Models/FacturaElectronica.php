<?php

namespace App\Models;

use App\Models\Transporte\Ticket;
use App\Models\Transporte\Viaje;
use Illuminate\Database\Eloquent\Model;

class FacturaElectronica extends Model
{

     const RUTA_LOGO_DEFAULT = "/default/logoweb.png";
    const VIRTUALT = 1;

    // Nombre de la tabla (asegúrate de que coincida con tu BD)
    protected $table = 'facturasElectronicas';
 /**
     * Campos que se pueden asignar masivamente
     */
       protected $fillable = [
        'ticket_id', 'reference_code', 'factus_id', 'prefix', 'number',
        'cufe', 'status', 'email_status', 'pdf_path', 'xml_path', 'qr_url',
        'error_code', 'error_message', 'validated_at', 'sent_at', 'idEmpresa'
    ];

    /**
     * Conversión automática de tipos
     */
    protected $casts = [
        'validated_at' => 'datetime',
        'sent_at' => 'datetime',
    ];

    /**
     * Relación con el ticket de venta.
     */
    public function ticket()
    {
        return $this->belongsTo(Ticket::class, 'ticket_id');
    }

    /**
     * Relación con el cliente (tercero) a través del ticket.
     */
    public function tercero()
    {
        return $this->hasOneThrough(
            Tercero::class,
            Ticket::class,
            'id',          // Foreign key en tickets
            'id',          // Foreign key en tercero
            'ticket_id',   // Local key en invoice
            'idTercero'    // Local key en ticket
        );
    }

    /**
     * Retorna la URL temporal para descargar el PDF desde el backend.
     */
    public function getPdfUrlAttribute()
    {
        return route('invoices.download.pdf', ['id' => $this->id]);
    }

    /**
     * Retorna el número completo con prefijo (ej: FE145).
     */
    public function getFullNumberAttribute()
    {
        return "{$this->prefix}{$this->number}";
    }

    /**
     * Determina si la factura fue validada ante la DIAN.
     */
    public function getIsValidatedAttribute()
    {
        return $this->status === 'validated' || $this->status === 'success';
    }

     protected $appends = ['qr_image'];

    public function getQrImageAttribute()
    {
        if (
            isset($this->attributes['qr_image']) &&
            isset($this->attributes['qr_image'][0])
        ) {
            return url($this->attributes['qr_image']);
        }
        return url(self::RUTA_LOGO_DEFAULT);
    }
    
}