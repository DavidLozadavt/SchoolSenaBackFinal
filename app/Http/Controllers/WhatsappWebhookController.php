<?php

namespace App\Http\Controllers;

use App\Models\SeguimientoAspirante;
use App\Models\TelecomConfig;
use App\Services\Telecom\MetaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Webhook autónomo de WhatsApp Cloud API (Meta) — único webhook del proyecto.
 *
 *  - GET  /api/webhooks/meta : validación (hub.challenge) con verifyToken de telecom_configs.
 *  - POST /api/webhooks/meta : recepción de messages / interactive / button / statuses.
 *
 * Reutiliza MetaService (mismo estilo que el ERP) para parsear el payload.
 */
class WhatsappWebhookController extends Controller
{

    /**
     * Verificación del webhook. Devuelve hub.challenge si el verify_token coincide
     * con TelecomConfig.verifyToken.
     */
    public function verify(Request $request)
    {
        $mode      = $request->query('hub_mode') ?? $request->query('hub.mode');
        $token     = $request->query('hub_verify_token') ?? $request->query('hub.verify_token');
        $challenge = $request->query('hub_challenge') ?? $request->query('hub.challenge');

        $verifyToken = TelecomConfig::activa()?->verifyToken;

        if ($mode === 'subscribe' && $verifyToken && hash_equals($verifyToken, (string) $token)) {
            return response($challenge, 200)->header('Content-Type', 'text/plain');
        }

        Log::warning('Webhook WhatsApp: verificación fallida.', ['mode' => $mode]);

        return response('Forbidden', 403);
    }

    /**
     * Recepción de eventos. Valida SIEMPRE la firma (appSecret desde telecom_configs).
     */
    public function receive(Request $request)
    {
        // Traza de diagnóstico: registra TODO evento entrante (antes de validar firma).
        Log::info('Webhook WhatsApp: evento entrante recibido de Meta.', [
            'has_signature' => $request->hasHeader('X-Hub-Signature-256'),
            'entry_count'   => is_array($request->input('entry')) ? count($request->input('entry')) : 0,
        ]);

        $meta = new MetaService();

        // Firma opcional: solo se valida si hay appSecret configurado.
        if (!$meta->verificarFirma($request->getContent(), $request->header('X-Hub-Signature-256'))) {
            Log::warning('Webhook WhatsApp: firma X-Hub-Signature-256 inválida o appSecret ausente.');
            return response()->json(['error' => 'Firma inválida'], 403);
        }

        try {
            foreach ($request->input('entry', []) as $entry) {
                foreach ($entry['changes'] ?? [] as $change) {
                    $value = $change['value'] ?? [];

                    foreach ($value['messages'] ?? [] as $message) {
                        $this->procesarMensajeEntrante($meta, $message);
                    }

                    foreach ($value['statuses'] ?? [] as $status) {
                        $this->procesarEstado($status);
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::error('Webhook WhatsApp: error procesando payload: ' . $e->getMessage());
        }

        return response()->json(['received' => true], 200);
    }

    /**
     * Guarda la respuesta del aspirante (SI/NO) buscándolo por celular.
     */
    private function procesarMensajeEntrante(MetaService $meta, array $message): void
    {
        $phone    = $message['from'] ?? null;
        $texto    = $meta->extractMessageBody($message);
        $buttonId = $meta->extractButtonReplyId($message);

        if (!$phone) {
            return;
        }

        $ultimos10 = substr(preg_replace('/\D+/', '', $phone), -10);
        $aspirante = SeguimientoAspirante::where('celular', 'LIKE', '%' . $ultimos10)
            ->orderByDesc('id')
            ->first();

        if (!$aspirante) {
            Log::info('Webhook WhatsApp: mensaje de número no registrado.', ['phone' => $phone]);
            return;
        }

        $estado = $this->interpretarRespuesta($buttonId, $texto);

        $aspirante->update([
            'respuesta'      => $texto !== '' ? $texto : $buttonId,
            'fechaRespuesta' => now(),
            'estado'         => $estado,
        ]);

        // Auto-respuesta según SI/NO (dentro de la ventana de 24h => texto libre permitido).
        $this->enviarAutorespuesta($meta, $phone, $aspirante, $estado);
    }

    /**
     * Envía la respuesta automática al aspirante según haya aceptado (SI) o rechazado (NO).
     */
    private function enviarAutorespuesta(MetaService $meta, string $phone, SeguimientoAspirante $aspirante, string $estado): void
    {
        $nombre   = trim((string) $aspirante->nombre) ?: 'aspirante';
        $programa = trim((string) $aspirante->programa);

        if ($estado === 'SI') {
            if (!$aspirante->tokenPublico) {
                $aspirante->tokenPublico = (string) \Illuminate\Support\Str::uuid();
            }
            $aspirante->estadoDocumental = 'link_enviado';
            $aspirante->save();

            $urlInscripcion = rtrim(config('app.frontend_url'), '/')
                . '/formulario-aspirante/' . $aspirante->tokenPublico;

            $mensaje = "¡Perfecto, {$nombre}! 🎉\n\n"
                . "Para finalizar tu registro" . ($programa !== '' ? " al programa {$programa}" : '') . ", "
                . "ingresa a este enlace y completa tus datos:\n"
                . $urlInscripcion . "\n\n"
                . "¡Te esperamos!";
        } elseif ($estado === 'NO') {
            $mensaje = "Entendido, {$nombre}. 🙏\n\n"
                . "Hemos cancelado tu inscripción" . ($programa !== '' ? " al programa {$programa}" : '') . ". "
                . "Lamentamos que no continúes por ahora; te esperamos pronto para que vuelvas a inscribirte. "
                . "¡Éxitos!";
        } else {
            // Respuesta no interpretable como SI/NO: no se envía auto-respuesta.
            return;
        }

        $resultado = $meta->enviarTexto($phone, $mensaje);
        if (!$resultado['ok']) {
            Log::warning('Webhook WhatsApp: no se pudo enviar la auto-respuesta.', [
                'aspirante_id' => $aspirante->id,
                'estado'       => $estado,
                'error'        => $resultado['error'] ?? null,
            ]);
        }
    }

    /**
     * Interpreta SI/NO a partir del botón o del texto libre (normalizado, sin acentos).
     * Acepta variantes: "si", "sí", "sí me interesa", "no", "no gracias", etc.
     */
    private function interpretarRespuesta(?string $buttonId, string $texto): string
    {
        $normalizar = function (string $s): string {
            $s = mb_strtolower(trim($s), 'UTF-8');
            $s = strtr($s, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u']);
            return $s;
        };

        // 1) Botón (payload/id de plantilla o interactivo) — prioridad.
        //    Negativos primero: "no_interes" contiene "interes" pero es NO.
        $b = $normalizar((string) $buttonId);
        if ($b !== '') {
            if (str_contains($b, 'no') || str_contains($b, 'rechaz') || str_contains($b, 'cancel')) {
                return 'NO';
            }
            if (str_contains($b, 'si') || str_contains($b, 'interes') || str_contains($b, 'acept')) {
                return 'SI';
            }
        }

        // 2) Texto libre. Negativos primero (para "no me interesa").
        $t = $normalizar($texto);
        if ($t === '') {
            return 'Respondido';
        }

        $negativos = ['no', 'no gracias', 'no me interesa', 'cancelar', 'rechazar'];
        foreach ($negativos as $n) {
            if ($t === $n || str_starts_with($t, 'no ') || str_starts_with($t, 'no,')) {
                return 'NO';
            }
        }

        $positivos = ['si', 'sii', 'yes', 'claro', 'acepto', 'aceptar', 'confirmar', 'interesado', 'me interesa'];
        foreach ($positivos as $p) {
            if ($t === $p || str_contains($t, $p)) {
                return 'SI';
            }
        }

        return 'Respondido';
    }

    /**
     * Correlaciona un status (sent/delivered/read/failed) por wa_message_id.
     */
    private function procesarEstado(array $status): void
    {
        $messageId = $status['id'] ?? null;
        $estado    = $status['status'] ?? null;

        if (!$messageId || !$estado) {
            return;
        }

        $aspirante = SeguimientoAspirante::where('waMessageId', $messageId)
            ->orderByDesc('id')
            ->first();

        if (!$aspirante) {
            Log::info('Webhook WhatsApp: status sin aspirante correlacionado.', ['waMessageId' => $messageId, 'estado' => $estado]);
            return;
        }

        $datos = ['estadoEnvio' => $estado];

        if ($estado === 'failed') {
            $datos['errorEnvio'] = $status['errors'][0]['title']
                ?? ($status['errors'][0]['message'] ?? 'Error de entrega');
        }

        $aspirante->update($datos);
    }
}
