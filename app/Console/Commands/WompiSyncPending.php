<?php

namespace App\Console\Commands;

use App\Models\WompiTransaccion;
use App\Services\Pagos\WompiService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Sincroniza contra la API oficial de Wompi las transacciones que siguen en
 * estado PENDING (Mejora 3).
 *
 * Reutiliza el mismo camino que el webhook: consulta el estado real y, si el
 * pago quedó aprobado y supera todas las validaciones, se crea la solicitud
 * para el Administrador VT. La activación del plan sigue siendo manual.
 */
class WompiSyncPending extends Command
{
    protected $signature = 'wompi:sync-pending
                            {--horas=72 : Antigüedad máxima en horas de las transacciones a revisar}
                            {--limite=200 : Máximo de transacciones por ejecución}';

    protected $description = 'Consulta en Wompi las transacciones PENDING y actualiza su estado.';

    public function handle(WompiService $wompi): int
    {
        if (!$wompi->estaConfigurado()) {
            $this->error('La pasarela Wompi no está configurada. Revise las llaves en el .env.');
            Log::warning('wompi:sync-pending abortado: pasarela no configurada.');

            return self::FAILURE;
        }

        $horas  = (int) $this->option('horas');
        $limite = (int) $this->option('limite');

        $pendientes = WompiTransaccion::where('status', WompiTransaccion::PENDING)
            ->where('created_at', '>=', now()->subHours($horas))
            ->orderBy('id')
            ->limit($limite)
            ->get();

        $revisadas    = $pendientes->count();
        $actualizadas = 0;
        $errores      = 0;

        $this->info("Transacciones PENDING a revisar: {$revisadas}");

        foreach ($pendientes as $transaccion) {
            try {
                $resultado = $transaccion->transactionId
                    ? $wompi->consultarTransaccion($transaccion->transactionId)
                    : $wompi->consultarPorReferencia($transaccion->reference);

                if (!$resultado['ok']) {
                    $errores++;
                    $this->warn("  [{$transaccion->reference}] {$resultado['error']}");
                    Log::warning('wompi:sync-pending error al consultar.', [
                        'reference' => $transaccion->reference,
                        'error'     => $resultado['error'],
                    ]);
                    continue;
                }

                $datos  = $resultado['data'];
                $estado = $wompi->normalizarEstado($datos['status'] ?? null);

                if ($estado === WompiTransaccion::PENDING) {
                    $this->line("  [{$transaccion->reference}] sigue PENDING.");
                    continue;
                }

                // Mismo procesamiento que el webhook (validaciones + idempotencia).
                app(\App\Http\Controllers\WompiController::class)
                    ->sincronizarDesdeComando($transaccion, $datos, 'COMANDO');

                $actualizadas++;
                $this->info("  [{$transaccion->reference}] PENDING → {$estado}");
            } catch (\Throwable $e) {
                $errores++;
                $this->error("  [{$transaccion->reference}] excepción: {$e->getMessage()}");
                Log::error('wompi:sync-pending excepción.', [
                    'reference' => $transaccion->reference,
                    'error'     => $e->getMessage(),
                ]);
            }
        }

        $resumen = "wompi:sync-pending → revisadas: {$revisadas}, actualizadas: {$actualizadas}, errores: {$errores}.";

        $this->info($resumen);
        Log::info($resumen, [
            'revisadas'    => $revisadas,
            'actualizadas' => $actualizadas,
            'errores'      => $errores,
        ]);

        return self::SUCCESS;
    }
}
