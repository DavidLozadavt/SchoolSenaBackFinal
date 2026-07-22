<?php

namespace App\Services\Telecom;

use App\Models\TelecomConfig;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Proveedor Meta (WhatsApp Cloud API) — misma lógica que el MetaService del ERP,
 * pero SINGLE-TENANT y SOLO-BD:
 *
 *  - Todas las credenciales salen EXCLUSIVAMENTE de la tabla telecom_configs (columnas
 *    explícitas: accessToken, phoneNumberId, graphVersion, appSecret). Sin company_id,
 *    sin env(), sin config(), sin TelecomManager.
 *  - Única salida HTTP: https://graph.facebook.com
 *
 * Reutiliza del ERP: construcción de payloads (texto/plantilla/interactivo) y el
 * parseo del webhook (extractMessageBody / extractButtonReplyId / statuses).
 *
 * @see https://developers.facebook.com/docs/whatsapp/cloud-api
 */
class MetaService implements TelecomProviderInterface
{
    private const GRAPH_API_BASE   = 'https://graph.facebook.com';
    private const BUTTON_TITLE_MAX = 20;
    private const DEFAULT_VERSION  = 'v23.0';

    private ?TelecomConfig $config;

    public function __construct(?TelecomConfig $config = null)
    {
        $this->config = $config ?? TelecomConfig::activa();
    }

    // -------------------------------------------------------------------------
    // Configuración (solo desde telecom_configs)
    // -------------------------------------------------------------------------

    public function estaConfigurado(): bool
    {
        return $this->config !== null
            && !empty($this->config->accessToken)
            && !empty($this->config->phoneNumberId);
    }

    public function getConfig(): ?TelecomConfig
    {
        return $this->config;
    }

    /**
     * Valida la firma X-Hub-Signature-256 de Meta usando appSecret de telecom_configs.
     *
     * Si NO hay appSecret configurado (formulario simplificado de 3 campos), se OMITE la
     * validación y se acepta el webhook. Si SÍ hay appSecret, la firma debe ser válida.
     */
    public function verificarFirma(string $rawBody, ?string $signature): bool
    {
        $appSecret = $this->config?->appSecret;

        if (empty($appSecret)) {
            return true; // Sin appSecret configurado => no se valida firma (se acepta)
        }

        if (empty($signature) || !str_starts_with($signature, 'sha256=')) {
            return false;
        }

        $esperado = 'sha256=' . hash_hmac('sha256', $rawBody, $appSecret);

        return hash_equals($esperado, $signature);
    }

    // -------------------------------------------------------------------------
    // Envío
    // -------------------------------------------------------------------------

    /**
     * Envía un mensaje de WhatsApp. Tipos vía $options['type']: text | template | interactive.
     *
     * @return array{ok: bool, id?: string|null, error?: string|null, raw?: mixed}
     */
    public function sendWhatsApp(string $to, string $message, array $options = []): array
    {
        if (!$this->estaConfigurado()) {
            return ['ok' => false, 'error' => 'No hay una configuración de WhatsApp Cloud API activa en este backend.'];
        }

        $payload = $this->buildPayload($this->normalizarNumero($to), $message, $options);

        try {
            $response = Http::withToken($this->config->accessToken)
                ->acceptJson()
                ->post($this->messagesEndpoint(), $payload);

            $body = $response->json();

            if (!$response->successful()) {
                $error = $body['error']['message'] ?? $response->body();
                Log::warning('MetaService: error al enviar a Meta.', ['status' => $response->status(), 'error' => $error]);

                return ['ok' => false, 'error' => $error, 'raw' => $body];
            }

            return ['ok' => true, 'id' => $body['messages'][0]['id'] ?? null, 'raw' => $body];
        } catch (\Throwable $e) {
            Log::error('MetaService: excepción al enviar a Meta: ' . $e->getMessage());

            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Envía la plantilla oficial con parámetros posicionales del cuerpo.
     *
     * @param  array  $parametros  Valores {{1}},{{2}}... en orden.
     */
    public function enviarPlantilla(string $to, string $templateName, string $idioma, array $parametros): array
    {
        $components = [];
        if (!empty($parametros)) {
            $components = [[
                'type'       => 'body',
                'parameters' => array_map(fn ($v) => ['type' => 'text', 'text' => (string) $v], $parametros),
            ]];
        }

        return $this->sendWhatsApp($to, '', [
            'type'              => 'template',
            'template_name'     => $templateName,
            'template_language' => $idioma,
            'components'        => $components,
        ]);
    }

    public function enviarTexto(string $to, string $mensaje): array
    {
        return $this->sendWhatsApp($to, $mensaje, ['type' => 'text']);
    }

    public function validateCredentials(): bool
    {
        if (!$this->estaConfigurado()) {
            return false;
        }

        $response = Http::withToken($this->config->accessToken)
            ->get($this->baseUrl() . "/{$this->config->phoneNumberId}");

        return $response->successful();
    }

    // -------------------------------------------------------------------------
    // Construcción de payloads (reutilizado del ERP)
    // -------------------------------------------------------------------------

    private function buildPayload(string $to, string $message, array $options): array
    {
        return match ($options['type'] ?? null) {
            'template'    => $this->buildTemplatePayload($to, $options),
            'interactive' => $this->buildInteractivePayload($to, $message, $options['buttons'] ?? []),
            default       => $this->buildTextPayload($to, $message),
        };
    }

    private function buildTextPayload(string $to, string $message): array
    {
        return [
            'messaging_product' => 'whatsapp',
            'recipient_type'    => 'individual',
            'to'                => $to,
            'type'              => 'text',
            'text'              => ['preview_url' => false, 'body' => $message],
        ];
    }

    private function buildTemplatePayload(string $to, array $options): array
    {
        $template = [
            'name'     => $options['template_name'] ?? '',
            'language' => ['code' => $options['template_language'] ?? 'es'],
        ];

        if (!empty($options['components'])) {
            $template['components'] = $options['components'];
        }

        return [
            'messaging_product' => 'whatsapp',
            'recipient_type'    => 'individual',
            'to'                => $to,
            'type'              => 'template',
            'template'          => $template,
        ];
    }

    private function buildInteractivePayload(string $to, string $message, array $buttons): array
    {
        $actionButtons = array_map(fn ($btn) => [
            'type'  => 'reply',
            'reply' => [
                'id'    => (string) $btn['id'],
                'title' => substr($btn['title'], 0, self::BUTTON_TITLE_MAX),
            ],
        ], $buttons);

        return [
            'messaging_product' => 'whatsapp',
            'recipient_type'    => 'individual',
            'to'                => $to,
            'type'              => 'interactive',
            'interactive'       => [
                'type'   => 'button',
                'body'   => ['text' => $message],
                'action' => ['buttons' => $actionButtons],
            ],
        ];
    }

    // -------------------------------------------------------------------------
    // Parseo del webhook (reutilizado del ERP)
    // -------------------------------------------------------------------------

    /**
     * Normaliza el primer mensaje/estado del payload. Robusto al orden del JSON.
     */
    public function receiveWebhook(array $payload): array
    {
        $value = $payload['entry'][0]['changes'][0]['value'] ?? [];

        if (!empty($value['messages'])) {
            $msg = $value['messages'][0] ?? [];

            return [
                'provider'   => 'meta',
                'type'       => 'message',
                'message_id' => $msg['id'] ?? null,
                'from'       => $msg['from'] ?? null,
                'body'       => $this->extractMessageBody($msg),
                'button_id'  => $this->extractButtonReplyId($msg),
                'status'     => 'received',
            ];
        }

        if (!empty($value['statuses'])) {
            return [
                'provider'   => 'meta',
                'type'       => 'status',
                'message_id' => null,
                'from'       => null,
                'body'       => '',
                'status'     => 'acknowledged',
                'statuses'   => $value['statuses'],
            ];
        }

        return ['provider' => 'meta', 'type' => 'ignored', 'message_id' => null, 'from' => null, 'body' => '', 'status' => 'ignored'];
    }

    /**
     * Extrae el texto del mensaje según su tipo (text / interactive / button de plantilla).
     */
    public function extractMessageBody(array $msg): string
    {
        return match ($msg['type'] ?? '') {
            'text'        => $msg['text']['body'] ?? '',
            'interactive' => $msg['interactive']['button_reply']['title']
                ?? $msg['interactive']['list_reply']['title']
                ?? '',
            'button'      => $msg['button']['text'] ?? '',
            default       => '',
        };
    }

    /**
     * Extrae el id del botón (interactive.button_reply.id o button.payload de plantilla).
     */
    public function extractButtonReplyId(array $msg): ?string
    {
        $type = $msg['type'] ?? '';

        if ($type === 'interactive') {
            $id = $msg['interactive']['button_reply']['id'] ?? $msg['interactive']['list_reply']['id'] ?? null;

            return is_string($id) && $id !== '' ? $id : null;
        }

        if ($type === 'button') {
            $payload = $msg['button']['payload'] ?? null;

            return is_string($payload) && $payload !== '' ? $payload : null;
        }

        return null;
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Normaliza el número a E.164 sin '+'. Colombia (57) para locales de 10 dígitos.
     */
    public function normalizarNumero(string $numero): string
    {
        $limpio = preg_replace('/\D+/', '', $numero);

        if (strlen($limpio) === 10 && str_starts_with($limpio, '3')) {
            $limpio = '57' . $limpio;
        }

        return $limpio;
    }

    private function version(): string
    {
        return $this->config->graphVersion ?: self::DEFAULT_VERSION;
    }

    private function baseUrl(): string
    {
        return self::GRAPH_API_BASE . '/' . $this->version();
    }

    private function messagesEndpoint(): string
    {
        return $this->baseUrl() . "/{$this->config->phoneNumberId}/messages";
    }
}
