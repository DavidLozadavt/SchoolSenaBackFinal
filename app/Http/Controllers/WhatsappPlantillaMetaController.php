<?php

namespace App\Http\Controllers;

use App\Models\WhatsappPlantillaMeta;
use App\Services\Telecom\MetaTemplateService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Administración completa de plantillas de Meta (WhatsApp Cloud API).
 *
 * Complementa —sin reemplazar— a WhatsappPlantillaController, que sigue
 * administrando la tabla local `whatsappPlantillas` usada por el flujo actual
 * de envío de campañas.
 *
 * Las credenciales (accessToken, businessAccountId, graphVersion) se toman
 * SIEMPRE de la configuración activa del módulo Configuración WhatsApp.
 */
class WhatsappPlantillaMetaController extends Controller
{
    /**
     * Listado local con filtros (buscador, categoría, estado, idioma).
     */
    public function index(Request $request)
    {
        try {
            $query = WhatsappPlantillaMeta::query();

            if ($buscar = $request->get('buscar')) {
                $query->where(function ($q) use ($buscar) {
                    $q->where('nombre', 'like', "%{$buscar}%")
                        ->orWhere('contenido', 'like', "%{$buscar}%");
                });
            }

            foreach (['categoria', 'idioma'] as $campo) {
                if ($valor = $request->get($campo)) {
                    $query->where($campo, $valor);
                }
            }

            if ($estado = $request->get('estado')) {
                $query->where('estadoMeta', strtoupper($estado));
            }

            if ($request->boolean('soloAprobadas')) {
                $query->aprobadas();
            }

            return response()->json($query->orderBy('nombre')->get(), 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al obtener las plantillas: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Detalle de una plantilla (incluye la respuesta completa de Meta).
     */
    public function show($id)
    {
        try {
            return response()->json(WhatsappPlantillaMeta::findOrFail($id), 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Plantilla no encontrada.'], 404);
        }
    }

    /**
     * Crea la plantilla EN META y guarda localmente la respuesta completa.
     */
    public function store(Request $request, MetaTemplateService $meta)
    {
        try {
            $data = $request->validate([
                'nombre'             => ['required', 'string', 'max:120', 'regex:/^[a-z0-9_]+$/'],
                'categoria'          => ['required', 'string', 'in:UTILITY,MARKETING,AUTHENTICATION'],
                'idioma'             => ['required', 'string', 'max:10'],
                'contenido'          => ['required', 'string', 'max:1024'],
                'encabezado'         => ['nullable', 'string', 'max:60'],
                'pie'                => ['nullable', 'string', 'max:60'],
                'variablesEjemplo'   => ['nullable', 'array'],
                'variablesEjemplo.*' => ['nullable', 'string'],
                'botones'            => ['nullable', 'array'],
                'botones.*.tipo'     => ['required_with:botones', 'string', 'in:QUICK_REPLY,URL,PHONE_NUMBER'],
                'botones.*.texto'    => ['required_with:botones', 'string', 'max:25'],
                'botones.*.valor'    => ['nullable', 'string', 'max:2000'],
            ], [
                'nombre.regex' => 'El nombre solo admite minúsculas, números y guion bajo (requisito de Meta).',
            ]);

            if (!$meta->estaConfigurado()) {
                return response()->json([
                    'error' => 'No hay una configuración activa de WhatsApp Cloud API con Business Account (WABA). Configúrela en el módulo Configuración WhatsApp.',
                ], 422);
            }

            $existente = WhatsappPlantillaMeta::where('nombre', $data['nombre'])
                ->where('idioma', $data['idioma'])
                ->first();

            if ($existente) {
                return response()->json(['error' => 'Ya existe una plantilla con ese nombre e idioma.'], 422);
            }

            $resultado = $meta->crearPlantilla($data);

            if (!$resultado['ok']) {
                return response()->json(['error' => 'Meta rechazó la plantilla: ' . $resultado['error']], 422);
            }

            $usuario = auth()->user();

            $plantilla = WhatsappPlantillaMeta::create([
                'metaTemplateId'       => $resultado['id'] ?? null,
                'nombre'               => $data['nombre'],
                'categoria'            => $resultado['category'] ?? $data['categoria'],
                'idioma'               => $data['idioma'],
                'estadoMeta'           => $resultado['status'] ?? 'PENDING',
                'contenido'            => $data['contenido'],
                'encabezado'           => $data['encabezado'] ?? null,
                'pie'                  => $data['pie'] ?? null,
                'variablesEjemplo'     => $data['variablesEjemplo'] ?? [],
                'botones'              => $data['botones'] ?? [],
                'respuestaMeta'        => $resultado['raw'] ?? null,
                'creadoPorUserId'      => $usuario?->id,
                'creadoPorNombre'      => $usuario?->name ?: $usuario?->email,
                'ultimaSincronizacion' => now(),
            ]);

            return response()->json([
                'message'   => 'Plantilla enviada a Meta correctamente. Quedará disponible cuando Meta la apruebe.',
                'plantilla' => $plantilla,
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['error' => $e->validator->errors()->first()], 400);
        } catch (\Exception $e) {
            Log::error('Error al crear la plantilla en Meta: ' . $e->getMessage());

            return response()->json(['error' => 'Error al crear la plantilla: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Sincroniza TODAS las plantillas con Meta: actualiza estado/categoría/idioma
     * y nombre, e importa las que existan en Meta pero no localmente. Sin duplicados
     * (clave lógica: metaTemplateId, o nombre + idioma).
     */
    public function sincronizar(MetaTemplateService $meta)
    {
        try {
            if (!$meta->estaConfigurado()) {
                return response()->json([
                    'error' => 'No hay una configuración activa de WhatsApp Cloud API con Business Account (WABA).',
                ], 422);
            }

            $resultado = $meta->listarPlantillas();

            if (!$resultado['ok']) {
                return response()->json(['error' => 'Error al consultar Meta: ' . $resultado['error']], 422);
            }

            $actualizadas = 0;
            $importadas   = 0;
            $ahora        = now();

            foreach ($resultado['data'] as $remota) {
                $nombre = $remota['name'] ?? null;
                $idioma = $remota['language'] ?? 'es';

                if (!$nombre) {
                    continue;
                }

                $plantilla = null;
                if (!empty($remota['id'])) {
                    $plantilla = WhatsappPlantillaMeta::where('metaTemplateId', $remota['id'])->first();
                }
                $plantilla ??= WhatsappPlantillaMeta::where('nombre', $nombre)
                    ->where('idioma', $idioma)
                    ->first();

                $estado = strtoupper($remota['status'] ?? 'PENDING');

                $atributos = [
                    'metaTemplateId'       => $remota['id'] ?? $plantilla?->metaTemplateId,
                    'nombre'               => $nombre,
                    'idioma'               => $idioma,
                    'categoria'            => strtoupper($remota['category'] ?? 'UTILITY'),
                    'estadoMeta'           => $estado,
                    'contenido'            => $meta->extraerComponente($remota, 'BODY') ?? $plantilla?->contenido,
                    'encabezado'           => $meta->extraerComponente($remota, 'HEADER'),
                    'pie'                  => $meta->extraerComponente($remota, 'FOOTER'),
                    'botones'              => $meta->extraerBotones($remota),
                    'respuestaMeta'        => $remota,
                    'motivoRechazo'        => $remota['rejected_reason'] ?? null,
                    'ultimaSincronizacion' => $ahora,
                ];

                if ($estado === 'APPROVED') {
                    // Meta no expone fecha de aprobación: se registra la primera
                    // sincronización en la que aparece como aprobada.
                    $atributos['fechaAprobacion'] = $plantilla?->fechaAprobacion ?? $ahora;
                }

                if ($plantilla) {
                    $plantilla->update($atributos);
                    $actualizadas++;
                } else {
                    WhatsappPlantillaMeta::create($atributos + [
                        'creadoPorNombre' => 'Importada desde Meta',
                    ]);
                    $importadas++;
                }
            }

            return response()->json([
                'message'      => "Sincronización completada. Actualizadas: {$actualizadas}, importadas: {$importadas}.",
                'actualizadas' => $actualizadas,
                'importadas'   => $importadas,
                'plantillas'   => WhatsappPlantillaMeta::orderBy('nombre')->get(),
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error al sincronizar plantillas con Meta: ' . $e->getMessage());

            return response()->json(['error' => 'Error al sincronizar: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Sincroniza UNA plantilla puntual contra Meta.
     */
    public function sincronizarUna($id, MetaTemplateService $meta)
    {
        try {
            $plantilla = WhatsappPlantillaMeta::findOrFail($id);

            $resultado = $meta->listarPlantillas();

            if (!$resultado['ok']) {
                return response()->json(['error' => 'Error al consultar Meta: ' . $resultado['error']], 422);
            }

            foreach ($resultado['data'] as $remota) {
                $coincide = (!empty($remota['id']) && $remota['id'] === $plantilla->metaTemplateId)
                    || (($remota['name'] ?? null) === $plantilla->nombre && ($remota['language'] ?? 'es') === $plantilla->idioma);

                if (!$coincide) {
                    continue;
                }

                $estado = strtoupper($remota['status'] ?? 'PENDING');

                $plantilla->update([
                    'metaTemplateId'       => $remota['id'] ?? $plantilla->metaTemplateId,
                    'categoria'            => strtoupper($remota['category'] ?? $plantilla->categoria),
                    'estadoMeta'           => $estado,
                    'contenido'            => $meta->extraerComponente($remota, 'BODY') ?? $plantilla->contenido,
                    'botones'              => $meta->extraerBotones($remota),
                    'respuestaMeta'        => $remota,
                    'motivoRechazo'        => $remota['rejected_reason'] ?? null,
                    'fechaAprobacion'      => $estado === 'APPROVED' ? ($plantilla->fechaAprobacion ?? now()) : $plantilla->fechaAprobacion,
                    'ultimaSincronizacion' => now(),
                ]);

                return response()->json(['message' => 'Plantilla sincronizada.', 'plantilla' => $plantilla->fresh()], 200);
            }

            return response()->json(['error' => 'La plantilla ya no existe en Meta.'], 404);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al sincronizar la plantilla: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Elimina la plantilla SOLO del sistema local (no la borra en Meta).
     */
    public function destroy($id)
    {
        try {
            WhatsappPlantillaMeta::findOrFail($id)->delete();

            return response()->json(['message' => 'Plantilla eliminada localmente. Sigue existiendo en Meta.'], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al eliminar la plantilla: ' . $e->getMessage()], 500);
        }
    }
}
