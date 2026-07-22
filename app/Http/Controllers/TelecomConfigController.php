<?php

namespace App\Http\Controllers;

use App\Models\TelecomConfig;
use App\Services\Telecom\MetaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * CRUD para administrar la configuración local de WhatsApp Cloud API (Meta)
 * desde este mismo backend. Módulo autónomo, sin dependencias externas.
 */
class TelecomConfigController extends Controller
{
    /**
     * Listar todas las configuraciones.
     */
    public function index()
    {
        return response()->json(TelecomConfig::orderByDesc('id')->get(), 200);
    }

    /**
     * Mostrar la configuración activa vigente.
     */
    public function activa()
    {
        $config = TelecomConfig::activa();

        if (!$config) {
            return response()->json(['error' => 'No hay configuración activa.'], 404);
        }

        return response()->json($config, 200);
    }

    /**
     * Crear una nueva configuración.
     */
    public function store(Request $request)
    {
        $data = $this->validar($request);

        $config = DB::transaction(function () use ($data) {
            if (($data['activo'] ?? true)) {
                TelecomConfig::query()->update(['activo' => false]);
            }

            return TelecomConfig::create($data);
        });

        return response()->json([
            'message' => 'Configuración de WhatsApp Cloud API creada correctamente.',
            'config'  => $config,
        ], 201);
    }

    /**
     * Actualizar una configuración existente.
     */
    public function update(Request $request, $id)
    {
        $config = TelecomConfig::findOrFail($id);
        $data = $this->validar($request, true);

        DB::transaction(function () use ($config, $data) {
            if (($data['activo'] ?? false)) {
                TelecomConfig::where('id', '!=', $config->id)->update(['activo' => false]);
            }

            $config->update($data);
        });

        return response()->json([
            'message' => 'Configuración actualizada correctamente.',
            'config'  => $config->fresh(),
        ], 200);
    }

    /**
     * Eliminar una configuración.
     */
    public function destroy($id)
    {
        $config = TelecomConfig::findOrFail($id);
        $config->delete();

        return response()->json(['message' => 'Configuración eliminada correctamente.'], 200);
    }

    /**
     * Probar el envío de un mensaje de texto con la configuración activa.
     */
    public function probar(Request $request)
    {
        $data = $request->validate([
            'numero'  => ['required', 'string'],
            'mensaje' => ['nullable', 'string'],
        ]);

        $service = new MetaService();

        if (!$service->estaConfigurado()) {
            return response()->json(['error' => 'No hay una configuración activa de WhatsApp Cloud API.'], 422);
        }

        $resultado = $service->enviarTexto(
            $data['numero'],
            $data['mensaje'] ?? 'Mensaje de prueba desde el módulo de Seguimiento de Aspirantes.'
        );

        return response()->json($resultado, $resultado['ok'] ? 200 : 502);
    }

    /**
     * Reglas de validación reutilizables.
     */
    private function validar(Request $request, bool $esUpdate = false): array
    {
        $requerido = $esUpdate ? 'sometimes' : 'required';

        return $request->validate([
            'provider'          => ['nullable', 'string', 'max:50'],
            'whatsappEnabled'   => ['nullable', 'boolean'],
            'nombre'            => ['nullable', 'string', 'max:150'],
            'accessToken'       => [$requerido, 'string'],
            'phoneNumberId'     => [$requerido, 'string', 'max:100'],
            'businessAccountId' => ['nullable', 'string', 'max:100'],
            'appId'             => ['nullable', 'string', 'max:100'],
            'verifyToken'       => [$requerido, 'string', 'max:255'],
            'appSecret'         => ['nullable', 'string', 'max:255'],
            'webhookUrl'        => ['nullable', 'string', 'max:255'],
            'graphVersion'      => ['nullable', 'string', 'max:20'],
            'activo'            => ['nullable', 'boolean'],
            'idFormularioInscripcion' => ['nullable', 'integer', 'exists:formularios,id'],
        ]);
    }
}
