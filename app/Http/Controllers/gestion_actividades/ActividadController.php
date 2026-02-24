<?php

namespace App\Http\Controllers\gestion_actividades;

use App\Http\Controllers\Controller;
use App\Models\Actividad;
use App\Models\TipoActividad;
use App\Models\ClasificacionActividad;
use App\Models\PlaneacionActividad;
use App\Models\Materia;
use App\Models\Status;
use App\Models\MaterialApoyoActividad;
use App\Models\AsignacionMaterialApoyoActividad;
use App\Models\Pregunta;
use App\Models\TipoPregunta;
use App\Models\Respuesta;
use App\Util\KeyUtil;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class ActividadController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try {
            $idCompany = KeyUtil::idCompany();
            $query = Actividad::with(['materia', 'estado', 'clasificacion', 'persona'])
                ->where('idCompany', $idCompany);

            if ($request->has('search') && $request->search) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('tituloActividad', 'like', "%{$search}%")
                        ->orWhere('descripcionActividad', 'like', "%{$search}%");
                });
            }

            $actividades = $query->orderBy('id', 'desc')->get();
            return response()->json($actividades);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'tituloActividad' => 'required|string',
                'descripcionActividad' => 'required|string',
                'pathDocumentoActividad' => 'nullable|string|max:255',
                'autor' => 'nullable|string|max:255',
                'tipoActividad' => 'required|in:sin evidencia,con evidencia,cuestionario',
                'idMateria' => 'required|exists:materia,id',
                'idEstado' => 'nullable|exists:estado,id',
                'idCompany' => 'required|exists:empresa,id',
                'idPersona' => 'nullable|exists:persona,id',
                'idClasificacion' => 'nullable|exists:clasificacionactividad,id',
                'estrategia' => 'required|string',
                'entregables' => 'required|string',
            ]);
            if (empty($validated['idPersona'])) {
                $user = KeyUtil::user();
                $validated['idPersona'] = $user?->idpersona ?? null;
            }

            $actividad = Actividad::create($validated);
            return response()->json($actividad, 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function update(Request $request, int $id): JsonResponse
    {
        try {
            $actividad = Actividad::findOrFail($id);
            $validated = $request->validate([
                'tituloActividad' => 'sometimes|required|string',
                'descripcionActividad' => 'sometimes|required|string',
                'pathDocumentoActividad' => 'nullable|string|max:255',
                'autor' => 'nullable|string|max:255',
                'tipoActividad' => 'sometimes|required|in:sin evidencia,con evidencia,cuestionario',
                'idMateria' => 'sometimes|required|exists:materia,id',
                'idEstado' => 'nullable|exists:estado,id',
                'idCompany' => 'sometimes|required|exists:empresa,id',
                'idPersona' => 'nullable|exists:persona,id',
                'idClasificacion' => 'nullable|exists:clasificacionactividad,id',
                'estrategia' => 'sometimes|required|string',
                'entregables' => 'sometimes|required|string',
            ]);

            $actividad->update($validated);
            return response()->json($actividad);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function show(int $id): JsonResponse
    {
        try {
            $actividad = Actividad::with(['materia', 'estado', 'clasificacion', 'persona'])->findOrFail($id);
            if ($actividad->tipoActividad === 'cuestionario') {
                $actividad->load(['preguntas.tipoPregunta', 'preguntas.respuestas']);
            }
            return response()->json($actividad);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 404);
        }
    }

    public function destroy(int $id): JsonResponse
    {
        try {
            $actividad = Actividad::findOrFail($id);
            $tieneAsignacion = PlaneacionActividad::where('idActividad', $id)->exists();
            if ($tieneAsignacion) {
                return response()->json(['error' => 'No se puede eliminar una actividad que está asignada a una clase. Quítela primero de la clase.'], 422);
            }
            $actividad->delete();
            return response()->json(['message' => 'Actividad eliminada']);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function tipoActividades(): JsonResponse
    {
        try {
            $idCompany = KeyUtil::idCompany();
            $tipos = TipoActividad::where('idCompany', $idCompany)->get();
            return response()->json($tipos);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function clasificaciones(): JsonResponse
    {
        try {
            $idCompany = KeyUtil::idCompany();
            $clasificaciones = ClasificacionActividad::where('idCompany', $idCompany)
                ->orWhereNull('idCompany')
                ->get();
            return response()->json($clasificaciones);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function materias(): JsonResponse
    {
        try {
            $idCompany = KeyUtil::idCompany();
            $materias = Materia::where('idEmpresa', $idCompany)->get();
            return response()->json($materias);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function estados(): JsonResponse
    {
        try {
            $estados = Status::all();
            return response()->json($estados);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function uploadDocumento(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'documento' => 'required|file|mimes:pdf,doc,docx|max:10240',
            ]);

            $file = $request->file('documento');
            $dir = 'actividades/documentos';
            if (!Storage::disk('public')->exists($dir)) {
                Storage::disk('public')->makeDirectory($dir);
            }
            $filename = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $file->getClientOriginalName());
            $path = $file->storeAs($dir, $filename, 'public');
            $url = Storage::disk('public')->url($path);
            return response()->json(['path' => $path, 'url' => $url]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Sube el documento principal de una actividad en actividades/{id}/
     */
    public function uploadDocumentoActividad(Request $request, int $id): JsonResponse
    {
        try {
            $actividad = Actividad::findOrFail($id);
            $request->validate([
                'documento' => 'required|file|mimes:pdf,doc,docx|max:10240',
            ]);

            $file = $request->file('documento');
            $dir = "actividades/{$id}";
            if (!Storage::disk('public')->exists($dir)) {
                Storage::disk('public')->makeDirectory($dir);
            }
            $filename = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $file->getClientOriginalName());
            $path = $file->storeAs($dir, $filename, 'public');
            $actividad->update(['pathDocumentoActividad' => $path]);
            $url = Storage::disk('public')->url($path);
            return response()->json(['path' => $path, 'url' => $url]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function planeacionPorFicha(int $idFicha): JsonResponse
    {
        try {
            $ficha = \App\Models\Ficha::with('asignacion')->findOrFail($idFicha);
            $idAsignacion = $ficha->idAsignacion ?? null;
            if (!$idAsignacion) {
                return response()->json(['id' => null]);
            }
            if (\Illuminate\Support\Facades\Schema::hasTable('planeacion') && \Illuminate\Support\Facades\Schema::hasColumn('planeacion', 'idAsignacion')) {
                $planeacion = \Illuminate\Support\Facades\DB::table('planeacion')
                    ->where('idAsignacion', $idAsignacion)->first();
                return response()->json($planeacion ?? ['id' => $idAsignacion]);
            }
            return response()->json(['id' => $idAsignacion]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function planeacionActividadesPorFicha(int $idFicha): JsonResponse
    {
        try {
            $ficha = \App\Models\Ficha::with('asignacion')->findOrFail($idFicha);
            $idAsignacion = $ficha->idAsignacion ?? null;
            if (!$idAsignacion) {
                return response()->json([]);
            }
            $idPlaneacion = null;
            if (\Illuminate\Support\Facades\Schema::hasTable('planeacion') && \Illuminate\Support\Facades\Schema::hasColumn('planeacion', 'idAsignacion')) {
                $planeacion = \Illuminate\Support\Facades\DB::table('planeacion')
                    ->where('idAsignacion', $idAsignacion)->first();
                $idPlaneacion = $planeacion->id ?? null;
            } else {
                $idPlaneacion = $idAsignacion;
            }
            if (!$idPlaneacion) {
                return response()->json([]);
            }
            $items = PlaneacionActividad::with(['actividad.persona', 'actividad.materia', 'actividad.estado'])
                ->where('idPlaneacion', $idPlaneacion)
                ->get();
            return response()->json($items);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function asignarPlaneacionActividad(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'idActividad' => 'required|exists:actividades,id',
                'idMateria' => 'required|exists:materia,id',
                'idPlaneacion' => 'required',
            ]);

            $item = PlaneacionActividad::create($validated);
            return response()->json($item, 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function quitarPlaneacionActividad(int $id): JsonResponse
    {
        try {
            $item = PlaneacionActividad::findOrFail($id);
            $item->delete();
            return response()->json(['message' => 'Actividad quitada de la planeación']);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function materialesApoyo(int $idActividad): JsonResponse
    {
        try {
            $actividad = Actividad::findOrFail($idActividad);
            $materiales = $actividad->materialesApoyo()->get();
            return response()->json($materiales);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function storeMaterialApoyo(Request $request, int $idActividad): JsonResponse
    {
        try {
            $actividad = Actividad::findOrFail($idActividad);
            $request->validate([
                'titulo' => 'required|string|max:255',
                'descripcion' => 'nullable|string|max:3000',
                'documento' => 'nullable|file|mimes:pdf|max:10240',
                'urlAdicional' => 'nullable|string|max:500',
            ]);

            $path = null;
            if ($request->hasFile('documento')) {
                $file = $request->file('documento');
                $dir = "actividades/{$idActividad}/material-apoyo";
                if (!Storage::disk('public')->exists($dir)) {
                    Storage::disk('public')->makeDirectory($dir, 0755, true);
                }
                $filename = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $file->getClientOriginalName());
                $path = $file->storeAs($dir, $filename, 'public');
            }

            if (!$path && empty($request->urlAdicional)) {
                return response()->json(['errors' => ['Se requiere documento PDF o enlace']], 422);
            }

            $material = MaterialApoyoActividad::create([
                'titulo' => $request->titulo,
                'descripcion' => $request->descripcion ?? null,
                'urlDocumento' => $path,
                'urlAdicional' => $request->urlAdicional ? trim($request->urlAdicional) : null,
                'idMateria' => $actividad->idMateria,
            ]);

            AsignacionMaterialApoyoActividad::create([
                'idActividad' => $idActividad,
                'idMaterialApoyo' => $material->id,
            ]);

            return response()->json($material, 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function destroyMaterialApoyo(int $idActividad, int $idMaterialApoyo): JsonResponse
    {
        try {
            $asignacion = AsignacionMaterialApoyoActividad::where('idActividad', $idActividad)
                ->where('idMaterialApoyo', $idMaterialApoyo)
                ->firstOrFail();
            $material = MaterialApoyoActividad::find($idMaterialApoyo);
            $asignacion->delete();
            if ($material) {
                if ($material->urlDocumento && Storage::disk('public')->exists($material->urlDocumento)) {
                    Storage::disk('public')->delete($material->urlDocumento);
                }
                $material->delete();
            }
            return response()->json(['message' => 'Material de apoyo eliminado']);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function storeCuestionario(Request $request): JsonResponse
    {
        try {
            $preguntasData = is_string($request->preguntas) ? json_decode($request->preguntas, true) : $request->preguntas;
            $request->merge(['preguntas' => $preguntasData ?? []]);

            $request->validate([
                'titulo' => 'required|string|max:500',
                'clasificacion' => 'nullable|string|max:255',
                'descripcion' => 'nullable|string',
                'idMateria' => 'required|exists:materia,id',
                'preguntas' => 'required|array|min:1',
                'preguntas.*.tipo' => 'required|in:Párrafo,Varias opciones',
                'preguntas.*.titulo' => 'required|string|max:1000',
            ]);

            $user = KeyUtil::user();
            $idCompany = KeyUtil::idCompany();

            $actividad = Actividad::create([
                'tituloActividad' => $request->titulo,
                'descripcionActividad' => $request->descripcion ?? null,
                'pathDocumentoActividad' => null,
                'autor' => $request->clasificacion ?? null,
                'tipoActividad' => 'cuestionario',
                'idMateria' => $request->idMateria,
                'idEstado' => 1,
                'idCompany' => $idCompany,
                'idPersona' => $user?->idpersona ?? null,
                'idClasificacion' => null,
                'estrategia' => 'Cuestionario',
                'entregables' => 'Respuestas al cuestionario',
            ]);

            $tiposPregunta = TipoPregunta::pluck('id', 'tipoPregunta')->toArray();

            foreach ($request->preguntas as $i => $p) {
                $idTipo = $tiposPregunta[$p['tipo']] ?? 1;
                $urlDoc = null;

                $fileKey = "foto_pregunta_{$i}";
                if ($request->hasFile($fileKey)) {
                    $file = $request->file($fileKey);
                    $dir = "cuestionarios/{$actividad->id}/preguntas";
                    if (!Storage::disk('public')->exists($dir)) {
                        Storage::disk('public')->makeDirectory($dir, 0755, true);
                    }
                    $filename = time() . '_' . $i . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $file->getClientOriginalName());
                    $urlDoc = $file->storeAs($dir, $filename, 'public');
                }

                $pregunta = Pregunta::create([
                    'descripcion' => $p['titulo'],
                    'puntaje' => 1,
                    'idTipoPregunta' => $idTipo,
                    'idActividad' => $actividad->id,
                    'urlDocumento' => $urlDoc,
                ]);

                if (($p['tipo'] ?? '') === 'Varias opciones' && !empty($p['opciones'])) {
                    foreach ($p['opciones'] as $op) {
                        if (!empty(trim($op['texto'] ?? ''))) {
                            Respuesta::create([
                                'idPregunta' => $pregunta->id,
                                'descripcionRespuesta' => $op['texto'],
                                'chkCorrecta' => (bool)($op['esCorrecta'] ?? false),
                                'puntaje' => ($op['esCorrecta'] ?? false) ? 1 : 0,
                            ]);
                        }
                    }
                }
            }

            return response()->json($actividad->load(['materia', 'estado', 'persona']), 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function updateCuestionario(Request $request, int $id): JsonResponse
    {
        try {
            $actividad = Actividad::findOrFail($id);
            if ($actividad->tipoActividad !== 'cuestionario') {
                return response()->json(['error' => 'La actividad no es un cuestionario'], 422);
            }

            $preguntasData = is_string($request->preguntas) ? json_decode($request->preguntas, true) : $request->preguntas;
            $request->merge(['preguntas' => $preguntasData ?? []]);

            $request->validate([
                'titulo' => 'required|string|max:500',
                'clasificacion' => 'nullable|string|max:255',
                'descripcion' => 'nullable|string',
                'idMateria' => 'required|exists:materia,id',
                'preguntas' => 'required|array|min:1',
                'preguntas.*.tipo' => 'required|in:Párrafo,Varias opciones',
                'preguntas.*.titulo' => 'required|string|max:1000',
            ]);

            $actividad->update([
                'tituloActividad' => $request->titulo,
                'descripcionActividad' => $request->descripcion ?? null,
                'autor' => $request->clasificacion ?? null,
                'idMateria' => $request->idMateria,
            ]);

            $idsPreguntas = $actividad->preguntas()->pluck('id')->toArray();
            if (!empty($idsPreguntas) && \Illuminate\Support\Facades\Schema::hasTable('respuesta_cuestionarios')) {
                \Illuminate\Support\Facades\DB::table('respuesta_cuestionarios')->whereIn('idPregunta', $idsPreguntas)->delete();
            }
            foreach ($actividad->preguntas as $preg) {
                Respuesta::where('idPregunta', $preg->id)->delete();
            }
            $actividad->preguntas()->delete();

            $tiposPregunta = TipoPregunta::pluck('id', 'tipoPregunta')->toArray();

            foreach ($request->preguntas as $i => $p) {
                $idTipo = $tiposPregunta[$p['tipo']] ?? 1;
                $urlDoc = null;

                $fileKey = "foto_pregunta_{$i}";
                if ($request->hasFile($fileKey)) {
                    $file = $request->file($fileKey);
                    $dir = "cuestionarios/{$actividad->id}/preguntas";
                    if (!Storage::disk('public')->exists($dir)) {
                        Storage::disk('public')->makeDirectory($dir, 0755, true);
                    }
                    $filename = time() . '_' . $i . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $file->getClientOriginalName());
                    $urlDoc = $file->storeAs($dir, $filename, 'public');
                }

                $pregunta = Pregunta::create([
                    'descripcion' => $p['titulo'],
                    'puntaje' => 1,
                    'idTipoPregunta' => $idTipo,
                    'idActividad' => $actividad->id,
                    'urlDocumento' => $urlDoc,
                ]);

                if (($p['tipo'] ?? '') === 'Varias opciones' && !empty($p['opciones'])) {
                    foreach ($p['opciones'] as $op) {
                        if (!empty(trim($op['texto'] ?? ''))) {
                            Respuesta::create([
                                'idPregunta' => $pregunta->id,
                                'descripcionRespuesta' => $op['texto'],
                                'chkCorrecta' => (bool)($op['esCorrecta'] ?? false),
                                'puntaje' => ($op['esCorrecta'] ?? false) ? 1 : 0,
                            ]);
                        }
                    }
                }
            }

            return response()->json($actividad->load(['materia', 'estado', 'persona', 'preguntas.respuestas']));
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
