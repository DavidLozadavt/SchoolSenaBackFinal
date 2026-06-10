<?php

namespace App\Http\Controllers;

use App\Http\Controllers\gestion_pago\PagoController;
use App\Models\MedioPago;
use App\Models\TipoPago;
use App\Services\EmpresaPasarelaPagoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Webhook y utilidades WOMPI multiempresa (credenciales vía ERP).
 * El registro financiero delega en PagoController::aplicarPagoFacturaAcademicaInterno().
 */
class WompiWebhookController extends Controller
{
    protected EmpresaPasarelaPagoService $pasarelaPagoService;

    public function __construct(EmpresaPasarelaPagoService $pasarelaPagoService)
    {
        $this->pasarelaPagoService = $pasarelaPagoService;
    }

    /**
     * POST /api/webhooks/wompi
     */
    public function handle(Request $request)
    {
        $event = $request->all();

        Log::info('WompiWebhook: evento recibido', [
            'event_type' => $event['event'] ?? 'desconocido',
            'reference'  => $event['data']['transaction']['reference'] ?? null,
        ]);

        if (($event['event'] ?? '') !== 'transaction.updated') {
            return response()->json(['status' => 'ignored', 'reason' => 'event_not_transaction'], 200);
        }

        $transaction = $event['data']['transaction'] ?? null;
        if (!$transaction) {
            return response()->json(['status' => 'error', 'reason' => 'no_transaction_data'], 422);
        }

        $reference = $transaction['reference'] ?? null;
        $status    = $transaction['status'] ?? null;

        if ($status !== 'APPROVED') {
            Log::info("WompiWebhook: transacción no aprobada ({$status}), ignorando.");
            return response()->json(['status' => 'ignored', 'reason' => 'not_approved'], 200);
        }

        $parsed = $this->pasarelaPagoService->parsearReferencia($reference ?? '');
        if (!$parsed) {
            Log::warning("WompiWebhook: referencia inválida: {$reference}");
            return response()->json(['status' => 'error', 'reason' => 'invalid_reference'], 422);
        }

        $idFactura = (int) $parsed['idFactura'];
        $idEmpresa = (int) $parsed['idEmpresa'];

        if (!$this->pasarelaPagoService->validarFirmaWebhook($idEmpresa, $event)) {
            Log::warning("WompiWebhook: firma inválida para empresa {$idEmpresa}");
            return response()->json(['status' => 'error', 'reason' => 'invalid_signature'], 401);
        }

        $transactionId = (string) ($transaction['id'] ?? '');
        if ($transactionId === '') {
            return response()->json(['status' => 'error', 'reason' => 'missing_transaction_id'], 422);
        }

        if ($this->transaccionYaProcesada($transactionId)) {
            Log::info("WompiWebhook: transacción {$transactionId} ya procesada, ignorando duplicado.");
            return response()->json(['status' => 'already_processed'], 200);
        }

        $monto = round((($transaction['amount_in_cents'] ?? 0) / 100), 2);
        $currency = $transaction['currency'] ?? 'COP';
        $paymentMethodType = $transaction['payment_method_type'] ?? 'UNKNOWN';

        $auditoriaId = $this->registrarAuditoriaPendiente([
            'idFactura'     => $idFactura,
            'idEmpresa'     => $idEmpresa,
            'referencia'    => $reference,
            'transactionId' => $transactionId,
            'amount'        => $monto,
            'currency'      => $currency,
            'provider'      => 'WOMPI',
            'payload'       => $this->sanitizarPayloadAuditoria($event, $transaction),
        ]);

        try {
            /** @var PagoController $pagoController */
            $pagoController = app(PagoController::class);

            $resultado = $pagoController->aplicarPagoFacturaAcademicaInterno(
                $idFactura,
                $idEmpresa,
                $this->resolverMedioPago($paymentMethodType),
                $this->resolverTipoPagoContado(),
                $monto > 0 ? $monto : null,
                'WOMPI_WEBHOOK:' . $transactionId
            );

            if ($resultado['httpStatus'] >= 400) {
                $this->actualizarAuditoria($auditoriaId, 'ERROR', $resultado['payload']['error'] ?? 'Error al aplicar pago');

                return response()->json([
                    'status' => 'error',
                    'reason' => $resultado['payload']['error'] ?? 'payment_failed',
                ], $resultado['httpStatus']);
            }

            $this->actualizarAuditoria($auditoriaId, 'PROCESADO');

            Log::info("WompiWebhook: pago aplicado sobre factura {$idFactura} (empresa {$idEmpresa}). Monto: {$monto}");

            return response()->json([
                'status'  => 'ok',
                'message' => $resultado['payload']['message'] ?? 'Pago registrado correctamente',
            ]);
        } catch (\Throwable $e) {
            Log::error('WompiWebhook: error al procesar pago: ' . $e->getMessage(), [
                'idFactura' => $idFactura,
                'idEmpresa' => $idEmpresa,
            ]);

            $this->actualizarAuditoria($auditoriaId, 'ERROR', substr($e->getMessage(), 0, 500));

            return response()->json(['status' => 'error', 'reason' => 'processing_error'], 500);
        }
    }

    /**
     * GET /api/wompi-config/{idEmpresa}
     */
    public function getPublicConfig(int $idEmpresa)
    {
        $config = $this->pasarelaPagoService->getPublicConfig($idEmpresa);

        if (!$config) {
            return response()->json([
                'message' => 'No hay configuración de pasarela activa para esta empresa.',
            ], 404);
        }

        return response()->json($config);
    }

    /**
     * POST /api/wompi-integrity
     */
    public function generarFirmaIntegridad(Request $request)
    {
        $request->validate([
            'idEmpresa'      => 'required|integer',
            'idFactura'      => 'required|integer',
            'amountInCents'  => 'required|integer|min:1',
            'currency'       => 'nullable|string|max:3',
        ]);

        $idEmpresa     = $request->integer('idEmpresa');
        $idFactura     = $request->integer('idFactura');
        $amountInCents = $request->integer('amountInCents');
        $currency      = $request->input('currency', 'COP');

        $reference = $this->pasarelaPagoService->generarReferencia($idFactura, $idEmpresa);

        $firma = $this->pasarelaPagoService->generarFirmaIntegridad(
            $idEmpresa,
            $reference,
            $amountInCents,
            $currency
        );

        if (!$firma) {
            return response()->json([
                'message' => 'No se pudo generar la firma. Verifique la configuración de Wompi.',
            ], 500);
        }

        return response()->json([
            'reference'      => $reference,
            'integrityHash'  => $firma,
            'amountInCents'  => $amountInCents,
            'currency'       => $currency,
        ]);
    }

    protected function transaccionYaProcesada(string $transactionId): bool
    {
        if (!Schema::hasTable('wompiAuditoriaPago') || !Schema::hasColumn('wompiAuditoriaPago', 'transactionId')) {
            return false;
        }

        return DB::table('wompiAuditoriaPago')
            ->where('transactionId', $transactionId)
            ->where('status', 'PROCESADO')
            ->exists();
    }

    protected function registrarAuditoriaPendiente(array $data): ?int
    {
        if (!Schema::hasTable('wompiAuditoriaPago')) {
            return null;
        }

        $row = array_merge($data, [
            'status'     => 'PENDIENTE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if (!Schema::hasColumn('wompiAuditoriaPago', 'transactionId')) {
            unset($row['transactionId'], $row['referencia'], $row['idFactura'], $row['idEmpresa']);
        }

        return DB::table('wompiAuditoriaPago')->insertGetId($row);
    }

    protected function actualizarAuditoria(?int $auditoriaId, string $status, ?string $observacion = null): void
    {
        if ($auditoriaId === null || !Schema::hasTable('wompiAuditoriaPago')) {
            return;
        }

        $update = [
            'status'     => $status,
            'updated_at' => now(),
        ];

        if ($observacion !== null && Schema::hasColumn('wompiAuditoriaPago', 'observacion')) {
            $update['observacion'] = $observacion;
        }

        DB::table('wompiAuditoriaPago')->where('id', $auditoriaId)->update($update);
    }

    /**
     * Payload reducido sin datos sensibles.
     */
    protected function sanitizarPayloadAuditoria(array $event, array $transaction): string
    {
        return json_encode([
            'event' => $event['event'] ?? null,
            'transaction' => [
                'id'                  => $transaction['id'] ?? null,
                'status'              => $transaction['status'] ?? null,
                'reference'           => $transaction['reference'] ?? null,
                'amount_in_cents'     => $transaction['amount_in_cents'] ?? null,
                'currency'            => $transaction['currency'] ?? null,
                'payment_method_type' => $transaction['payment_method_type'] ?? null,
            ],
        ]);
    }

    protected function resolverMedioPago(string $paymentMethodType): int
    {
        $type = strtoupper($paymentMethodType);

        $id = match ($type) {
            'PSE' => MedioPago::TRANSFERENCIA,
            'CARD', 'CREDIT_CARD' => MedioPago::where('detalleMedioPago', 'TARJETA CREDITO')->value('id')
                ?? MedioPago::where('detalleMedioPago', 'TARJETA DEBITO')->value('id'),
            'NEQUI' => MedioPago::TRANSFERENCIA,
            default => MedioPago::TRANSFERENCIA,
        };

        if ($id) {
            return (int) $id;
        }

        Log::warning("WompiWebhook: medio de pago no encontrado para tipo {$type}");

        return MedioPago::EFECTIVO;
    }

    protected function resolverTipoPagoContado(): ?int
    {
        $id = TipoPago::where('detalleTipoPago', 'CONTADO')->value('id');

        return $id ? (int) $id : null;
    }
}
