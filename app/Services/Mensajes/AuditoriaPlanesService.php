<?php

namespace App\Services\Mensajes;

use App\Models\AuditoriaPlanMensaje;
use Illuminate\Support\Facades\Log;

/**
 * Registro enriquecido de la auditoría de planes (Mejora 5).
 *
 * Complementa —sin reemplazar— las llamadas directas a
 * `AuditoriaPlanMensaje::create()` que ya existían: añade IP, User Agent,
 * datos del plan, referencias de pago y transición de estados.
 *
 * Nunca lanza: la auditoría no debe tumbar la operación auditada.
 */
class AuditoriaPlanesService
{
    /**
     * @param  array  $datos  Cualquier columna de `auditoriaPlanesMensajes`.
     */
    public function registrar(string $accion, int $userId, array $datos = []): ?AuditoriaPlanMensaje
    {
        try {
            $request = request();

            return AuditoriaPlanMensaje::create(array_merge([
                'userId'      => $userId,
                'accion'       => $accion,
                'realizadoPor' => auth()->id(),
                'ip'           => $request?->ip(),
                'userAgent'    => substr((string) $request?->userAgent(), 0, 512),
            ], $datos));
        } catch (\Throwable $e) {
            Log::warning('No se pudo registrar la auditoría de planes: ' . $e->getMessage());

            return null;
        }
    }

    /**
     * Incidente de seguridad detectado al validar un pago (Mejora 1).
     */
    public function incidentePago(int $userId, string $motivo, array $datos = []): void
    {
        Log::warning('Validación de pago Wompi fallida: ' . $motivo, $datos);

        $this->registrar('PAGO_VALIDACION_FALLIDA', $userId, array_merge([
            'descripcion'   => 'Pago rechazado por validación de seguridad.',
            'observaciones' => $motivo,
        ], $datos));
    }
}
