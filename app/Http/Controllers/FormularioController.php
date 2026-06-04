<?php

namespace App\Http\Controllers;

use App\Models\Formulario;
use App\Models\FormularioPregunta;
use App\Models\FormularioOpcion;
use App\Models\FormularioRespuesta;
use App\Models\Evento;
use App\Models\ParticipanteEvento;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class FormularioController extends Controller
{
    /**
     * Listar formularios de la empresa actual.
     */
    public function index(Request $request)
    {
        $idCompany = $request->user()->idempresa ?? 1; // Ajustar según cómo se obtiene la empresa

        $formularios = Formulario::where('idCompany', $idCompany)
            ->withCount(['preguntas', 'respuestas'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($formularios);
    }

    /**
     * Crear un nuevo formulario con sus preguntas y opciones.
     */
    public function store(Request $request)
    {
        $request->validate([
            'titulo' => 'required|string|max:255',
            'preguntas' => 'array'
        ]);

        try {
            DB::beginTransaction();

            $user = $request->user();
            
            $formulario = Formulario::create([
                'idCompany' => $user->idempresa ?? 1, // Obtener empresa del usuario
                'idUser' => $user->id,
                'titulo' => $request->titulo,
                'descripcion' => $request->descripcion,
                'colorTema' => $request->colorTema ?? '#6366f1',
                'estado' => $request->estado ?? 'borrador',
                'requiereAutenticacion' => $request->requiereAutenticacion ?? false,
            ]);

            if ($request->has('preguntas')) {
                foreach ($request->preguntas as $p) {
                    $pregunta = $formulario->preguntas()->create([
                        'tipo' => $p['tipo'],
                        'titulo' => $p['titulo'],
                        'descripcion' => $p['descripcion'] ?? null,
                        'esObligatoria' => $p['esObligatoria'] ?? false,
                        'orden' => $p['orden'],
                        'configuracion' => $p['configuracion'] ?? null,
                    ]);

                    if (isset($p['opciones']) && is_array($p['opciones'])) {
                        foreach ($p['opciones'] as $o) {
                            $pregunta->opciones()->create([
                                'texto' => $o['texto'],
                                'orden' => $o['orden'],
                            ]);
                        }
                    }
                }
            }

            DB::commit();

            return response()->json($formulario->load('preguntas.opciones'), 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Obtener un formulario específico con preguntas y opciones.
     */
    public function show($id)
    {
        $formulario = Formulario::with('preguntas.opciones')->findOrFail($id);
        return response()->json($formulario);
    }

    /**
     * Actualizar un formulario existente.
     */
    public function update(Request $request, $id)
    {
        $formulario = Formulario::findOrFail($id);

        try {
            DB::beginTransaction();

            $newSlug = $formulario->slug;
            if ($formulario->titulo !== $request->titulo) {
                $oldSlug = $formulario->slug;
                $parts = explode('-', $oldSlug);
                $suffix = end($parts);
                if (strlen($suffix) === 13) {
                    $newSlug = \Illuminate\Support\Str::slug($request->titulo) . '-' . $suffix;
                } else {
                    $newSlug = \Illuminate\Support\Str::slug($request->titulo) . '-' . uniqid();
                }
            }

            $formulario->update([
                'titulo' => $request->titulo,
                'descripcion' => $request->descripcion,
                'colorTema' => $request->colorTema,
                'estado' => $request->estado,
                'requiereAutenticacion' => $request->requiereAutenticacion,
                'slug' => $newSlug,
            ]);

            if ($request->has('preguntas')) {
                // Sincronizar preguntas
                $preguntasActualesIds = [];

                foreach ($request->preguntas as $p) {
                    if (isset($p['id']) && strpos((string)$p['id'], 'new') === false) {
                        // Actualizar pregunta existente
                        $pregunta = FormularioPregunta::find($p['id']);
                        if ($pregunta) {
                            $pregunta->update([
                                'tipo' => $p['tipo'],
                                'titulo' => $p['titulo'],
                                'descripcion' => $p['descripcion'] ?? null,
                                'esObligatoria' => $p['esObligatoria'] ?? false,
                                'orden' => $p['orden'],
                                'configuracion' => $p['configuracion'] ?? null,
                            ]);
                            $preguntasActualesIds[] = $pregunta->id;
                            
                            // Sincronizar opciones
                            if (isset($p['opciones']) && in_array($p['tipo'], ['opcion_multiple', 'casillas', 'desplegable'])) {
                                $opcionesActualesIds = [];
                                foreach ($p['opciones'] as $o) {
                                    if (isset($o['id']) && strpos((string)$o['id'], 'new') === false) {
                                        $opcion = FormularioOpcion::find($o['id']);
                                        if ($opcion) {
                                            $opcion->update(['texto' => $o['texto'], 'orden' => $o['orden']]);
                                            $opcionesActualesIds[] = $opcion->id;
                                        }
                                    } else {
                                        $nuevaOpcion = $pregunta->opciones()->create(['texto' => $o['texto'], 'orden' => $o['orden']]);
                                        $opcionesActualesIds[] = $nuevaOpcion->id;
                                    }
                                }
                                // Eliminar opciones que ya no están
                                FormularioOpcion::where('idFormularioPregunta', $pregunta->id)
                                    ->whereNotIn('id', $opcionesActualesIds)
                                    ->delete();
                            }
                        }
                    } else {
                        // Crear nueva pregunta
                        $pregunta = $formulario->preguntas()->create([
                            'tipo' => $p['tipo'],
                            'titulo' => $p['titulo'],
                            'descripcion' => $p['descripcion'] ?? null,
                            'esObligatoria' => $p['esObligatoria'] ?? false,
                            'orden' => $p['orden'],
                            'configuracion' => $p['configuracion'] ?? null,
                        ]);
                        $preguntasActualesIds[] = $pregunta->id;

                        if (isset($p['opciones']) && is_array($p['opciones'])) {
                            foreach ($p['opciones'] as $o) {
                                $pregunta->opciones()->create([
                                    'texto' => $o['texto'],
                                    'orden' => $o['orden'],
                                ]);
                            }
                        }
                    }
                }

                // Eliminar preguntas que ya no están
                FormularioPregunta::where('idFormulario', $formulario->id)
                    ->whereNotIn('id', $preguntasActualesIds)
                    ->delete();
            }

            DB::commit();

            return response()->json($formulario->load('preguntas.opciones'));
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Eliminar formulario.
     */
    public function destroy($id)
    {
        $formulario = Formulario::findOrFail($id);
        
        // Desvincular formulario de los eventos asociados
        Evento::where('idFormularioInterno', $id)->update([
            'idFormularioInterno' => null,
            'formUrl' => null,
            'formProvider' => null
        ]);

        $formulario->delete();
        return response()->json(['message' => 'Formulario eliminado.']);
    }

    /**
     * Ver formulario público por slug o ID.
     */
    public function showPublic($slugOrId)
    {
        $formulario = Formulario::with('preguntas.opciones')
            ->where('slug', $slugOrId)
            ->orWhere('id', $slugOrId)
            ->firstOrFail();
        
        $isAuth = Auth::check() || Auth::guard('api')->check();
        if ($formulario->estado !== 'publicado' && !$isAuth) {
            return response()->json(['error' => 'Formulario no disponible'], 403);
        }

        return response()->json($formulario);
    }

    /**
     * Guardar respuestas del formulario.
     */
    public function responder(Request $request, $slugOrId)
    {
        $formulario = Formulario::where('slug', $slugOrId)
            ->orWhere('id', $slugOrId)
            ->firstOrFail();

        $isAuth = Auth::check() || Auth::guard('api')->check();
        $user = Auth::user() ?: Auth::guard('api')->user();

        if ($formulario->estado !== 'publicado' && !$isAuth) {
            return response()->json(['error' => 'Formulario no disponible'], 403);
        }

        if ($formulario->requiereAutenticacion && !$isAuth) {
            return response()->json(['error' => 'Debe iniciar sesión para responder'], 401);
        }

        $userId = $user ? $user->id : null;

        // Validar si ya respondió (si requiere auth y el formulario está publicado)
        if ($formulario->estado === 'publicado' && $formulario->requiereAutenticacion && FormularioRespuesta::where('idFormulario', $formulario->id)->where('idUser', $userId)->exists()) {
            return response()->json(['error' => 'Ya has respondido este formulario'], 403);
        }

        try {
            DB::beginTransaction();

            $respuesta = FormularioRespuesta::create([
                'idFormulario' => $formulario->id,
                'idUser' => $userId,
                'ipAddress' => $request->ip(),
                'respuestas' => $request->respuestas ?? [],
            ]);

            // Auto-inscribir a evento si está asociado
            $evento = Evento::where('idFormularioInterno', $formulario->id)->first();
            if ($evento && $user) {
                if ($user->idpersona) {
                    // Buscamos si el usuario ya es participante
                    $participante = ParticipanteEvento::where('idEvento', $evento->idEvento)
                        ->where('idPersona', $user->idpersona)
                        ->first();
                    
                    if (!$participante) {
                        $participante = ParticipanteEvento::create([
                            'idEvento' => $evento->idEvento,
                            'idPersona' => $user->idpersona,
                            'estado' => 'CONFIRMADO' // Ajustar al valor real
                        ]);
                    } else {
                        // Actualizamos estado a CONFIRMADO
                        $participante->update([
                            'estado' => 'CONFIRMADO' 
                        ]);
                    }
                }
            }

            DB::commit();

            if ($formulario->slug === \App\Services\Inscripcion\FormularioInscripcionDatosService::SLUG_INSCRIPCION) {
                /** @var \App\Services\Inscripcion\FormularioInscripcionDatosService $inscripcionDatos */
                $inscripcionDatos = app(\App\Services\Inscripcion\FormularioInscripcionDatosService::class);
                $tercero = $inscripcionDatos->sincronizarTerceroDesdeRespuesta(
                    $respuesta,
                    (int) ($formulario->idCompany ?? 1)
                );

                /** @var \App\Services\Inscripcion\SeguimientoInscripcionService $seguimientoService */
                $seguimientoService = app(\App\Services\Inscripcion\SeguimientoInscripcionService::class);
                $seguimientoService->registrarSolicitudDesdeFormulario(
                    $respuesta,
                    (int) ($formulario->idCompany ?? 1),
                    $tercero
                );
            }

            return response()->json(['message' => 'Respuesta guardada con éxito', 'data' => $respuesta]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Obtener respuestas de un formulario (para el builder).
     */
    public function respuestas($id)
    {
        $respuestas = FormularioRespuesta::where('idFormulario', $id)
            ->with(['usuario' => function($query) {
                $query->with('persona:id,nombre1,nombre2,apellido1,apellido2');
            }])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($r) {
                if ($r->usuario) {
                    $persona = $r->usuario->persona;
                    $parts = array_filter([
                        $persona->nombre1 ?? '',
                        $persona->nombre2 ?? '',
                        $persona->apellido1 ?? '',
                        $persona->apellido2 ?? '',
                    ]);
                    $r->usuario->name = implode(' ', $parts) ?: $r->usuario->email;
                }
                return $r;
            });

        return response()->json($respuestas);
    }

    /**
     * Subir archivo adjunto públicamente para formularios.
     */
    public function uploadAdjunto(Request $request)
    {
        $request->validate([
            'archivo' => 'required|file|mimes:pdf,jpeg,png,jpg,doc,docx|max:5120',
        ]);

        try {
            if ($request->hasFile('archivo')) {
                $file = $request->file('archivo');
                $path = $file->store('formularios/adjuntos', 'public');
                $url = asset('storage/' . $path);

                return response()->json([
                    'success' => true,
                    'url' => $url,
                    'path' => $path
                ]);
            }
            return response()->json(['error' => 'No se recibió ningún archivo'], 400);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}

