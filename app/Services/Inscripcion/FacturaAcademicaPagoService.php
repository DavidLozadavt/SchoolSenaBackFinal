<?php

namespace App\Services\Inscripcion;

use App\Models\Factura;
use App\Models\Pago;
use App\Models\Status;
use App\Models\Transaccion;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class FacturaAcademicaPagoService
{
    /**
     * Registra abono contra la transacción/pago existentes de una factura académica.
     * Fuente de verdad financiera: factura + transaccion + pagos.
     */
    public function registrarPago(
        Factura $factura,
        int $idMedioPago,
        ?int $idTipoPago = null,
        ?float $valorAbono = null,
        ?string $rutaComprobante = null
    ): array {
        $factura->loadMissing(['transacciones.pago']);

        $transaccion = $factura->transacciones->first();
        if (!$transaccion) {
            throw new \RuntimeException('La factura no tiene transacción asociada.');
        }

        /** @var Pago|null $pago */
        $pago = $transaccion->pago->first();
        if (!$pago) {
            throw new \RuntimeException('La transacción no tiene registro de pago.');
        }

        if ((int) $pago->idEstado === Status::ID_APROBADO && (float) $pago->excedente <= 0) {
            return [
                'yaPagada' => true,
                'idTransaccion' => $transaccion->id,
                'pago' => $pago,
            ];
        }

        $valorAbono = $valorAbono ?? (float) $pago->excedente;
        if ($valorAbono <= 0 || (float) $pago->excedente <= 0) {
            throw new \RuntimeException('No hay saldo pendiente por registrar.');
        }

        DB::beginTransaction();

        try {
            $restarExcedente = min((float) $pago->excedente, $valorAbono);
            $pago->excedente = round((float) $pago->excedente - $restarExcedente, 2);
            $pago->valor = round((float) $pago->valor + $restarExcedente, 2);
            $pago->fechaPago = Carbon::now()->format('Y-m-d');
            $pago->fechaReg = Carbon::now()->format('Y-m-d');
            $pago->idMedioPago = $idMedioPago;

            if ($factura->numeroFactura) {
                $pago->numeroFact = $factura->numeroFactura;
            }

            if ($rutaComprobante) {
                $pago->rutaComprobante = $rutaComprobante;
            }

            if ((float) $pago->excedente <= 0) {
                $pago->idEstado = Status::ID_APROBADO;
            }

            $pago->save();

            if ($idTipoPago) {
                $transaccion->idTipoPago = $idTipoPago;
            }

            if (isset($transaccion->excedente) && (float) $transaccion->excedente > 0) {
                $transaccion->excedente = max(
                    0,
                    round((float) $transaccion->excedente - $restarExcedente, 2)
                );
            }

            if ((float) $pago->excedente <= 0) {
                $transaccion->idEstado = Status::ID_APROBADO;
            }

            $transaccion->save();

            DB::commit();

            return [
                'yaPagada' => false,
                'idTransaccion' => $transaccion->id,
                'pago' => $pago->fresh(),
            ];
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function facturaEstaPagada(Factura $factura): bool
    {
        $factura->loadMissing(['transacciones.pago']);
        $transaccion = $factura->transacciones->first();
        $pago = $transaccion?->pago?->first();

        return $pago
            && (int) $pago->idEstado === Status::ID_APROBADO
            && (float) $pago->excedente <= 0;
    }
}
