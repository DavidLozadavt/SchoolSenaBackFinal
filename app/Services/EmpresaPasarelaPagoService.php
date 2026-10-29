<?php

namespace App\Services;

use App\Services\WompiConfigProvider;

/**
 * EmpresaPasarelaPagoService
 * Fachada del School para interactuar con la configuración
 * de pasarela de pagos almacenada en el ERP.
 *
 * School NUNCA guarda credenciales localmente.
 * Todo se obtiene dinámicamente desde el ERP vía WompiConfigProvider.
 */
class EmpresaPasarelaPagoService
{
    protected WompiConfigProvider $wompiConfig;

    public function __construct(WompiConfigProvider $wompiConfig)
    {
        $this->wompiConfig = $wompiConfig;
    }

    /**
     * Obtener la configuración pública para el Portal Aspirante.
     * Devuelve null si no hay configuración activa.
     *
     * @return array|null {publicKey, environment, habilitarPSE, habilitarTarjetas}
     */
    public function getPublicConfig(int $idEmpresa): ?array
    {
        return $this->wompiConfig->getPublicConfig($idEmpresa);
    }

    /**
     * Obtener la configuración completa del servidor (para generar firma, validar webhooks).
     * NUNCA exponer esto al cliente.
     *
     * @return array|null {publicKey, privateKey, integritySecret, eventsSecret, environment}
     */
    public function getPrivateConfig(int $idEmpresa): ?array
    {
        return $this->wompiConfig->getPrivateConfig($idEmpresa);
    }

    /**
     * Genera la firma de integridad para abrir el Web Checkout de Wompi.
     */
    public function generarFirmaIntegridad(
        int    $idEmpresa,
        string $reference,
        int    $amountInCents,
        string $currency = 'COP'
    ): ?string {
        $config = $this->getPrivateConfig($idEmpresa);
        if (!$config || empty($config['integritySecret'])) {
            return null;
        }

        return $this->wompiConfig->generateIntegrityHash(
            $reference,
            $amountInCents,
            $currency,
            $config['integritySecret']
        );
    }

    /**
     * Valida la firma de un webhook recibido de Wompi.
     */
    public function validarFirmaWebhook(int $idEmpresa, array $event): bool
    {
        $config = $this->getPrivateConfig($idEmpresa);
        if (!$config || empty($config['eventsSecret'])) {
            return false;
        }

        return $this->wompiConfig->validateWebhookSignature($event, $config['eventsSecret']);
    }

    /**
     * Genera la referencia de pago en el formato estándar.
     * Formato: FACTURA-{idFactura}-{idEmpresa}-{intento}
     * Wompi no acepta una referencia ya usada, por eso cada intento lleva un sufijo propio.
     */
    public function generarReferencia(int $idFactura, int $idEmpresa): string
    {
        return "FACTURA-{$idFactura}-{$idEmpresa}-" . now()->format('YmdHis');
    }

    /**
     * Consulta en Wompi las transacciones de una referencia (requiere llave privada).
     * Se usa para confirmar pagos cuando el webhook no llega (p. ej. en local).
     *
     * @return array<int, array> transacciones tal como las devuelve Wompi
     */
    public function consultarTransaccionesPorReferencia(int $idEmpresa, string $reference): array
    {
        $config = $this->getPrivateConfig($idEmpresa);
        $privateKey = $config['privateKey'] ?? null;
        if (!$privateKey) {
            return [];
        }

        $baseUrl = str_starts_with($privateKey, 'prv_prod_')
            ? 'https://production.wompi.co/v1'
            : 'https://sandbox.wompi.co/v1';

        try {
            $response = \Illuminate\Support\Facades\Http::timeout(15)
                ->withToken($privateKey)
                ->get("{$baseUrl}/transactions", ['reference' => $reference]);

            if (!$response->successful()) {
                \Illuminate\Support\Facades\Log::warning("Wompi: no se pudo consultar la referencia {$reference}", [
                    'status' => $response->status(),
                ]);
                return [];
            }

            $data = $response->json('data');
            return is_array($data) ? $data : [];
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error("Wompi: error consultando referencia {$reference}: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Parsea una referencia de pago para obtener idFactura e idEmpresa.
     * Acepta FACTURA-{idFactura}-{idEmpresa} y FACTURA-{idFactura}-{idEmpresa}-{intento}.
     * Retorna ['idFactura' => int, 'idEmpresa' => int] o null si es inválida.
     */
    public function parsearReferencia(string $reference): ?array
    {
        $parts = explode('-', $reference);
        if (in_array(count($parts), [3, 4], true) && $parts[0] === 'FACTURA' && is_numeric($parts[1]) && is_numeric($parts[2])) {
            return [
                'idFactura' => (int) $parts[1],
                'idEmpresa' => (int) $parts[2],
            ];
        }
        return null;
    }

    /**
     * Invalida la caché de la configuración de una empresa.
     * Útil cuando se actualizan credenciales en el ERP.
     */
    public function invalidarCache(int $idEmpresa): void
    {
        $this->wompiConfig->clearCache($idEmpresa);
    }
}
