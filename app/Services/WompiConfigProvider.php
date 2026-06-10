<?php

namespace App\Services;

use App\Models\Company;
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
        $erpEmpresaId = $this->resolveErpEmpresaId($idEmpresa);
        $cacheKey = "wompi_public_config_{$idEmpresa}_{$erpEmpresaId}";

        return Cache::remember($cacheKey, $this->cacheTtl, function () use ($erpEmpresaId, $idEmpresa) {
            try {
                $response = Http::timeout(10)->get(
                    "{$this->erpBaseUrl}/empresa/{$erpEmpresaId}/pasarela-pago/publica"
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
        $erpEmpresaId = $this->resolveErpEmpresaId($idEmpresa);
        $cacheKey = "wompi_private_config_{$idEmpresa}_{$erpEmpresaId}";

        return Cache::remember($cacheKey, $this->cacheTtl, function () use ($erpEmpresaId, $idEmpresa) {
            try {
                $response = Http::timeout(10)
                    ->withHeaders($this->erpServiceHeaders())
                    ->get("{$this->erpBaseUrl}/empresa/{$erpEmpresaId}/pasarela-pago/privada");

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
        $erpEmpresaId = $this->resolveErpEmpresaId($idEmpresa);
        Cache::forget("wompi_public_config_{$idEmpresa}_{$erpEmpresaId}");
        Cache::forget("wompi_private_config_{$idEmpresa}_{$erpEmpresaId}");
        Cache::forget("erp_empresa_id_for_school_{$idEmpresa}");
    }

    /**
     * School y ERP pueden tener distinto ID para la misma institución.
     * Se resuelve por NIT contra el listado de empresas del ERP.
     */
    public function resolveErpEmpresaId(int $schoolCompanyId): int
    {
        $cacheKey = "erp_empresa_id_for_school_{$schoolCompanyId}";

        return Cache::remember($cacheKey, $this->cacheTtl, function () use ($schoolCompanyId) {
            $schoolCompany = Company::find($schoolCompanyId);
            $nit = trim((string) ($schoolCompany?->nit ?? ''));

            if ($nit === '') {
                return $schoolCompanyId;
            }

            try {
                $response = Http::timeout(10)->get("{$this->erpBaseUrl}/list_companies");

                if ($response->successful()) {
                    $companies = $response->json();
                    if (!is_array($companies)) {
                        return $schoolCompanyId;
                    }

                    foreach ($companies as $company) {
                        if (trim((string) ($company['nit'] ?? '')) === $nit) {
                            return (int) $company['id'];
                        }
                    }
                }

                Log::warning("WompiConfigProvider: no se encontró empresa ERP con NIT {$nit} (School id {$schoolCompanyId})");
            } catch (\Throwable $e) {
                Log::error("WompiConfigProvider: error resolviendo empresa ERP para School {$schoolCompanyId}: " . $e->getMessage());
            }

            return $schoolCompanyId;
        });
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
