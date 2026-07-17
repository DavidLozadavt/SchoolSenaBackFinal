<?php

namespace App\Services\Telecom;

/**
 * Contrato del proveedor de telecomunicaciones (misma idea que el ERP).
 * En este proyecto el único proveedor es Meta (WhatsApp Cloud API).
 */
interface TelecomProviderInterface
{
    /**
     * Envía un mensaje de WhatsApp.
     *
     * @param  array  $options  type: text|template|interactive y sus datos.
     * @return array{ok: bool, id?: string|null, error?: string|null, raw?: mixed}
     */
    public function sendWhatsApp(string $to, string $message, array $options = []): array;

    /**
     * Normaliza el payload entrante del webhook de Meta a un formato universal.
     *
     * @return array{provider:string, type:string, message_id:?string, from:?string, body:string, button_id?:?string, status:string, statuses?:array}
     */
    public function receiveWebhook(array $payload): array;

    /**
     * Valida que las credenciales funcionen contra Graph API.
     */
    public function validateCredentials(): bool;
}
