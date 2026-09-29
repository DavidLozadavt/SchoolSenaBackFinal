<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Solicitud de compra de un plan de mensajes hecha por un usuario.
 */
class SolicitudPlanMensaje extends Model
{
    use HasFactory;

    public const PENDIENTE = 'PENDIENTE';
    public const APROBADA  = 'APROBADA';
    public const RECHAZADA = 'RECHAZADA';

    /**
     * Pago confirmado por Wompi, pendiente de que el Administrador VT active el
     * plan. Estado inicial de las solicitudes creadas por la pasarela; las del
     * flujo manual con comprobante siguen naciendo como PENDIENTE.
     */
    public const PAGO_REALIZADO = 'PAGO_REALIZADO';

    /** Estados que el Administrador VT puede aprobar o rechazar. */
    public const ESTADOS_REVISABLES = [self::PENDIENTE, self::PAGO_REALIZADO];

    public static $snakeAttributes = false;

    protected $table = 'solicitudesPlanMensajes';

    protected $fillable = [
        'userId',
        'companyId',
        'planId',
        'planNombre',
        'cantidadMensajes',
        'valor',
        'metodoPago',
        'comprobanteRuta',
        'comprobanteNombre',
        'estado',
        'motivoRechazo',
        'revisadoPor',
        'fechaRevision',
        // Pago por Wompi (aditivo; NULL en el flujo manual con comprobante).
        'wompiTransaccionId',
        'estadoPago',
        'referenciaPago',
    ];

    protected $casts = [
        'cantidadMensajes' => 'integer',
        'valor'            => 'decimal:2',
        'fechaRevision'    => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'userId');
    }

    public function plan()
    {
        return $this->belongsTo(MensajesPlan::class, 'planId');
    }

    public function empresa()
    {
        return $this->belongsTo(Company::class, 'companyId');
    }
}
