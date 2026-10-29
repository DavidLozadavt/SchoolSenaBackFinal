<?php

namespace App\Services;

use App\Models\EmpresaPasarelaPago;

/**
 * WompiConfigProvider
 * Llaves de Wompi de cada institución, registradas por la propia empresa en
 * Configuración de pagos (tabla empresaPasarelaPago). No depende del ERP.
 */
class WompiConfigProvider
{
    public function getPublicConfig(int $idEmpresa): ?array
    {
        $pasarela = $this->pasarelaActiva($idEmpresa);

        if ($pasarela) {
            return [
                'publicKey'         => $pasarela->publicKey,
                'environment'       => $pasarela->environment,
                'habilitarPSE'      => (bool) $pasarela->habilitarPSE,
                'habilitarTarjetas' => (bool) $pasarela->habilitarTarjetas,
            ];
        }

        return $this->localPublicConfig();
    }

    public function getPrivateConfig(int $idEmpresa): ?array
    {
        $pasarela = $this->pasarelaActiva($idEmpresa);

        if ($pasarela) {
            return [
                'publicKey'       => $pasarela->publicKey,
                'privateKey'      => $pasarela->privateKey,
                'integritySecret' => $pasarela->integritySecret,
                'eventsSecret'    => $pasarela->eventsSecret,
                'environment'     => $pasarela->environment,
            ];
        }

        return $this->localPrivateConfig();
    }

    /**
     * Pasarela activa y con las cuatro llaves; si falta alguna no se ofrece pago en línea.
     */
    protected function pasarelaActiva(int $idEmpresa): ?EmpresaPasarelaPago
    {
        $pasarela = EmpresaPasarelaPago::where('idEmpresa', $idEmpresa)
            ->where('activo', true)
            ->first();

        return $pasarela && $pasarela->completa() ? $pasarela : null;
    }

    /**
     * Solo en desarrollo local: si la empresa no registró llaves se usan las de
     * services.wompi (WOMPI_* en .env). En producción cada empresa cobra con las suyas.
     */
    protected function localPublicConfig(): ?array
    {
        $publicKey = config('services.wompi.public_key');
        if (!app()->environment('local') || !$publicKey) {
            return null;
        }

        return [
            'publicKey'         => $publicKey,
            'environment'       => 'test',
            'habilitarPSE'      => (bool) config('services.wompi.habilitar_pse', true),
            'habilitarTarjetas' => (bool) config('services.wompi.habilitar_tarjetas', true),
        ];
    }

    protected function localPrivateConfig(): ?array
    {
        $integritySecret = config('services.wompi.integrity_secret');
        if (!app()->environment('local') || !$integritySecret) {
            return null;
        }

        return [
            'publicKey'       => config('services.wompi.public_key'),
            'privateKey'      => config('services.wompi.private_key'),
            'integritySecret' => $integritySecret,
            'eventsSecret'    => config('services.wompi.events_secret'),
            'environment'     => 'test',
        ];
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
}
