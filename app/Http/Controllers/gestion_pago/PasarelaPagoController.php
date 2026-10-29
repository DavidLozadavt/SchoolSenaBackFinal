<?php

namespace App\Http\Controllers\gestion_pago;

use App\Http\Controllers\Controller;
use App\Models\EmpresaPasarelaPago;
use App\Util\KeyUtil;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * Llaves de Wompi de la institución en sesión (Configuración de pagos).
 * Los secretos nunca se devuelven completos: solo sus últimos caracteres.
 */
class PasarelaPagoController extends Controller
{
    private const PERMISO = 'GESTION_CONFIGURACION_PAGOS';

    private const LLAVES = ['publicKey', 'privateKey', 'integritySecret', 'eventsSecret'];

    public function show(): JsonResponse
    {
        if (!$this->tienePermiso()) {
            return response()->json(['message' => 'No tiene permiso para gestionar la pasarela de pagos.'], 403);
        }

        $pasarela = EmpresaPasarelaPago::where('idEmpresa', KeyUtil::idCompany())->first();

        return response()->json($this->formatear($pasarela));
    }

    /**
     * Crea o actualiza las llaves. Un secreto vacío conserva el valor guardado.
     */
    public function update(Request $request): JsonResponse
    {
        if (!$this->tienePermiso()) {
            return response()->json(['message' => 'No tiene permiso para gestionar la pasarela de pagos.'], 403);
        }

        $datos = $request->validate([
            'environment'       => ['required', 'in:test,prod'],
            'publicKey'         => ['nullable', 'string', 'max:255'],
            'privateKey'        => ['nullable', 'string', 'max:255'],
            'integritySecret'   => ['nullable', 'string', 'max:255'],
            'eventsSecret'      => ['nullable', 'string', 'max:255'],
            'habilitarPSE'      => ['required', 'boolean'],
            'habilitarTarjetas' => ['required', 'boolean'],
            'activo'            => ['required', 'boolean'],
        ]);

        $pasarela = EmpresaPasarelaPago::firstOrNew(['idEmpresa' => (int) KeyUtil::idCompany()]);

        foreach (self::LLAVES as $campo) {
            $valor = trim((string) ($datos[$campo] ?? ''));
            if ($valor !== '') {
                $pasarela->{$campo} = $valor;
            }
        }

        $pasarela->environment = $datos['environment'];
        $pasarela->habilitarPSE = $datos['habilitarPSE'];
        $pasarela->habilitarTarjetas = $datos['habilitarTarjetas'];
        $pasarela->activo = $datos['activo'];

        $this->validarLlaves($pasarela);

        $pasarela->save();

        return response()->json([
            'message' => 'Llaves de Wompi guardadas.',
            'pasarela' => $this->formatear($pasarela),
        ]);
    }

    /**
     * Comprueba con Wompi la llave pública (comercio) y la privada.
     * Usa las llaves enviadas o, si faltan, las guardadas.
     */
    public function probar(Request $request): JsonResponse
    {
        if (!$this->tienePermiso()) {
            return response()->json(['message' => 'No tiene permiso para gestionar la pasarela de pagos.'], 403);
        }

        $request->validate([
            'publicKey'  => ['nullable', 'string', 'max:255'],
            'privateKey' => ['nullable', 'string', 'max:255'],
        ]);

        $guardada = EmpresaPasarelaPago::where('idEmpresa', KeyUtil::idCompany())->first();
        $publicKey = trim((string) $request->input('publicKey')) ?: $guardada?->publicKey;
        $privateKey = trim((string) $request->input('privateKey')) ?: $guardada?->privateKey;

        if (!$publicKey) {
            return response()->json(['message' => 'Ingrese la llave pública para probarla.'], 422);
        }

        $baseUrl = $this->urlApiWompi($publicKey);

        try {
            $comercio = Http::timeout(15)->get("{$baseUrl}/merchants/{$publicKey}");
        } catch (\Throwable $e) {
            return response()->json(['message' => 'No se pudo conectar con Wompi. Intente de nuevo.'], 502);
        }

        if (!$comercio->successful()) {
            return response()->json(['message' => 'Wompi no reconoce la llave pública.'], 422);
        }

        $resultado = [
            'comercio' => $comercio->json('data.name'),
            'publicKeyValida' => true,
            'privateKeyValida' => null,
        ];

        if ($privateKey) {
            try {
                $privada = Http::timeout(15)
                    ->withToken($privateKey)
                    ->get("{$baseUrl}/transactions", ['reference' => 'PRUEBA-LLAVES']);
                $resultado['privateKeyValida'] = $privada->successful();
            } catch (\Throwable $e) {
                $resultado['privateKeyValida'] = null;
            }
        }

        $resultado['message'] = $resultado['privateKeyValida'] === false
            ? 'La llave pública es válida, pero Wompi rechazó la llave privada.'
            : 'Llaves válidas para el comercio ' . ($resultado['comercio'] ?? '') . '.';

        return response()->json($resultado, $resultado['privateKeyValida'] === false ? 422 : 200);
    }

    /**
     * Prefijo correcto por llave y mismo ambiente (test/prod) que el elegido.
     * Para activar la pasarela deben estar las cuatro llaves.
     */
    private function validarLlaves(EmpresaPasarelaPago $pasarela): void
    {
        $errores = [];
        $ambiente = $pasarela->environment;
        $esperados = [
            'publicKey'       => "pub_{$ambiente}_",
            'privateKey'      => "prv_{$ambiente}_",
            'integritySecret' => "{$ambiente}_integrity_",
            'eventsSecret'    => "{$ambiente}_events_",
        ];

        foreach ($esperados as $campo => $prefijo) {
            $valor = (string) ($pasarela->{$campo} ?? '');
            if ($valor !== '' && !str_starts_with($valor, $prefijo)) {
                $errores[$campo] = "Debe empezar por {$prefijo} (ambiente " . ($ambiente === 'prod' ? 'producción' : 'pruebas') . ').';
            }
        }

        if ($pasarela->activo && !$pasarela->completa()) {
            foreach (array_keys($esperados) as $campo) {
                if (empty($pasarela->{$campo}) && !isset($errores[$campo])) {
                    $errores[$campo] = 'Es obligatoria para activar el pago en línea.';
                }
            }
        }

        if ($errores) {
            throw ValidationException::withMessages($errores);
        }
    }

    private function formatear(?EmpresaPasarelaPago $pasarela): array
    {
        return [
            'configurada'         => (bool) $pasarela?->exists,
            'environment'         => $pasarela?->environment ?? 'test',
            'publicKey'           => $pasarela?->publicKey,
            'privateKeyFinal'     => $this->finalSecreto($pasarela?->privateKey),
            'integritySecretFinal'=> $this->finalSecreto($pasarela?->integritySecret),
            'eventsSecretFinal'   => $this->finalSecreto($pasarela?->eventsSecret),
            'habilitarPSE'        => $pasarela?->habilitarPSE ?? true,
            'habilitarTarjetas'   => $pasarela?->habilitarTarjetas ?? true,
            'activo'              => $pasarela?->activo ?? true,
            'completa'            => (bool) $pasarela?->completa(),
            'webhookUrl'          => url('api/webhooks/wompi'),
            'actualizado'         => $pasarela?->updated_at?->toDateTimeString(),
        ];
    }

    private function finalSecreto(?string $valor): ?string
    {
        return $valor ? substr($valor, -4) : null;
    }

    private function urlApiWompi(string $publicKey): string
    {
        return str_starts_with($publicKey, 'pub_prod_')
            ? 'https://production.wompi.co/v1'
            : 'https://sandbox.wompi.co/v1';
    }

    /**
     * Permisos del token de sesión. No usa KeyUtil::permissions(): llama ->keys() sobre un array y falla.
     */
    private function tienePermiso(): bool
    {
        $permisos = JWTAuth::getPayload(JWTAuth::getToken())->get('permissions');

        return collect((array) $permisos)
            ->flatten()
            ->map(fn ($p) => (string) $p)
            ->contains(self::PERMISO);
    }
}
