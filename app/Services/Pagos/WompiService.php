<?php

namespace App\Services\Pagos;

use App\Models\ConfiguracionWompi;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Integración con la API oficial de Wompi (https://docs.wompi.co).
 *
 * Se usa el Checkout Web oficial de Wompi: el backend genera la referencia y la
 * firma de integridad, y el navegador abre `https://checkout.wompi.co/p/` con
 * esos datos. Wompi resuelve todos los métodos de pago habilitados en el
 * comercio (tarjetas débito/crédito, PSE, Nequi, Bancolombia, etc.); aquí no se
 * implementa ninguna pasarela propia.
 *
 * Endpoints consumidos:
 *   GET /merchants/{public_key}   → métodos de pago habilitados
 *   GET /transactions/{id}        → estado de una transacción
 *   GET /transactions?reference=  → transacción por referencia
 */
class WompiService
{
    /** Cache por request de las llaves resueltas (evita releer la BD). */
    private ?array $llavesCache = null;

    /**
     * Llave concreta ya resuelta: BD si `usarLlavesPropias` está activo, si no .env.
     */
    private function llave(string $nombre): ?string
    {
        $this->llavesCache ??= $this->llavesEfectivas();

        return $this->llavesCache[$nombre] ?? null;
    }

    public function estaConfigurado(): bool
    {
        return !empty($this->llave('publicKey'))
            && !empty($this->llave('integritySecret'));
    }

    public function publicKey(): ?string
    {
        return $this->llave('publicKey');
    }

    /** Cache por request de la fila de configuración. */
    private ?ConfiguracionWompi $configCache = null;

    /**
     * Moneda efectiva: la de la tabla; si está vacía, la del .env; si no, COP.
     */
    public function moneda(): string
    {
        return $this->configuracion()->moneda
            ?: config('services.wompi.currency', 'COP');
    }

    /**
     * Porcentaje de IVA efectivo (incluido en el precio del plan).
     */
    public function ivaPorcentaje(): float
    {
        $configuracion = $this->configuracion();

        return $configuracion->ivaPorcentaje !== null
            ? (float) $configuracion->ivaPorcentaje
            : (float) config('services.wompi.iva_porcentaje', 0);
    }


    /**
     * URL del webhook, detectada automáticamente según el backend donde corra.
     *
     * Prioridad:
     *  1. El valor guardado en la configuración, si el administrador lo fijó a mano.
     *  2. El host de la petición en curso: así cada entorno (local, pre, producción)
     *     muestra su propia URL sin configurar nada.
     *  3. APP_URL, para contextos sin petición HTTP (comandos de consola).
     */
    public function urlWebhook(): string
    {
        $manual = $this->configuracion()->urlWebhook;

        if (!empty($manual)) {
            return $manual;
        }

        $request = request();

        if ($request && $request->getHttpHost()) {
            return $request->getSchemeAndHttpHost() . '/api/webhooks/wompi';
        }

        return url('/api/webhooks/wompi');
    }

    /**
     * URL de retorno del checkout, detectada igual que el webhook cuando no se
     * ha configurado a mano.
     */
    public function urlRetorno(): ?string
    {
        $manual = $this->configuracion()->urlRetorno;

        if (!empty($manual)) {
            return $manual;
        }

        // Detección automática: la URL de retorno apunta al FRONTEND, así que se
        // deduce del origen de la petición (el navegador que abre el checkout).
        // Así cada frontend —local, preproducción o producción— vuelve a sí mismo
        // sin configurar nada, sea cual sea el backend al que apunte su
        // VITE_APP_API_URL.
        $origen = $this->origenFrontend();

        if ($origen) {
            return $origen . '/pago-plan/resultado';
        }

        return config('services.wompi.redirect_url');
    }

    /**
     * Esquema + host del frontend que originó la petición (cabecera Origin y,
     * si falta, Referer). Devuelve null en consola o si no viene ninguna.
     */
    private function origenFrontend(): ?string
    {
        $request = request();

        if (!$request) {
            return null;
        }

        $origin = $request->headers->get('Origin');

        if (!empty($origin)) {
            return rtrim($origin, '/');
        }

        $referer = $request->headers->get('Referer');

        if (!empty($referer)) {
            $partes = parse_url($referer);

            if (!empty($partes['scheme']) && !empty($partes['host'])) {
                $puerto = isset($partes['port']) ? ':' . $partes['port'] : '';

                return $partes['scheme'] . '://' . $partes['host'] . $puerto;
            }
        }

        return null;
    }

    public function checkoutUrl(): string
    {
        return config('services.wompi.checkout_url', 'https://checkout.wompi.co/p/');
    }

    /**
     * Referencia única e irrepetible de la compra.
     */
    public function generarReferencia(int $userId, int $planId): string
    {
        return sprintf('PLAN-%d-%d-%s', $planId, $userId, strtoupper(Str::random(10)));
    }

    /**
     * Firma de integridad exigida por el Checkout Web:
     * SHA256("<reference><amount_in_cents><currency><integrity_secret>").
     */
    public function firmaIntegridad(string $reference, int $amountInCents, string $currency): string
    {
        return hash(
            'sha256',
            $reference . $amountInCents . $currency . $this->llave('integritySecret')
        );
    }

    /**
     * Datos que el frontend necesita para abrir el Checkout oficial.
     */
    public function datosCheckout(string $reference, int $amountInCents, ?string $email = null): array
    {
        $currency = $this->moneda();

        return [
            'checkoutUrl'    => $this->checkoutUrl(),
            'publicKey'      => $this->publicKey(),
            'reference'      => $reference,
            'amountInCents'  => $amountInCents,
            'currency'       => $currency,
            'signature'      => $this->firmaIntegridad($reference, $amountInCents, $currency),
            'redirectUrl'    => $this->urlRetorno(),
            'customerEmail'  => $email,
        ];
    }

    /**
     * Métodos de pago habilitados en el comercio (para mostrarlos en el modal).
     *
     * @return array{ok: bool, metodos?: array, error?: string|null}
     */
    public function metodosDisponibles(): array
    {
        if (!$this->estaConfigurado()) {
            return ['ok' => false, 'error' => 'La pasarela Wompi no está configurada en este backend.'];
        }

        try {
            $response = Http::acceptJson()->get($this->baseUrl() . '/merchants/' . $this->publicKey());
            $body = $response->json();

            if (!$response->successful()) {
                return ['ok' => false, 'error' => $body['error']['reason'] ?? $response->body()];
            }

            return [
                'ok'      => true,
                'metodos' => $body['data']['accepted_payment_methods'] ?? [],
                'raw'     => $body,
            ];
        } catch (\Throwable $e) {
            Log::error('WompiService: error al consultar métodos de pago: ' . $e->getMessage());

            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Consulta una transacción por su id de Wompi.
     */
    public function consultarTransaccion(string $transactionId): array
    {
        try {
            $response = Http::withToken($this->llave('privateKey'))
                ->acceptJson()
                ->get($this->baseUrl() . '/transactions/' . $transactionId);

            $body = $response->json();

            if (!$response->successful()) {
                return ['ok' => false, 'error' => $body['error']['reason'] ?? $response->body()];
            }

            return ['ok' => true, 'data' => $body['data'] ?? [], 'raw' => $body];
        } catch (\Throwable $e) {
            Log::error('WompiService: error al consultar la transacción: ' . $e->getMessage());

            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Consulta una transacción por la referencia generada por el sistema.
     */
    public function consultarPorReferencia(string $reference): array
    {
        try {
            $response = Http::withToken($this->llave('privateKey'))
                ->acceptJson()
                ->get($this->baseUrl() . '/transactions', ['reference' => $reference]);

            $body = $response->json();

            if (!$response->successful()) {
                return ['ok' => false, 'error' => $body['error']['reason'] ?? $response->body()];
            }

            $transacciones = $body['data'] ?? [];

            if (empty($transacciones)) {
                return ['ok' => false, 'error' => 'Wompi aún no reporta ninguna transacción para esta referencia.'];
            }

            // La más reciente para esa referencia.
            return ['ok' => true, 'data' => $transacciones[0], 'raw' => $body];
        } catch (\Throwable $e) {
            Log::error('WompiService: error al consultar por referencia: ' . $e->getMessage());

            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Valida la firma del webhook (evento) de Wompi.
     *
     * Wompi envía `signature.properties` (rutas dentro de `data`) y
     * `signature.checksum` = SHA256(valores concatenados + timestamp + events_secret).
     */
    public function verificarFirmaEvento(array $payload): bool
    {
        $secret = $this->llave('eventsSecret');

        if (empty($secret)) {
            Log::warning('WompiService: WOMPI_EVENTS_SECRET no configurado; se rechaza el evento.');

            return false;
        }

        $checksumRecibido = $payload['signature']['checksum'] ?? null;
        $propiedades      = $payload['signature']['properties'] ?? [];
        $timestamp        = $payload['timestamp'] ?? '';

        if (empty($checksumRecibido) || empty($propiedades)) {
            return false;
        }

        $concatenado = '';
        foreach ($propiedades as $ruta) {
            $concatenado .= (string) data_get($payload['data'] ?? [], $ruta, '');
        }

        $esperado = hash('sha256', $concatenado . $timestamp . $secret);

        return hash_equals($esperado, (string) $checksumRecibido);
    }

    /**
     * Normaliza el estado recibido a uno de los estados soportados.
     */
    public function normalizarEstado(?string $estado): string
    {
        $estado = strtoupper((string) $estado);

        return in_array($estado, ['PENDING', 'APPROVED', 'DECLINED', 'VOIDED', 'ERROR'], true)
            ? $estado
            : 'PENDING';
    }

    private function baseUrl(): string
    {
        // El .env manda solo si define explícitamente la URL; si no, la deriva
        // del `modo` guardado en la tabla de configuración.
        $desdeEnv = config('services.wompi.base_url');

        if (!empty($desdeEnv)) {
            return rtrim($desdeEnv, '/');
        }

        return $this->configuracion()->esProduccion()
            ? 'https://production.wompi.co/v1'
            : 'https://sandbox.wompi.co/v1';
    }

    // -------------------------------------------------------------------------
    // Configuración y diagnóstico
    //
    // Fusionado aquí desde el antiguo ConfiguracionPagosService para que exista
    // un único servicio del dominio Wompi. Es SOLO LECTURA: no altera el
    // checkout, el webhook ni el flujo PSE de WompiController.
    // -------------------------------------------------------------------------

    public function configuracion(): ConfiguracionWompi
    {
        return $this->configCache ??= ConfiguracionWompi::vigente();
    }

    /**
     * Llaves efectivas (valores reales, uso interno del diagnóstico).
     *
     * Con `usarLlavesPropias = false` (valor por defecto) se siguen leyendo del
     * .env, exactamente igual que antes de este refactor.
     */
    public function llavesEfectivas(): array
    {
        $configuracion = $this->configuracion();

        if ($configuracion->usarLlavesPropias) {
            return [
                'origen'          => 'BASE_DE_DATOS',
                'publicKey'       => $configuracion->publicKey,
                'privateKey'      => $configuracion->privateKey,
                'integritySecret' => $configuracion->integritySecret,
                'eventsSecret'    => $configuracion->eventsSecret,
            ];
        }

        return [
            'origen'          => 'ARCHIVO_ENV',
            'publicKey'       => config('services.wompi.public_key'),
            'privateKey'      => config('services.wompi.private_key'),
            'integritySecret' => config('services.wompi.integrity_secret'),
            'eventsSecret'    => config('services.wompi.events_secret'),
        ];
    }

    /**
     * Diagnóstico de la configuración. Nunca expone el valor de una llave.
     */
    public function diagnostico(): array
    {
        $configuracion = $this->configuracion();
        $llaves        = $this->llavesEfectivas();

        $variables = [
            'publicKey'       => !empty($llaves['publicKey']),
            'privateKey'      => !empty($llaves['privateKey']),
            'integritySecret' => !empty($llaves['integritySecret']),
            'eventsSecret'    => !empty($llaves['eventsSecret']),
        ];

        $faltantes = array_keys(array_filter($variables, fn ($presente) => !$presente));

        // Coherencia entre el modo declarado y el prefijo real de la llave pública.
        $publica       = (string) ($llaves['publicKey'] ?? '');
        $modoCoherente = $publica === '' || ($configuracion->esProduccion()
            ? str_starts_with($publica, 'pub_prod_')
            : str_starts_with($publica, 'pub_test_'));

        return [
            'modo'                  => $configuracion->modo,
            'proveedor'             => $configuracion->proveedor,
            'moneda'                => $this->moneda(),
            'ivaPorcentaje'         => $this->ivaPorcentaje(),
            'mensajesGratuitos'     => $configuracion->mensajesGratuitos,
            'sistemaActivo'         => $configuracion->activo,
            'horasMaxAprobacion'    => $configuracion->horasMaxAprobacion,
            'urlRetorno'            => $this->urlRetorno(),
            'urlWebhook'            => $this->urlWebhook(),
            'origenLlaves'          => $llaves['origen'],
            'variables'             => $variables,
            'variablesFaltantes'    => $faltantes,
            'configuracionCompleta' => empty($faltantes),
            'modoCoherente'         => $modoCoherente,
            'webhookConfigurado'    => !empty($llaves['eventsSecret']),
            'urlWebhookAutomatica'  => empty($configuracion->urlWebhook),
            'baseApi'               => $this->baseUrl(),
        ];
    }

    /**
     * Verificación activa contra la API de Wompi. No crea ni modifica transacciones.
     */
    public function verificar(): array
    {
        $diagnostico = $this->diagnostico();
        $llaves      = $this->llavesEfectivas();
        $problemas   = [];

        foreach ($diagnostico['variablesFaltantes'] as $faltante) {
            $problemas[] = "Falta configurar la llave «{$faltante}».";
        }

        if (!$diagnostico['modoCoherente']) {
            $problemas[] = $diagnostico['modo'] === ConfiguracionWompi::MODO_PRODUCCION
                ? 'El modo es PRODUCCIÓN pero la llave pública no empieza por «pub_prod_».'
                : 'El modo es SANDBOX pero la llave pública no empieza por «pub_test_».';
        }

        if (!$diagnostico['webhookConfigurado']) {
            $problemas[] = 'Sin Events Secret configurado el webhook rechazará todos los eventos de Wompi.';
        }

        if (empty($diagnostico['urlRetorno'])) {
            $problemas[] = 'No hay URL de retorno configurada para el checkout.';
        }

        if (!$diagnostico['sistemaActivo']) {
            $problemas[] = 'El sistema de pagos está marcado como INACTIVO.';
        }

        $conexion = ['ok' => false, 'mensaje' => 'No se intentó: falta la llave pública.'];

        if (!empty($llaves['publicKey'])) {
            $conexion = $this->probarConexion($llaves['publicKey']);

            if (!$conexion['ok']) {
                $problemas[] = 'No se pudo conectar con Wompi: ' . $conexion['mensaje'];
            }
        }

        return [
            'valida'      => empty($problemas),
            'problemas'   => $problemas,
            'conexion'    => $conexion,
            'diagnostico' => $diagnostico,
        ];
    }

    /**
     * GET /merchants/{publicKey} — endpoint público de Wompi.
     */
    private function probarConexion(string $publicKey): array
    {
        try {
            $response = Http::acceptJson()->timeout(15)->get($this->baseUrl() . "/merchants/{$publicKey}");
            $body     = $response->json();

            if (!$response->successful()) {
                return [
                    'ok'      => false,
                    'mensaje' => $body['error']['reason'] ?? 'HTTP ' . $response->status(),
                ];
            }

            return [
                'ok'               => true,
                'mensaje'          => 'Conexión correcta con Wompi.',
                'comercio'         => $body['data']['name'] ?? null,
                'metodosAceptados' => $body['data']['accepted_payment_methods'] ?? [],
            ];
        } catch (\Throwable $e) {
            Log::warning('WompiService: fallo al verificar conexión con Wompi: ' . $e->getMessage());

            return ['ok' => false, 'mensaje' => $e->getMessage()];
        }
    }
}
