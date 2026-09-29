<?php

namespace App\Services\Telecom;

use App\Models\TelecomConfig;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Administración de plantillas (message templates) contra la API oficial de Meta.
 *
 * Las credenciales salen SIEMPRE de la configuración activa (`telecomConfigs`),
 * la misma que usa MetaService para enviar: nunca se piden de nuevo al usuario.
 *
 * Endpoints usados:
 *   POST   /{waba_id}/message_templates      → crear plantilla
 *   GET    /{waba_id}/message_templates      → listar plantillas (sincronizar)
 *   DELETE /{waba_id}/message_templates?name → eliminar en Meta (opcional)
 *
 * @see https://developers.facebook.com/docs/whatsapp/business-management-api/message-templates
 */
class MetaTemplateService
{
    private const GRAPH_API_BASE  = 'https://graph.facebook.com';
    private const DEFAULT_VERSION = 'v23.0';

    private ?TelecomConfig $config;

    public function __construct(?TelecomConfig $config = null)
    {
        $this->config = $config ?? TelecomConfig::activa();
    }

    public function estaConfigurado(): bool
    {
        return $this->config !== null
            && !empty($this->config->accessToken)
            && !empty($this->config->businessAccountId);
    }

    public function getConfig(): ?TelecomConfig
    {
        return $this->config;
    }

    /**
     * Crea una plantilla directamente en Meta.
     *
     * @param  array  $datos  nombre, categoria, idioma, contenido, encabezado, pie,
     *                        variablesEjemplo[], botones[]
     * @return array{ok: bool, id?: string|null, status?: string|null, category?: string|null, error?: string|null, raw?: mixed}
     */
    public function crearPlantilla(array $datos): array
    {
        if (!$this->estaConfigurado()) {
            return ['ok' => false, 'error' => 'No hay configuración activa de WhatsApp Cloud API (falta accessToken o businessAccountId).'];
        }

        $payload = [
            'name'       => $datos['nombre'],
            'category'   => strtoupper($datos['categoria']),
            'language'   => $datos['idioma'],
            'components' => $this->construirComponentes($datos),
        ];

        try {
            $response = Http::withToken($this->config->accessToken)
                ->acceptJson()
                ->post($this->templatesEndpoint(), $payload);

            $body = $response->json();

            if (!$response->successful()) {
                $error = $body['error']['error_user_msg']
                    ?? $body['error']['message']
                    ?? $response->body();
                Log::warning('MetaTemplateService: error al crear plantilla en Meta.', ['status' => $response->status(), 'error' => $error]);

                return ['ok' => false, 'error' => $error, 'raw' => $body];
            }

            return [
                'ok'       => true,
                'id'       => $body['id'] ?? null,
                'status'   => $body['status'] ?? 'PENDING',
                'category' => $body['category'] ?? strtoupper($datos['categoria']),
                'raw'      => $body,
            ];
        } catch (\Throwable $e) {
            Log::error('MetaTemplateService: excepción al crear plantilla: ' . $e->getMessage());

            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Lista TODAS las plantillas de la WABA (siguiendo la paginación de Meta).
     *
     * @return array{ok: bool, data?: array, error?: string|null}
     */
    public function listarPlantillas(): array
    {
        if (!$this->estaConfigurado()) {
            return ['ok' => false, 'error' => 'No hay configuración activa de WhatsApp Cloud API (falta accessToken o businessAccountId).'];
        }

        $plantillas = [];
        $url = $this->templatesEndpoint();
        $params = ['limit' => 100];

        try {
            // Máximo 20 páginas como salvaguarda contra bucles infinitos.
            for ($pagina = 0; $pagina < 20 && $url; $pagina++) {
                $response = Http::withToken($this->config->accessToken)
                    ->acceptJson()
                    ->get($url, $params);

                $body = $response->json();

                if (!$response->successful()) {
                    $error = $body['error']['message'] ?? $response->body();

                    return ['ok' => false, 'error' => $error];
                }

                $plantillas = array_merge($plantillas, $body['data'] ?? []);

                $url    = $body['paging']['next'] ?? null;
                $params = []; // El `next` ya trae la query string completa.
            }

            return ['ok' => true, 'data' => $plantillas];
        } catch (\Throwable $e) {
            Log::error('MetaTemplateService: excepción al listar plantillas: ' . $e->getMessage());

            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Elimina la plantilla en Meta por nombre (borrado local aparte).
     */
    public function eliminarEnMeta(string $nombre): array
    {
        if (!$this->estaConfigurado()) {
            return ['ok' => false, 'error' => 'No hay configuración activa de WhatsApp Cloud API.'];
        }

        try {
            $response = Http::withToken($this->config->accessToken)
                ->acceptJson()
                ->delete($this->templatesEndpoint(), ['name' => $nombre]);

            if (!$response->successful()) {
                return ['ok' => false, 'error' => $response->json('error.message') ?? $response->body()];
            }

            return ['ok' => true, 'raw' => $response->json()];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Construye el array `components` que exige Meta a partir del formulario.
     */
    private function construirComponentes(array $datos): array
    {
        $components = [];

        if (!empty($datos['encabezado'])) {
            $components[] = [
                'type'   => 'HEADER',
                'format' => 'TEXT',
                'text'   => $datos['encabezado'],
            ];
        }

        $body = ['type' => 'BODY', 'text' => $datos['contenido']];

        // Meta exige ejemplos cuando el cuerpo tiene variables {{1}}, {{2}}, ...
        $variables = array_values(array_filter($datos['variablesEjemplo'] ?? [], fn ($v) => $v !== null && $v !== ''));
        if ($variables) {
            $body['example'] = ['body_text' => [array_map(fn ($v) => (string) $v, $variables)]];
        }
        $components[] = $body;

        if (!empty($datos['pie'])) {
            $components[] = ['type' => 'FOOTER', 'text' => $datos['pie']];
        }

        $botones = $datos['botones'] ?? [];
        if ($botones) {
            $components[] = [
                'type'    => 'BUTTONS',
                'buttons' => array_map(function ($boton) {
                    $tipo = strtoupper($boton['tipo'] ?? 'QUICK_REPLY');
                    $base = ['type' => $tipo, 'text' => $boton['texto'] ?? ''];

                    if ($tipo === 'URL') {
                        $base['url'] = $boton['valor'] ?? '';
                    } elseif ($tipo === 'PHONE_NUMBER') {
                        $base['phone_number'] = $boton['valor'] ?? '';
                    }

                    return $base;
                }, $botones),
            ];
        }

        return $components;
    }

    /**
     * Extrae el texto del BODY de la respuesta de Meta (para el listado local).
     */
    public function extraerComponente(array $plantillaMeta, string $tipo): ?string
    {
        foreach ($plantillaMeta['components'] ?? [] as $componente) {
            if (strtoupper($componente['type'] ?? '') === strtoupper($tipo)) {
                return $componente['text'] ?? null;
            }
        }

        return null;
    }

    /**
     * Extrae los botones de la respuesta de Meta normalizados al formato local.
     */
    public function extraerBotones(array $plantillaMeta): array
    {
        foreach ($plantillaMeta['components'] ?? [] as $componente) {
            if (strtoupper($componente['type'] ?? '') === 'BUTTONS') {
                return array_map(fn ($b) => [
                    'tipo'  => $b['type'] ?? 'QUICK_REPLY',
                    'texto' => $b['text'] ?? '',
                    'valor' => $b['url'] ?? $b['phone_number'] ?? null,
                ], $componente['buttons'] ?? []);
            }
        }

        return [];
    }

    private function version(): string
    {
        return $this->config->graphVersion ?: self::DEFAULT_VERSION;
    }

    private function templatesEndpoint(): string
    {
        return self::GRAPH_API_BASE . '/' . $this->version() . "/{$this->config->businessAccountId}/message_templates";
    }
}
