<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Exception\RequestException;



class FactusClient
{
    protected Client $http;
    protected string $baseUrl;
    protected string $clientId;
    protected string $clientSecret;
    protected string $username;
    protected string $password;
    protected int $timeout;

    public function __construct()
    {
        $this->baseUrl = config('services.factus.base_url');
        $this->clientId = config('services.factus.client_id');
        $this->clientSecret = config('services.factus.client_secret');
        $this->username = config('services.factus.username');
        $this->password = config('services.factus.password');
        $this->timeout = (int) config('services.factus.timeout', 30);

        $this->http = new Client([
            'base_uri' => $this->baseUrl,
            'timeout' => $this->timeout,
            'connect_timeout' => 10,
        ]);
    }

    /**
     * Obtiene y cachea el token de acceso de Factus (1 hora de validez)
     */
    protected function getAccessToken(): string
    {
        return Cache::remember('factus_access_token', 3500, function () {
            try {
                $response = $this->http->post('/oauth/token', [
                    'form_params' => [
                        'grant_type' => 'password',
                        'client_id' => $this->clientId,
                        'client_secret' => $this->clientSecret,
                        'username' => $this->username,
                        'password' => $this->password,
                    ],
                ]);

                $data = json_decode((string) $response->getBody(), true);

                if (empty($data['access_token'])) {
                    Log::error('Factus: Token no recibido', ['response' => $data]);
                    throw new \Exception('No se pudo obtener el token de Factus.');
                }

                return $data['access_token'];
            } catch (RequestException $e) {
                $body = $e->getResponse()?->getBody()?->getContents();
                Log::error('Error autenticando con Factus', [
                    'mensaje' => $e->getMessage(),
                    'response' => $body,
                ]);
                throw new \Exception('Error de autenticación con Factus');
            }
        });
    }

    protected function headers(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->getAccessToken(),
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];
    }

    /**
     * Validar factura (antes de enviarla a la DIAN)
     */
    public function validateInvoice(array $payload)
    {
        $maxRetries = 3;
        $attempt = 0;

        while ($attempt < $maxRetries) {
            try {
                $attempt++;

                Log::info("📤 Enviando factura a Factus (intento {$attempt})", [
                    'endpoint' => '/v1/bills/validate',
                    'payload' => $payload,
                ]);

                $response = $this->http->post('/v1/bills/validate', [
                    'headers' => $this->headers(),
                    'json' => $payload,
                ]);

                $decoded = json_decode($response->getBody()->getContents(), true);

                Log::info('✅ Factus validateInvoice response', [
                    'status_code' => $response->getStatusCode(),
                    'body' => $decoded,
                ]);

                return $decoded;
            } catch (ConnectException $e) {
                Log::warning("⚠️ Timeout al conectar con Factus (intento {$attempt})", [
                    'error' => $e->getMessage(),
                ]);

                if ($attempt >= $maxRetries) {
                    throw new \Exception('Error: Timeout al conectar con Factus después de varios intentos.');
                }

                sleep(2);
            } catch (RequestException $e) {
                $response = $e->getResponse();
                $statusCode = $response?->getStatusCode();
                $body = $response?->getBody()?->getContents();

                $decoded = null;
                if ($body) {
                    try {
                        $decoded = json_decode($body, true);
                    } catch (\Throwable) {
                        $decoded = $body;
                    }
                }

                Log::error('❌ Factus validateInvoice error', [
                    'status_code' => $statusCode,
                    'message' => $e->getMessage(),
                    'decoded_body' => $decoded,
                    'raw_body' => $body,
                ]);

                // Manejo especial para error 409 (Factura pendiente en Factus)
                if ($statusCode === 409) {
                    $errorMessage = is_array($decoded) ? ($decoded['message'] ?? '') : '';
                    
                    if (strpos($errorMessage, 'pendiente por enviar') !== false) {
                        Log::warning("⏳ Factus tiene facturas pendientes (intento {$attempt}/{$maxRetries}). Esperando...");
                        
                        if ($attempt < $maxRetries) {
                            // Esperar más tiempo en cada intento: 10, 20, 30 segundos
                            $waitTime = $attempt * 10;
                            sleep($waitTime);
                            continue; // Reintentar
                        }
                        
                        // Si agotamos los reintentos, lanzamos una excepción específica
                        throw new \Exception(
                            "Factus tiene facturas pendientes por enviar a la DIAN. Por favor intente más tarde.",
                            409
                        );
                    }
                }

                throw new \Exception(
                    "Error Factus validateInvoice ({$statusCode}): " . ($decoded['message'] ?? $e->getMessage())
                );
            }
        }
    }
}
