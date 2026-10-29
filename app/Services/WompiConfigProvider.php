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

        $config = Cache::remember($cacheKey, $this->cacheTtl, function () use ($erpEmpresaId, $idEmpresa) {
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

        return $config ?: $this->localPublicConfig();
    }

    public function getPrivateConfig(int $idEmpresa): ?array
    {
        $erpEmpresaId = $this->resolveErpEmpresaId($idEmpresa);
        $cacheKey = "wompi_private_config_{$idEmpresa}_{$erpEmpresaId}";

        $config = Cache::remember($cacheKey, $this->cacheTtl, function () use ($erpEmpresaId, $idEmpresa) {
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

        return $config ?: $this->localPrivateConfig();
    }

    /**
     * Respaldo para desarrollo local: si el ERP no entrega la pasarela, se usan
     * las llaves de services.wompi (WOMPI_* en .env). Sin llave pública no aplica.
     */
    protected function localPublicConfig(): ?array
    {
        $publicKey = config('services.wompi.public_key');
        if (!$publicKey) {
            return null;
        }

        return [
            'publicKey'         => $publicKey,
            'habilitarPSE'      => (bool) config('services.wompi.habilitar_pse', true),
            'habilitarTarjetas' => (bool) config('services.wompi.habilitar_tarjetas', true),
        ];
    }

    protected function localPrivateConfig(): ?array
    {
        $integritySecret = config('services.wompi.integrity_secret');
        if (!$integritySecret) {
            return null;
        }

        return [
            'publicKey'       => config('services.wompi.public_key'),
            'privateKey'      => config('services.wompi.private_key'),
            'integritySecret' => $integritySecret,
            'eventsSecret'    => config('services.wompi.events_secret'),
        ];
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

    /**
     * Checksum de eventos según Wompi: valores de signature.properties (en su orden,
     * leídos de data) + timestamp + secreto de eventos, en SHA256. Wompi lo envía en mayúsculas.
     */
    public function validateWebhookSignature(array $event, string $eventsSecret): bool
    {
        $properties = $event['signature']['properties'] ?? null;
        $receivedSignature = (string) ($event['signature']['checksum'] ?? '');
        if (!is_array($properties) || $properties === [] || $receivedSignature === '' || !isset($event['timestamp'])) {
            return false;
        }

        $raw = '';
        foreach ($properties as $property) {
            $raw .= (string) data_get($event['data'] ?? [], $property, '');
        }
        $raw .= $event['timestamp'] . $eventsSecret;

        return hash_equals(hash('sha256', $raw), strtolower($receivedSignature));
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
