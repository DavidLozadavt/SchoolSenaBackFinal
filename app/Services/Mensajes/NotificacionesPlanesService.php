<?php

namespace App\Services\Mensajes;

use App\Models\NotificacionPlan;
use Illuminate\Support\Facades\Log;

/**
 * Emisión de notificaciones in-app del módulo de Planes de Mensajes.
 *
 * Nunca lanza: una notificación fallida jamás debe interrumpir un pago, una
 * aprobación ni un envío. No usa correo.
 */
class NotificacionesPlanesService
{
    public function crear(
        int $userId,
        string $tipo,
        string $titulo,
        string $mensaje,
        string $nivel = 'info',
        array $contexto = []
    ): ?NotificacionPlan {
        try {
            return NotificacionPlan::create([
                'userId'       => $userId,
                'tipo'          => $tipo,
                'titulo'        => $titulo,
                'mensaje'       => $mensaje,
                'nivel'         => $nivel,
                'solicitudId'   => $contexto['solicitudId'] ?? null,
                'transaccionId' => $contexto['transaccionId'] ?? null,
                'datos'         => $contexto['datos'] ?? null,
            ]);
        } catch (\Throwable $e) {
            Log::warning('No se pudo crear la notificación de planes: ' . $e->getMessage());

            return null;
        }
    }

    public function pagoAprobado(int $userId, string $referencia, $valor, array $contexto = []): void
    {
        $this->crear(
            $userId,
            NotificacionPlan::PAGO_APROBADO,
            'Pago aprobado por Wompi',
            "Tu pago por {$this->moneda($valor)} (ref. {$referencia}) fue aprobado. La solicitud quedó pendiente de aprobación del Administrador VT.",
            'success',
            $contexto
        );
    }

    public function solicitudEnviada(int $userId, string $plan, array $contexto = []): void
    {
        $this->crear(
            $userId,
            NotificacionPlan::SOLICITUD_ENVIADA,
            'Solicitud enviada',
            "Tu solicitud del plan {$plan} fue registrada y está en revisión.",
            'info',
            $contexto
        );
    }

    public function solicitudAprobada(int $userId, string $plan, array $contexto = []): void
    {
        $this->crear(
            $userId,
            NotificacionPlan::SOLICITUD_APROBADA,
            'Solicitud aprobada',
            "El Administrador VT aprobó tu solicitud del plan {$plan}.",
            'success',
            $contexto
        );
    }

    public function solicitudRechazada(int $userId, string $plan, string $motivo, array $contexto = []): void
    {
        $this->crear(
            $userId,
            NotificacionPlan::SOLICITUD_RECHAZADA,
            'Solicitud rechazada',
            "Tu solicitud del plan {$plan} fue rechazada. Motivo: {$motivo}",
            'danger',
            $contexto
        );
    }

    public function planActivado(int $userId, string $plan, array $contexto = []): void
    {
        $this->crear(
            $userId,
            NotificacionPlan::PLAN_ACTIVADO,
            'Plan activado',
            "El plan {$plan} ya está activo en tu cuenta.",
            'success',
            $contexto
        );
    }

    public function mensajesAcreditados(int $userId, int $cantidad, int $saldoNuevo, array $contexto = []): void
    {
        $this->crear(
            $userId,
            NotificacionPlan::MENSAJES_ACREDITADOS,
            'Mensajes acreditados',
            "Se acreditaron {$cantidad} mensajes a tu cuenta. Saldo disponible: {$saldoNuevo}.",
            'success',
            $contexto
        );
    }

    public function saldoInsuficiente(int $userId, int $requeridos, int $disponibles): void
    {
        $this->crear(
            $userId,
            NotificacionPlan::SALDO_INSUFICIENTE,
            'Saldo insuficiente',
            "Intentaste enviar {$requeridos} mensajes y solo dispones de {$disponibles}. No se envió ningún mensaje.",
            'warning'
        );
    }

    private function moneda($valor): string
    {
        return '$' . number_format((float) $valor, 0, ',', '.');
    }
}
