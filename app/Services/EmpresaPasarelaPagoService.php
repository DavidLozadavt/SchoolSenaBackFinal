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
     * Formato: FACTURA-{idFactura}-{idEmpresa}
     */
    public function generarReferencia(int $idFactura, int $idEmpresa): string
    {
        return "FACTURA-{$idFactura}-{$idEmpresa}";
    }

    /**
     * Parsea una referencia de pago para obtener idFactura e idEmpresa.
     * Retorna ['idFactura' => int, 'idEmpresa' => int] o null si es inválida.
     */
    public function parsearReferencia(string $reference): ?array
    {
        $parts = explode('-', $reference);
        if (count($parts) === 3 && $parts[0] === 'FACTURA' && is_numeric($parts[1]) && is_numeric($parts[2])) {
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
