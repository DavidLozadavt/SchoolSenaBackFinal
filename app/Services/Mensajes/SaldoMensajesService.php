<?php

namespace App\Services\Mensajes;

use App\Models\AuditoriaPlanMensaje;
use App\Models\MensajesPlan;
use App\Models\SolicitudPlanMensaje;
use App\Models\UsuarioMensajeMovimiento;
use App\Models\UsuarioMensajesSaldo;
use Illuminate\Support\Facades\DB;

/**
 * Lógica de saldo de mensajes de WhatsApp por USUARIO.
 *
 * Reglas:
 *  - Cada usuario arranca con 10 mensajes gratuitos (se crean de forma perezosa).
 *  - Un envío se permite SOLO si hay saldo suficiente para TODOS los destinatarios.
 *    Nunca se descuenta parcialmente.
 */
class SaldoMensajesService
{
    /**
     * Devuelve (creando si hace falta) el saldo del usuario.
     */
    public function obtenerSaldo(int $userId): UsuarioMensajesSaldo
    {
        $saldo = UsuarioMensajesSaldo::firstOrCreate(
            ['userId' => $userId],
            [
                'mensajesGratuitos'   => UsuarioMensajesSaldo::MENSAJES_GRATUITOS,
                'mensajesDisponibles' => UsuarioMensajesSaldo::MENSAJES_GRATUITOS,
                'mensajesConsumidos'  => 0,
            ]
        );

        // Historial (aditivo): la asignación de los mensajes gratuitos al crear el
        // saldo del usuario también queda registrada como movimiento.
        if ($saldo->wasRecentlyCreated) {
            $this->registrarMovimiento(
                $userId,
                UsuarioMensajeMovimiento::GRATUITO_INICIAL,
                $saldo->mensajesGratuitos,
                0,
                $saldo->mensajesDisponibles,
                'Asignación automática de mensajes gratuitos al usuario.',
                'usuario:' . $userId
            );
        }

        return $saldo;
    }

    /**
     * Registra un movimiento en el historial append-only. Nunca actualiza filas
     * existentes y jamás interrumpe la operación principal si algo falla.
     */
    public function registrarMovimiento(
        int $userId,
        string $tipo,
        int $cantidad,
        int $saldoAnterior,
        int $saldoNuevo,
        ?string $descripcion = null,
        ?string $referencia = null
    ): void {
        try {
            UsuarioMensajeMovimiento::create([
                'userId'         => $userId,
                'tipoMovimiento' => $tipo,
                'cantidad'        => $cantidad,
                'saldoAnterior'  => $saldoAnterior,
                'saldoNuevo'     => $saldoNuevo,
                'descripcion'     => $descripcion,
                'referencia'      => $referencia,
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning(
                'No se pudo registrar el movimiento de mensajes: ' . $e->getMessage()
            );
        }
    }

    /**
     * Historial de movimientos de un usuario (más recientes primero).
     */
    public function movimientos(int $userId, int $limite = 100)
    {
        return UsuarioMensajeMovimiento::where('userId', $userId)
            ->orderByDesc('id')
            ->limit($limite)
            ->get();
    }

    /**
     * Suma mensajes por bonificación o ajuste manual de un administrador.
     * Registra el movimiento correspondiente.
     */
    public function acreditarManual(
        int $userId,
        int $cantidad,
        string $tipo = UsuarioMensajeMovimiento::BONIFICACION,
        ?string $descripcion = null,
        ?string $referencia = null
    ): UsuarioMensajesSaldo {
        return DB::transaction(function () use ($userId, $cantidad, $tipo, $descripcion, $referencia) {
            $saldo = $this->obtenerSaldo($userId);
            $saldo = UsuarioMensajesSaldo::lockForUpdate()->find($saldo->id);

            $antes = $saldo->mensajesDisponibles;

            $saldo->mensajesDisponibles = max(0, $antes + $cantidad);
            $saldo->save();

            $this->registrarMovimiento(
                $userId,
                $tipo,
                $cantidad,
                $antes,
                $saldo->mensajesDisponibles,
                $descripcion ?? "Movimiento manual de {$cantidad} mensajes.",
                $referencia
            );

            return $saldo->fresh();
        });
    }

    /**
     * Resumen para la interfaz (indicadores encima del botón "Enviar WhatsApp").
     */
    public function resumen(int $userId): array
    {
        $saldo = $this->obtenerSaldo($userId);
        $plan  = $saldo->planActivoId ? MensajesPlan::find($saldo->planActivoId) : null;

        return [
            'mensajesGratuitos'   => $saldo->mensajesGratuitos,
            'mensajesDisponibles' => $saldo->mensajesDisponibles,
            'mensajesConsumidos'  => $saldo->mensajesConsumidos,
            'planActivo'          => $plan ? [
                'id'               => $plan->id,
                'nombre'           => $plan->nombre,
                'cantidadMensajes' => $plan->cantidadMensajes,
            ] : null,
            'fechaActivacionPlan' => $saldo->fechaActivacionPlan,
            'tieneSolicitudPendiente' => SolicitudPlanMensaje::where('userId', $userId)
                ->where('estado', SolicitudPlanMensaje::PENDIENTE)
                ->exists(),
        ];
    }

    /**
     * ¿El usuario tiene saldo para enviar exactamente $cantidad mensajes?
     */
    public function tieneSaldoSuficiente(int $userId, int $cantidad): bool
    {
        return $this->obtenerSaldo($userId)->mensajesDisponibles >= $cantidad;
    }

    /**
     * Descuenta $cantidad mensajes de forma atómica. Devuelve false (sin descontar)
     * si no alcanza el saldo — todo o nada.
     */
    public function consumir(int $userId, int $cantidad, ?string $descripcion = null, ?string $referencia = null): bool
    {
        if ($cantidad <= 0) {
            return true;
        }

        return DB::transaction(function () use ($userId, $cantidad, $descripcion, $referencia) {
            $saldo = $this->obtenerSaldo($userId);
            $saldo = UsuarioMensajesSaldo::lockForUpdate()->find($saldo->id);

            if (!$saldo || $saldo->mensajesDisponibles < $cantidad) {
                return false;
            }

            $antes = $saldo->mensajesDisponibles;

            $saldo->mensajesDisponibles -= $cantidad;
            $saldo->mensajesConsumidos  += $cantidad;
            $saldo->save();

            AuditoriaPlanMensaje::create([
                'userId'         => $userId,
                'accion'          => AuditoriaPlanMensaje::MENSAJES_CONSUMIDOS,
                'descripcion'     => $descripcion ?? "Consumo de {$cantidad} mensajes.",
                'mensajesAntes'   => $antes,
                'mensajesDespues' => $saldo->mensajesDisponibles,
                'realizadoPor'    => $userId,
                'detalle'         => ['cantidad' => $cantidad],
            ]);

            $this->registrarMovimiento(
                $userId,
                UsuarioMensajeMovimiento::CONSUMO,
                $cantidad,
                $antes,
                $saldo->mensajesDisponibles,
                $descripcion ?? "Consumo de {$cantidad} mensajes.",
                $referencia
            );

            return true;
        });
    }

    /**
     * Devuelve al saldo mensajes reservados que no llegaron a enviarse.
     */
    public function devolver(int $userId, int $cantidad, ?string $descripcion = null, ?string $referencia = null): void
    {
        if ($cantidad <= 0) {
            return;
        }

        DB::transaction(function () use ($userId, $cantidad, $descripcion, $referencia) {
            $saldo = $this->obtenerSaldo($userId);
            $saldo = UsuarioMensajesSaldo::lockForUpdate()->find($saldo->id);

            if (!$saldo) {
                return;
            }

            $antes = $saldo->mensajesDisponibles;

            $saldo->mensajesDisponibles += $cantidad;
            $saldo->mensajesConsumidos   = max(0, $saldo->mensajesConsumidos - $cantidad);
            $saldo->save();

            AuditoriaPlanMensaje::create([
                'userId'         => $userId,
                'accion'          => AuditoriaPlanMensaje::MENSAJES_CONSUMIDOS,
                'descripcion'     => $descripcion ?? "Devolución de {$cantidad} mensajes.",
                'mensajesAntes'   => $antes,
                'mensajesDespues' => $saldo->mensajesDisponibles,
                'realizadoPor'    => $userId,
                'detalle'         => ['devolucion' => $cantidad],
            ]);

            $this->registrarMovimiento(
                $userId,
                UsuarioMensajeMovimiento::REVERSO,
                $cantidad,
                $antes,
                $saldo->mensajesDisponibles,
                $descripcion ?? "Devolución de {$cantidad} mensajes.",
                $referencia
            );
        });
    }

    /**
     * Acredita los mensajes de un plan aprobado y lo marca como plan activo.
     */
    public function acreditarPlan(SolicitudPlanMensaje $solicitud, ?int $aprobadoPor = null): UsuarioMensajesSaldo
    {
        return DB::transaction(function () use ($solicitud, $aprobadoPor) {
            $saldo = $this->obtenerSaldo($solicitud->userId);
            $saldo = UsuarioMensajesSaldo::lockForUpdate()->find($saldo->id);

            $antes = $saldo->mensajesDisponibles;

            $saldo->mensajesDisponibles += $solicitud->cantidadMensajes;
            $saldo->planActivoId         = $solicitud->planId;
            $saldo->fechaActivacionPlan  = now();
            $saldo->save();

            AuditoriaPlanMensaje::create([
                'userId'         => $solicitud->userId,
                'solicitudId'     => $solicitud->id,
                'accion'          => AuditoriaPlanMensaje::SOLICITUD_APROBADA,
                'descripcion'     => "Plan {$solicitud->planNombre} aprobado: +{$solicitud->cantidadMensajes} mensajes.",
                'mensajesAntes'   => $antes,
                'mensajesDespues' => $saldo->mensajesDisponibles,
                'realizadoPor'    => $aprobadoPor,
                'detalle'         => [
                    'planId'           => $solicitud->planId,
                    'planNombre'       => $solicitud->planNombre,
                    'cantidadMensajes' => $solicitud->cantidadMensajes,
                    'valor'            => $solicitud->valor,
                ],
            ]);

            $this->registrarMovimiento(
                $solicitud->userId,
                UsuarioMensajeMovimiento::RECARGA,
                $solicitud->cantidadMensajes,
                $antes,
                $saldo->mensajesDisponibles,
                "Recarga por compra aprobada del plan {$solicitud->planNombre}.",
                'solicitud:' . $solicitud->id
            );

            return $saldo->fresh();
        });
    }
}
