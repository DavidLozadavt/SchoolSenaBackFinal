<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * WompiConfigProvider
 * Consulta el ERP para obtener la configuración de Wompi de una empresa.
 * School NUNCA persiste credenciales — solo cache temporal en memoria.
 */
class WompiConfigProvider
{
    protected string $erpBaseUrl;

    protected ?string $erpServiceToken;

    protected int $cacheTtl = 600;

    public function __construct()
    {
        $this->erpBaseUrl = rtrim(config('services.erp.base_url', env('ERP_BASE_URL', 'http://localhost:8001/api')), '/');
        $this->erpServiceToken = config('services.erp.service_token', env('ERP_SERVICE_TOKEN'));
    }

    public function getPublicConfig(int $idEmpresa): ?array
    {
        $cacheKey = "wompi_public_config_{$idEmpresa}";

        return Cache::remember($cacheKey, $this->cacheTtl, function () use ($idEmpresa) {
            try {
                $response = Http::timeout(10)->get(
                    "{$this->erpBaseUrl}/empresa/{$idEmpresa}/pasarela-pago/publica"
                );

                if ($response->successful()) {
                    return $response->json();
                }

                Log::warning("WompiConfigProvider: no se pudo obtener config pública para empresa {$idEmpresa}", [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);

                return null;
            } catch (\Throwable $e) {
                Log::error("WompiConfigProvider: error al consultar ERP para empresa {$idEmpresa}: " . $e->getMessage());
                return null;
            }
        });
    }

    public function getPrivateConfig(int $idEmpresa): ?array
    {
        $cacheKey = "wompi_private_config_{$idEmpresa}";

        return Cache::remember($cacheKey, $this->cacheTtl, function () use ($idEmpresa) {
            try {
                $response = Http::timeout(10)
                    ->withHeaders($this->erpServiceHeaders())
                    ->get("{$this->erpBaseUrl}/empresa/{$idEmpresa}/pasarela-pago/privada");

                if ($response->successful()) {
                    return $response->json();
                }

                Log::warning("WompiConfigProvider: no se pudo obtener config privada para empresa {$idEmpresa}", [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);

                return null;
            } catch (\Throwable $e) {
                Log::error("WompiConfigProvider: error al consultar ERP (privada) para empresa {$idEmpresa}: " . $e->getMessage());
                return null;
            }
        });
    }

    public function clearCache(int $idEmpresa): void
    {
        Cache::forget("wompi_public_config_{$idEmpresa}");
        Cache::forget("wompi_private_config_{$idEmpresa}");
    }

    public function generateIntegrityHash(
        string $reference,
        int    $amountInCents,
        string $currency,
        string $integritySecret
    ): string {
        $raw = "{$reference}{$amountInCents}{$currency}{$integritySecret}";
        return hash('sha256', $raw);
    }

    public function validateWebhookSignature(array $event, string $eventsSecret): bool
    {
        $transaction = $event['data']['transaction'] ?? null;
        if (!$transaction) {
            return false;
        }

        $raw = implode('', [
            $transaction['id']              ?? '',
            $transaction['status']          ?? '',
            $transaction['amount_in_cents'] ?? '',
            $eventsSecret,
        ]);

        $expectedSignature = hash('sha256', $raw);
        $receivedSignature = $event['signature']['checksum'] ?? '';

        return hash_equals($expectedSignature, $receivedSignature);
    }

    /**
     * @return array<string, string>
     */
    protected function erpServiceHeaders(): array
    {
        $headers = [];

        if (!empty($this->erpServiceToken)) {
            $headers['X-ERP-Service-Token'] = $this->erpServiceToken;
        }

        return $headers;
    }
}
