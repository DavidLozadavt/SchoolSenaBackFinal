<?php

namespace App\Http\Controllers\ambiente_virtual;

use App\Http\Controllers\Concerns\ValidatesMaterialDocumentUpload;
use App\Http\Controllers\Controller;
use App\Models\Actividad;
use App\Models\AsignacionMaterialApoyoActividad;
use App\Models\MaterialApoyoActividad;
use App\Models\MaterialCompetencia;
use App\Models\MaterialCompetenciaRecurso;
use App\Models\Materia;
use App\Models\Person;
use App\Services\ambiente_virtual\RapAccessService;
use App\Support\MaterialCompetenciaEnlace;
use App\Support\MaterialCompetenciaReglas;
use App\Util\KeyUtil;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class MaterialCompetenciaController extends Controller
{
    use ValidatesMaterialDocumentUpload;

    public function __construct(private RapAccessService $rapAccess)
    {
    }

    public function competencias(): JsonResponse
    {
        try {
            if (! $this->usuarioAutorizadoModulo()) {
                return response()->json(['error' => 'No autorizado para Material de Apoyo.'], 403);
            }

            return response()->json([
                'data' => $this->rapAccess->competenciasAccesibles(),
                'puedeAdministrar' => $this->rapAccess->usuarioPuedeAdministrar(),
            ]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function index(int $idCompetencia): JsonResponse
    {
        try {
            $gate = $this->asegurarCompetencia($idCompetencia);
            if ($gate instanceof JsonResponse) {
                return $gate;
            }
            if (! $this->tablaLista()) {
                return response()->json(['error' => $this->mensajeMigracion()], 503);
            }

            $rows = MaterialCompetencia::query()
                ->where('idCompetencia', $idCompetencia)
                ->where('activo', true)
                ->with($this->relacionesMaterial())
                ->orderByDesc('id')
                ->get();

            $puedeAdministrar = $this->rapAccess->usuarioPuedeAdministrar();

            return response()->json($rows->map(fn (MaterialCompetencia $m) => $this->toResource($m, $puedeAdministrar))->values());
        } catch (QueryException $e) {
            if ($this->esTablaAusente($e)) {
                return response()->json(['error' => $this->mensajeMigracion()], 503);
            }

            return response()->json(['error' => $e->getMessage()], 500);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Materiales de todas las competencias accesibles del usuario (vista aprendiz).
     * No sustituye el index por competencia que usa el instructor.
     */
    public function indexTodos(): JsonResponse
    {
        try {
            if (! $this->usuarioAutorizadoModulo()) {
                return response()->json(['error' => 'No autorizado para Material de Apoyo.'], 403);
            }
            if (! $this->tablaLista()) {
                return response()->json(['error' => $this->mensajeMigracion()], 503);
            }

            $ids = $this->rapAccess->idsCompetenciaAccesibles();
            $puedeAdministrar = $this->rapAccess->usuarioPuedeAdministrar();
            if ($ids === []) {
                return response()->json([
                    'data' => [],
                    'puedeAdministrar' => $puedeAdministrar,
                ]);
            }

            $rows = MaterialCompetencia::query()
                ->whereIn('idCompetencia', $ids)
                ->where('activo', true)
                ->with($this->relacionesMaterial())
                ->orderBy('idCompetencia')
                ->orderByDesc('id')
                ->get()
                ->unique('id')
                ->values();

            return response()->json([
                'data' => $rows->map(fn (MaterialCompetencia $m) => $this->toResource($m, false))->values(),
                'puedeAdministrar' => $puedeAdministrar,
            ]);
        } catch (QueryException $e) {
            if ($this->esTablaAusente($e)) {
                return response()->json(['error' => $this->mensajeMigracion()], 503);
            }

            return response()->json(['error' => $e->getMessage()], 500);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function store(Request $request, int $idCompetencia): JsonResponse
    {
        try {
            $gate = $this->asegurarCompetenciaAdministrable($idCompetencia);
            if ($gate instanceof JsonResponse) {
                return $gate;
            }
            if (! $this->tablaLista()) {
                return response()->json(['error' => $this->mensajeMigracion()], 503);
            }

            $validator = $this->validadorRecursos($request, true);
            $validator->validate();

            $pendientes = [];
            try {
                $pendientes = $this->almacenarArchivosNuevos($request);
                $enlaces = $this->enlacesValidados($request);
                if ($pendientes === [] && $enlaces === []) {
                    return response()->json(['errors' => ['Agregue al menos un documento, archivo multimedia o enlace.']], 422);
                }

                $payloadCreate = [
                    'idCompetencia' => $idCompetencia,
                    'idPersona' => $this->idPersonaActual(),
                    'idActividad' => null,
                    'idMaterialApoyoActividad' => null,
                    'origen' => MaterialCompetencia::ORIGEN_DIRECTO,
                    'titulo' => $request->titulo,
                    'descripcion' => $request->descripcion ?? null,
                    'tipoMaterial' => $request->filled('tipoMaterial') ? (string) $request->tipoMaterial : null,
                    'urlDocumento' => $this->primeraRutaTipo($pendientes, MaterialCompetenciaRecurso::TIPO_DOCUMENTO),
                    'urlAdicional' => $enlaces[0]['ruta'] ?? null,
                    'activo' => true,
                ];
                if ($this->tieneColumnaUrlVideo()) {
                    $payloadCreate['urlVideo'] = $this->primeraRutaTipo($pendientes, MaterialCompetenciaRecurso::TIPO_MULTIMEDIA);
                }

                $material = DB::transaction(function () use ($payloadCreate, $pendientes, $enlaces) {
                    $m = MaterialCompetencia::create($payloadCreate);
                    $this->persistirRecursos($m, $pendientes, $enlaces);

                    return $m;
                });
            } catch (\Throwable $e) {
                foreach ($pendientes as $item) {
                    $this->deleteStoredPublicFileIfLocal($item['ruta'] ?? null);
                }
                throw $e;
            }

            $material->load($this->relacionesMaterial());

            return response()->json($this->toResource($material, true), 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (QueryException $e) {
            if ($this->esTablaAusente($e)) {
                return response()->json(['error' => $this->mensajeMigracion()], 503);
            }

            return response()->json(['error' => $e->getMessage()], 500);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function elegibles(Request $request, int $idCompetencia): JsonResponse
    {
        try {
            $gate = $this->asegurarCompetenciaAdministrable($idCompetencia);
            if ($gate instanceof JsonResponse) {
                return $gate;
            }

            $q = trim((string) $request->query('q', ''));

            return response()->json($this->materialesElegibles($idCompetencia, $q));
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function incorporar(Request $request, int $idCompetencia): JsonResponse
    {
        try {
            $gate = $this->asegurarCompetenciaAdministrable($idCompetencia);
            if ($gate instanceof JsonResponse) {
                return $gate;
            }
            if (! $this->tablaLista()) {
                return response()->json(['error' => $this->mensajeMigracion()], 503);
            }

            $validated = Validator::make($request->all(), [
                'origen' => 'required|string|in:'.MaterialCompetencia::ORIGEN_ACTIVIDAD.','.MaterialCompetencia::ORIGEN_MATERIAL_APOYO,
                'idActividad' => 'nullable|integer',
                'idMaterialApoyoActividad' => 'nullable|integer',
            ])->validate();

            $origen = (string) $validated['origen'];
            $idActividad = isset($validated['idActividad']) ? (int) $validated['idActividad'] : 0;
            $idApoyo = isset($validated['idMaterialApoyoActividad']) ? (int) $validated['idMaterialApoyoActividad'] : 0;

            $payload = $this->payloadIncorporacion($idCompetencia, $origen, $idActividad, $idApoyo);
            if ($payload instanceof JsonResponse) {
                return $payload;
            }

            if ($this->yaAsociado($idCompetencia, $origen, $payload['idActividad'], $payload['idMaterialApoyoActividad'])) {
                return response()->json(['error' => 'Este material ya está asociado a la competencia.'], 422);
            }

            try {
                $material = DB::transaction(function () use ($payload) {
                    return MaterialCompetencia::create($payload);
                });
            } catch (QueryException $e) {
                if ($this->esViolacionUnica($e)) {
                    return response()->json(['error' => 'Este material ya está asociado a la competencia.'], 422);
                }
                throw $e;
            }

            $material->load($this->relacionesMaterial());

            return response()->json($this->toResource($material, true), 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (QueryException $e) {
            if ($this->esTablaAusente($e)) {
                return response()->json(['error' => $this->mensajeMigracion()], 503);
            }

            return response()->json(['error' => $e->getMessage()], 500);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function show(int $idCompetencia, int $id): JsonResponse
    {
        try {
            $gate = $this->asegurarCompetencia($idCompetencia);
            if ($gate instanceof JsonResponse) {
                return $gate;
            }
            $material = $this->buscarMaterial($idCompetencia, $id);
            if (! $material) {
                return response()->json(['error' => 'Material no encontrado.'], 404);
            }

            return response()->json($this->toResource($material, $this->rapAccess->usuarioPuedeAdministrar()));
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function descargar(Request $request, int $idCompetencia, int $id)
    {
        try {
            $gate = $this->asegurarCompetencia($idCompetencia);
            if ($gate instanceof JsonResponse) {
                return $gate;
            }
            $material = $this->buscarMaterial($idCompetencia, $id);
            if (! $material) {
                return response()->json(['error' => 'Material no encontrado.'], 404);
            }

            $recurso = $this->resolverRecurso($material);
            $path = $recurso['path'];
            $lista = $this->recursosPublicos($material);
            $primeroDoc = collect($lista)->firstWhere('tipo', MaterialCompetenciaRecurso::TIPO_DOCUMENTO);
            if ($primeroDoc && ! empty($primeroDoc['ruta'])) {
                $path = $primeroDoc['ruta'];
            }
            if (! $path) {
                if (! empty($recurso['urlExterna'])) {
                    return redirect()->away($recurso['urlExterna']);
                }

                return response()->json(['error' => 'Este material no tiene archivo para descargar.'], 404);
            }
            if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
                return redirect()->away($path);
            }
            if (! Storage::disk('public')->exists($path)) {
                return response()->json(['error' => 'Archivo no encontrado.'], 404);
            }

            $fileName = basename($path);
            $absolutePath = Storage::disk('public')->path($path);
            $mime = Storage::disk('public')->mimeType($path) ?: 'application/octet-stream';
            $inline = $request->boolean('inline');
            $disposition = $inline ? 'inline' : 'attachment';

            return response()->download($absolutePath, $fileName, [
                'Content-Type' => $mime,
                'Content-Disposition' => $disposition.'; filename="'.$fileName.'"',
                'Access-Control-Expose-Headers' => 'Content-Disposition',
            ]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function destroy(int $idCompetencia, int $id): JsonResponse
    {
        try {
            $gate = $this->asegurarCompetenciaAdministrable($idCompetencia);
            if ($gate instanceof JsonResponse) {
                return $gate;
            }
            $material = $this->buscarMaterial($idCompetencia, $id);
            if (! $material) {
                return response()->json(['error' => 'Material no encontrado.'], 404);
            }

            DB::transaction(function () use ($material) {
                if ($this->tablaRecursosLista()) {
                    foreach ($material->recursos as $recurso) {
                        if ($recurso->esArchivoLocal()) {
                            $this->deleteStoredPublicFileIfLocal($recurso->ruta);
                        }
                    }
                    $material->recursos()->delete();
                }
                if ($material->esDirecto()) {
                    $this->deleteStoredPublicFileIfLocal($material->urlDocumento);
                    if ($this->tieneColumnaUrlVideo()) {
                        $this->deleteStoredPublicFileIfLocal($material->urlVideo);
                    }
                }
                $material->delete();
            });

            return response()->json(['message' => 'Material desvinculado de la competencia.']);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function mover(Request $request, int $idCompetencia, int $id): JsonResponse
    {
        try {
            $gateOrigen = $this->asegurarCompetenciaAdministrable($idCompetencia);
            if ($gateOrigen instanceof JsonResponse) {
                return $gateOrigen;
            }

            $idDestino = (int) Validator::make($request->all(), [
                'idCompetenciaDestino' => 'required|integer|min:1',
            ])->validate()['idCompetenciaDestino'];

            if ($idDestino === $idCompetencia) {
                return response()->json(['error' => 'Seleccione una competencia distinta a la actual.'], 422);
            }

            $gateDestino = $this->asegurarCompetenciaAdministrable($idDestino);
            if ($gateDestino instanceof JsonResponse) {
                return $gateDestino;
            }

            $material = $this->buscarMaterial($idCompetencia, $id);
            if (! $material) {
                return response()->json(['error' => 'Material no encontrado.'], 404);
            }

            $bloqueo = MaterialCompetenciaReglas::motivoBloqueoTraslado((string) $material->origen);
            if ($bloqueo) {
                return response()->json(['error' => $bloqueo], 422);
            }

            if ($this->yaAsociado(
                $idDestino,
                (string) $material->origen,
                $material->idActividad ? (int) $material->idActividad : null,
                $material->idMaterialApoyoActividad ? (int) $material->idMaterialApoyoActividad : null
            )) {
                return response()->json(['error' => 'El material ya está asociado a la competencia de destino.'], 422);
            }

            try {
                DB::transaction(function () use ($material, $idDestino) {
                    $material->idCompetencia = $idDestino;
                    $material->save();
                });
            } catch (QueryException $e) {
                if ($this->esViolacionUnica($e)) {
                    return response()->json(['error' => 'El material ya está asociado a la competencia de destino.'], 422);
                }
                throw $e;
            }

            $material->load($this->relacionesMaterial());

            return response()->json([
                'message' => 'Material movido correctamente a la competencia seleccionada.',
                'data' => $this->toResource($material, true),
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function update(Request $request, int $idCompetencia, int $id): JsonResponse
    {
        try {
            $gate = $this->asegurarCompetenciaAdministrable($idCompetencia);
            if ($gate instanceof JsonResponse) {
                return $gate;
            }
            $material = $this->buscarMaterial($idCompetencia, $id);
            if (! $material) {
                return response()->json(['error' => 'Material no encontrado.'], 404);
            }
            if (! $material->esDirecto()) {
                return response()->json(['error' => 'Solo se pueden editar los materiales de subida directa.'], 422);
            }

            $validator = $this->validadorRecursos($request, false);
            $validator->validate();

            $pendientes = [];
            try {
                $pendientes = $this->almacenarArchivosNuevos($request);
                $enlaces = $this->enlacesValidados($request);

                DB::transaction(function () use ($request, $material, $pendientes, $enlaces) {
                    $material->titulo = $request->titulo;
                    $material->descripcion = $request->descripcion ?? $material->descripcion;
                    if ($request->exists('tipoMaterial')) {
                        $material->tipoMaterial = $request->filled('tipoMaterial') ? (string) $request->tipoMaterial : null;
                    }
                    $material->save();
                    $this->actualizarEnlacesExistentes($material, $request);
                    $this->persistirRecursos($material, $pendientes, $enlaces);
                    $this->sincronizarCamposLegacy($material);
                });
            } catch (\Throwable $e) {
                foreach ($pendientes as $item) {
                    $this->deleteStoredPublicFileIfLocal($item['ruta'] ?? null);
                }
                throw $e;
            }

            $material->load($this->relacionesMaterial());

            return response()->json($this->toResource($material, true));
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function destroyRecurso(int $idCompetencia, int $id, int $idRecurso): JsonResponse
    {
        try {
            $gate = $this->asegurarCompetenciaAdministrable($idCompetencia);
            if ($gate instanceof JsonResponse) {
                return $gate;
            }
            $material = $this->buscarMaterial($idCompetencia, $id);
            if (! $material) {
                return response()->json(['error' => 'Material no encontrado.'], 404);
            }
            if (! $this->tablaRecursosLista()) {
                return response()->json(['error' => 'La tabla de recursos no está disponible.'], 503);
            }
            $recurso = $material->recursos->firstWhere('id', $idRecurso);
            if (! $recurso) {
                return response()->json(['error' => 'El recurso no existe en este material.'], 404);
            }

            DB::transaction(function () use ($material, $recurso) {
                if ($recurso->esArchivoLocal()) {
                    $this->deleteStoredPublicFileIfLocal($recurso->ruta);
                }
                $recurso->delete();
                $this->sincronizarCamposLegacy($material);
            });

            $material->load($this->relacionesMaterial());

            return response()->json($this->toResource($material, true));
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function descargarRecurso(Request $request, int $idCompetencia, int $id, int $idRecurso)
    {
        try {
            $gate = $this->asegurarCompetencia($idCompetencia);
            if ($gate instanceof JsonResponse) {
                return $gate;
            }
            $material = $this->buscarMaterial($idCompetencia, $id);
            if (! $material) {
                return response()->json(['error' => 'Material no encontrado.'], 404);
            }
            $recurso = $this->recursoDeMaterial($material, $idRecurso);
            if (! $recurso) {
                return response()->json(['error' => 'Recurso no encontrado.'], 404);
            }
            $path = $recurso['ruta'] ?? null;
            if (! $path) {
                return response()->json(['error' => 'Este recurso no tiene archivo para descargar.'], 404);
            }
            if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
                return redirect()->away($path);
            }
            if (! Storage::disk('public')->exists($path)) {
                return response()->json(['error' => 'Archivo no encontrado.'], 404);
            }

            $fileName = basename($path);
            $absolutePath = Storage::disk('public')->path($path);
            $mime = Storage::disk('public')->mimeType($path) ?: 'application/octet-stream';
            $inline = $request->boolean('inline');
            $disposition = $inline ? 'inline' : 'attachment';

            return response()->download($absolutePath, $fileName, [
                'Content-Type' => $mime,
                'Content-Disposition' => $disposition.'; filename="'.$fileName.'"',
                'Access-Control-Expose-Headers' => 'Content-Disposition',
            ]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    private function usuarioAutorizadoModulo(): bool
    {
        return $this->rapAccess->usuarioPuedeAdministrar() || $this->rapAccess->usuarioEsAprendiz();
    }

    private function asegurarCompetencia(int $idCompetencia): ?JsonResponse
    {
        if (! $this->usuarioAutorizadoModulo()) {
            return response()->json(['error' => 'No autorizado para Material de Apoyo.'], 403);
        }
        $competencia = Materia::query()->find($idCompetencia);
        if (! $competencia || ! $competencia->esCompetencia()) {
            return response()->json(['error' => 'La competencia solicitada no existe.'], 404);
        }
        if (! $this->rapAccess->usuarioPuedeAccederCompetencia($idCompetencia)) {
            return response()->json(['error' => 'No autorizado para consultar esta competencia.'], 403);
        }

        return null;
    }

    private function asegurarCompetenciaAdministrable(int $idCompetencia): ?JsonResponse
    {
        $gate = $this->asegurarCompetencia($idCompetencia);
        if ($gate instanceof JsonResponse) {
            return $gate;
        }
        if (! $this->rapAccess->usuarioPuedeAdministrar()) {
            return response()->json(['error' => 'El aprendiz no puede administrar el material de la competencia.'], 403);
        }

        return null;
    }

    private function tablaLista(): bool
    {
        $table = (new MaterialCompetencia())->getTable();
        if (Schema::hasTable($table)) {
            return true;
        }

        try {
            $db = Schema::getConnection()->getDatabaseName();
            $row = DB::selectOne(
                'SELECT 1 AS ok FROM information_schema.tables
                 WHERE table_schema = ? AND LOWER(table_name) = LOWER(?) AND table_type = ? LIMIT 1',
                [$db, $table, 'BASE TABLE']
            );

            return (bool) $row;
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function mensajeMigracion(): string
    {
        return 'Ejecute la migración de materialCompetencia para habilitar el material por competencia.';
    }

    private function esTablaAusente(QueryException $e): bool
    {
        $msg = $e->getMessage();

        return str_contains($msg, "Base table or view not found")
            || str_contains($msg, '1146')
            || str_contains($msg, '42S02');
    }

    /**
     * @return array<int, string|array<string, string>>
     */
    private function relacionesMaterial(): array
    {
        $rels = [
            'persona:id,nombre1,nombre2,apellido1,apellido2,email,rutaFoto',
            'actividad:id,tituloActividad,idPersona,idMateria,pathDocumentoActividad',
            'actividad.persona:id,nombre1,nombre2,apellido1,apellido2,email,rutaFoto',
            'actividad.materia:id,nombreMateria,idMateriaPadre,codigo',
            'materialApoyoActividad',
            'competencia:id,nombreMateria,idMateriaPadre,codigo',
        ];
        if ($this->tablaRecursosLista()) {
            $rels[] = 'recursos';
        }

        return $rels;
    }

    private function buscarMaterial(int $idCompetencia, int $id): ?MaterialCompetencia
    {
        if (! $this->tablaLista()) {
            return null;
        }

        return MaterialCompetencia::query()
            ->where('idCompetencia', $idCompetencia)
            ->whereKey($id)
            ->where('activo', true)
            ->with($this->relacionesMaterial())
            ->first();
    }

    /**
     * @return array<string, mixed>|\Illuminate\Http\JsonResponse
     */
    private function payloadIncorporacion(int $idCompetencia, string $origen, int $idActividad, int $idApoyo)
    {
        if ($origen === MaterialCompetencia::ORIGEN_MATERIAL_APOYO) {
            if ($idApoyo <= 0) {
                return response()->json(['error' => 'Debe indicar el material de apoyo a incorporar.'], 422);
            }
            $apoyo = MaterialApoyoActividad::query()->find($idApoyo);
            if (! $apoyo) {
                return response()->json(['error' => 'El material de apoyo no existe.'], 404);
            }
            $asig = AsignacionMaterialApoyoActividad::query()
                ->where('idMaterialApoyo', $idApoyo)
                ->when($idActividad > 0, fn ($q) => $q->where('idActividad', $idActividad))
                ->first();
            if (! $asig) {
                return response()->json(['error' => 'El material no pertenece a una actividad de la competencia.'], 422);
            }
            $actividad = Actividad::query()->with('materia:id,idMateriaPadre')->find((int) $asig->idActividad);
            if (! $actividad) {
                return response()->json(['error' => 'La actividad de origen no existe.'], 404);
            }
            $idPadreRap = $actividad->materia?->idMateriaPadre ? (int) $actividad->materia->idMateriaPadre : null;
            if (! MaterialCompetenciaReglas::materialElegibleParaCompetencia($idPadreRap, $idCompetencia)) {
                return response()->json(['error' => 'El material no es elegible para esta competencia.'], 422);
            }
            if (! $apoyo->urlDocumento && ! $apoyo->urlAdicional) {
                return response()->json(['error' => 'El material de apoyo no tiene documento ni enlace.'], 422);
            }

            return [
                'idCompetencia' => $idCompetencia,
                'idPersona' => $this->idPersonaActual(),
                'idActividad' => (int) $actividad->id,
                'idMaterialApoyoActividad' => (int) $apoyo->id,
                'origen' => MaterialCompetencia::ORIGEN_MATERIAL_APOYO,
                'titulo' => $apoyo->titulo ?: ($actividad->tituloActividad ?: 'Material de apoyo'),
                'descripcion' => $apoyo->descripcion,
                'tipoMaterial' => null,
                'urlDocumento' => null,
                'urlAdicional' => null,
                'activo' => true,
            ];
        }

        if ($idActividad <= 0) {
            return response()->json(['error' => 'Debe indicar la actividad de origen.'], 422);
        }
        $actividad = Actividad::query()->with('materia:id,idMateriaPadre')->find($idActividad);
        if (! $actividad) {
            return response()->json(['error' => 'La actividad de origen no existe.'], 404);
        }
        $idPadreRap = $actividad->materia?->idMateriaPadre ? (int) $actividad->materia->idMateriaPadre : null;
        if (! MaterialCompetenciaReglas::materialElegibleParaCompetencia($idPadreRap, $idCompetencia)) {
            return response()->json(['error' => 'El documento no es elegible para esta competencia.'], 422);
        }
        if (! $actividad->pathDocumentoActividad) {
            return response()->json(['error' => 'La actividad no tiene documento para incorporar.'], 422);
        }

        return [
            'idCompetencia' => $idCompetencia,
            'idPersona' => $this->idPersonaActual(),
            'idActividad' => (int) $actividad->id,
            'idMaterialApoyoActividad' => null,
            'origen' => MaterialCompetencia::ORIGEN_ACTIVIDAD,
            'titulo' => $actividad->tituloActividad ?: 'Documento de actividad',
            'descripcion' => $actividad->descripcionActividad ?? null,
            'tipoMaterial' => null,
            'urlDocumento' => null,
            'urlAdicional' => null,
            'activo' => true,
        ];
    }

    private function yaAsociado(int $idCompetencia, string $origen, ?int $idActividad, ?int $idApoyo): bool
    {
        $clave = MaterialCompetenciaReglas::claveIncorporacion($origen, $idActividad, $idApoyo);
        $query = MaterialCompetencia::query()->where('idCompetencia', $idCompetencia)->where('activo', true);
        if ($clave && Schema::hasColumn((new MaterialCompetencia())->getTable(), 'claveOrigen')) {
            return $query->where('claveOrigen', $clave)->exists();
        }
        $query->where('origen', $origen);
        if ($origen === MaterialCompetencia::ORIGEN_MATERIAL_APOYO) {
            return $query->where('idMaterialApoyoActividad', (int) $idApoyo)->exists();
        }
        if ($origen === MaterialCompetencia::ORIGEN_ACTIVIDAD) {
            return $query->where('idActividad', (int) $idActividad)->whereNull('idMaterialApoyoActividad')->exists();
        }

        return false;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function materialesElegibles(int $idCompetencia, string $q): array
    {
        $idsRap = $this->rapAccess->idsRapDeCompetencia($idCompetencia);
        if ($idsRap === []) {
            return [];
        }

        $actividadesQuery = Actividad::query()->whereIn('idMateria', $idsRap);
        $idCompany = $this->idCompanyOpcional();
        if ($idCompany !== null && Schema::hasColumn('actividades', 'idCompany')) {
            $actividadesQuery->where('idCompany', $idCompany);
        }

        $actividades = $actividadesQuery
            ->with([
                'persona:id,nombre1,nombre2,apellido1,apellido2,email',
                'materia:id,nombreMateria,idMateriaPadre,codigo',
                'materialesApoyo',
            ])
            ->orderByDesc('id')
            ->get();

        $asociadosApoyo = MaterialCompetencia::query()
            ->where('idCompetencia', $idCompetencia)
            ->where('activo', true)
            ->where('origen', MaterialCompetencia::ORIGEN_MATERIAL_APOYO)
            ->whereNotNull('idMaterialApoyoActividad')
            ->pluck('idMaterialApoyoActividad')
            ->map(fn ($id) => (int) $id)
            ->all();
        $asociadosActividad = MaterialCompetencia::query()
            ->where('idCompetencia', $idCompetencia)
            ->where('activo', true)
            ->where('origen', MaterialCompetencia::ORIGEN_ACTIVIDAD)
            ->pluck('idActividad')
            ->map(fn ($id) => (int) $id)
            ->all();

        $out = [];
        foreach ($actividades as $actividad) {
            $instructor = $this->personaResumen($actividad->persona);
            $rapNombre = $actividad->materia?->nombreMateria;
            if ($actividad->pathDocumentoActividad && ! in_array((int) $actividad->id, $asociadosActividad, true)) {
                $out[] = $this->candidatoElegible([
                    'origen' => MaterialCompetencia::ORIGEN_ACTIVIDAD,
                    'idActividad' => (int) $actividad->id,
                    'idMaterialApoyoActividad' => null,
                    'titulo' => $actividad->tituloActividad ?: 'Documento de actividad',
                    'descripcion' => $actividad->descripcionActividad ?? null,
                    'tipoMaterial' => 'DOCUMENTO_ACTIVIDAD',
                    'actividadTitulo' => $actividad->tituloActividad,
                    'instructor' => $instructor,
                    'rapNombre' => $rapNombre,
                    'created_at' => null,
                    'urlDocumento' => $actividad->pathDocumentoActividad,
                    'urlAdicional' => null,
                ], $q);
            }
            foreach ($actividad->materialesApoyo as $apoyo) {
                if (in_array((int) $apoyo->id, $asociadosApoyo, true)) {
                    continue;
                }
                if (! $apoyo->urlDocumento && ! $apoyo->urlAdicional) {
                    continue;
                }
                $out[] = $this->candidatoElegible([
                    'origen' => MaterialCompetencia::ORIGEN_MATERIAL_APOYO,
                    'idActividad' => (int) $actividad->id,
                    'idMaterialApoyoActividad' => (int) $apoyo->id,
                    'titulo' => $apoyo->titulo ?: ($actividad->tituloActividad ?: 'Material de apoyo'),
                    'descripcion' => $apoyo->descripcion,
                    'tipoMaterial' => 'MATERIAL_APOYO',
                    'actividadTitulo' => $actividad->tituloActividad,
                    'instructor' => $instructor,
                    'rapNombre' => $rapNombre,
                    'created_at' => $apoyo->created_at,
                    'urlDocumento' => $apoyo->urlDocumento,
                    'urlAdicional' => $apoyo->urlAdicional,
                ], $q);
            }
        }

        return array_values(array_filter($out));
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>|null
     */
    private function candidatoElegible(array $item, string $q): ?array
    {
        $item['urlDocumentoUrl'] = $this->publicUrl($item['urlDocumento'] ?? null);
        if ($q === '') {
            return $item;
        }
        $haystack = mb_strtolower(implode(' ', array_filter([
            $item['titulo'] ?? '',
            $item['descripcion'] ?? '',
            $item['actividadTitulo'] ?? '',
            $item['rapNombre'] ?? '',
            $item['instructor']['nombreCompleto'] ?? '',
            $item['tipoMaterial'] ?? '',
            $item['origen'] ?? '',
        ])));
        if (! str_contains($haystack, mb_strtolower($q))) {
            return null;
        }

        return $item;
    }

    private function toResource(MaterialCompetencia $m, bool $puedeAdministrar): array
    {
        $recurso = $this->resolverRecurso($m);
        $competencia = $m->competencia;
        $actividad = $m->actividad;
        $rapOrigen = $actividad?->materia;
        $creador = $this->personaResumen($m->persona);
        if (! $creador && $actividad) {
            $creador = $this->personaResumen($actividad->persona);
        }

        return [
            'id' => (int) $m->id,
            'idCompetencia' => (int) $m->idCompetencia,
            'origen' => $m->origen,
            'origenLabel' => $this->origenLabel($m->origen),
            'titulo' => $m->titulo,
            'descripcion' => $m->descripcion,
            'tipoMaterial' => $m->tipoMaterial,
            'urlDocumento' => $recurso['path'],
            'urlDocumentoUrl' => $this->publicUrl($recurso['path']),
            'urlAdicional' => $recurso['urlAdicional'],
            'urlVideo' => $recurso['urlVideo'],
            'urlVideoUrl' => $this->publicUrl($recurso['urlVideo']),
            'recursos' => $this->recursosPublicos($m),
            'idActividad' => $m->idActividad ? (int) $m->idActividad : null,
            'idMaterialApoyoActividad' => $m->idMaterialApoyoActividad ? (int) $m->idMaterialApoyoActividad : null,
            'actividadOrigen' => $actividad ? [
                'id' => (int) $actividad->id,
                'titulo' => $actividad->tituloActividad,
            ] : null,
            'rapOrigen' => $rapOrigen ? [
                'id' => (int) $rapOrigen->id,
                'nombre' => $rapOrigen->nombreMateria,
                'codigo' => $rapOrigen->codigo,
            ] : null,
            'idPersona' => $m->idPersona ? (int) $m->idPersona : null,
            'creador' => $creador,
            'competencia' => $competencia ? [
                'id' => (int) $competencia->id,
                'nombre' => $competencia->nombreMateria,
                'codigo' => $competencia->codigo,
            ] : null,
            'competenciaNombre' => $competencia?->nombreMateria,
            'puedeDesvincular' => $puedeAdministrar,
            'created_at' => $m->created_at,
            'updated_at' => $m->updated_at,
        ];
    }

    /**
     * @return array{path: ?string, urlAdicional: ?string, urlExterna: ?string, urlVideo: ?string}
     */
    private function resolverRecurso(MaterialCompetencia $m): array
    {
        $path = $m->urlDocumento;
        $urlAdicional = $m->urlAdicional;
        $urlVideo = $this->tieneColumnaUrlVideo() ? $m->urlVideo : null;
        if ($m->origen === MaterialCompetencia::ORIGEN_MATERIAL_APOYO && $m->materialApoyoActividad) {
            $path = $m->materialApoyoActividad->urlDocumento ?: $path;
            $urlAdicional = $m->materialApoyoActividad->urlAdicional ?: $urlAdicional;
        }
        if ($m->origen === MaterialCompetencia::ORIGEN_ACTIVIDAD && $m->actividad) {
            $path = $m->actividad->pathDocumentoActividad ?: $path;
        }

        return [
            'path' => $path ?: null,
            'urlAdicional' => $urlAdicional ?: null,
            'urlExterna' => $urlAdicional ?: null,
            'urlVideo' => $urlVideo ?: null,
        ];
    }

    private function tieneColumnaUrlVideo(): bool
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }

        $table = (new MaterialCompetencia())->getTable();
        $cache = Schema::hasTable($table) && Schema::hasColumn($table, 'urlVideo');

        return $cache;
    }

    private function origenLabel(?string $origen): string
    {
        return match ($origen) {
            MaterialCompetencia::ORIGEN_DIRECTO => 'Subida directa',
            MaterialCompetencia::ORIGEN_ACTIVIDAD => 'Actividad',
            MaterialCompetencia::ORIGEN_MATERIAL_APOYO => 'Material de apoyo',
            default => (string) $origen,
        };
    }

    private function personaResumen(?Person $p): ?array
    {
        if (! $p) {
            return null;
        }

        return [
            'idPersona' => (int) $p->id,
            'nombreCompleto' => trim(implode(' ', array_filter([$p->nombre1, $p->nombre2, $p->apellido1, $p->apellido2]))),
            'email' => $p->email,
            'rutaFoto' => $p->rutaFoto ?? null,
            'rutaFotoUrl' => $p->rutaFotoUrl ?? null,
        ];
    }

    private function idPersonaActual(): ?int
    {
        try {
            $user = KeyUtil::user();

            return $user?->idpersona ? (int) $user->idpersona : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function idCompanyOpcional(): ?int
    {
        try {
            $id = (int) KeyUtil::idCompany();

            return $id > 0 ? $id : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function storeFileInPublicDir(\Illuminate\Http\UploadedFile $file, string $dir): string
    {
        if (! Storage::disk('public')->exists($dir)) {
            Storage::disk('public')->makeDirectory($dir, 0755, true);
        }
        $filename = time().'_'.str_replace('.', '', uniqid('', true)).'_'.preg_replace('/[^a-zA-Z0-9._-]/', '_', $file->getClientOriginalName());

        return $file->storeAs($dir, $filename, 'public');
    }

    private function deleteStoredPublicFileIfLocal(?string $path): void
    {
        if (! $path) {
            return;
        }
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return;
        }
        if (str_starts_with($path, 'material-competencia/') || str_starts_with($path, 'material-rap/')) {
            if (Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);
            }
        }
    }

    private function publicUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return Storage::disk('public')->url($path);
    }

    private function tablaRecursosLista(): bool
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        $cache = Schema::hasTable('materialCompetenciaRecurso');

        return $cache;
    }

    /**
     * @return list<string>
     */
    private function extensionesDocumentoCompetencia(): array
    {
        return array_values(array_unique(array_merge($this->materialDocumentoAllowedExtensions(), ['csv'])));
    }

    /**
     * @return list<string>
     */
    private function extensionesMultimedia(): array
    {
        return ['mp4', 'webm', 'mov', 'avi', 'mp3'];
    }

    /**
     * @return list<string>
     */
    private function extensionesAudio(): array
    {
        return ['mp3'];
    }

    private function validadorRecursos(Request $request, bool $tituloRequerido)
    {
        $validator = Validator::make($request->all(), array_merge([
            'titulo' => ($tituloRequerido ? 'required' : 'sometimes|required').'|string|max:255',
            'descripcion' => 'nullable|string|max:3000',
            'tipoMaterial' => 'nullable|string|in:'.implode(',', MaterialCompetencia::TIPOS_MATERIAL),
            'urlAdicional' => 'nullable|string|max:1000',
            'urlVideo' => 'nullable|string|max:1000',
            'documento' => 'nullable|file|max:51200',
            'video' => 'nullable|file|max:51200',
            'documentos' => 'nullable|array|max:20',
            'documentos.*' => 'file|max:51200',
            'multimedia' => 'nullable|array|max:20',
            'multimedia.*' => 'file|max:51200',
            'enlaces' => 'nullable|array|max:30',
            'enlaces.*.url' => 'nullable|string|max:1000',
            'enlaces.*.titulo' => 'nullable|string|max:255',
            'enlacesActualizar' => 'nullable|array|max:30',
            'enlacesActualizar.*.id' => 'required|integer',
            'enlacesActualizar.*.url' => 'nullable|string|max:1000',
            'enlacesActualizar.*.titulo' => 'nullable|string|max:255',
        ], $this->materialDocumentoFileRules(true)), array_merge($this->materialDocumentoValidationMessages(), [
            'video.max' => 'El archivo no puede superar los 50 MB.',
            'documentos.*.max' => 'El archivo no puede superar los 50 MB.',
            'multimedia.*.max' => 'El archivo no puede superar los 50 MB.',
            'documento.uploaded' => 'El servidor no pudo recibir el archivo.',
            'video.uploaded' => 'El servidor no pudo recibir el archivo multimedia.',
            'documentos.*.uploaded' => 'El servidor no pudo recibir el archivo.',
            'multimedia.*.uploaded' => 'El servidor no pudo recibir el archivo multimedia.',
            'documentos.*.mimes' => 'El formato del archivo no está permitido.',
            'multimedia.*.mimes' => 'El formato del archivo multimedia no está permitido.',
        ], $this->mensajesErrorCargaRequest($request)));

        $validator->after(function ($v) use ($request) {
            foreach ($this->archivosDocumentoRequest($request) as $i => $file) {
                $this->assertDocumentoCompetenciaFile($v, $file, $i === 'legacy' ? 'documento' : 'documentos.'.$i);
            }
            foreach ($this->archivosMultimediaRequest($request) as $i => $file) {
                $this->assertMultimediaFile($v, $file, $i === 'legacy' ? 'video' : 'multimedia.'.$i);
            }
            foreach ($this->enlacesRequest($request) as $i => $enlace) {
                $url = trim((string) ($enlace['url'] ?? ''));
                if ($url === '') {
                    continue;
                }
                $clasif = MaterialCompetenciaEnlace::clasificar($url);
                if (! ($clasif['ok'] ?? false)) {
                    $v->errors()->add('enlaces.'.$i.'.url', $clasif['mensaje'] ?? 'La URL no es válida.');
                }
            }
            foreach ($request->input('enlacesActualizar', []) as $i => $enlace) {
                if (! is_array($enlace)) {
                    continue;
                }
                $url = trim((string) ($enlace['url'] ?? ''));
                if ($url === '') {
                    continue;
                }
                $clasif = MaterialCompetenciaEnlace::clasificar($url);
                if (! ($clasif['ok'] ?? false)) {
                    $v->errors()->add('enlacesActualizar.'.$i.'.url', $clasif['mensaje'] ?? 'La URL no es válida.');
                }
            }
            if ($request->filled('urlVideo')) {
                $clasif = MaterialCompetenciaEnlace::clasificar((string) $request->urlVideo);
                if (! ($clasif['ok'] ?? false) && ! $this->pareceRutaLocal((string) $request->urlVideo)) {
                    $v->errors()->add('urlVideo', $clasif['mensaje'] ?? 'La URL de video no es válida.');
                }
            }
        });

        return $validator;
    }

    /**
     * @return array<int|string, \Illuminate\Http\UploadedFile>
     */
    private function archivosDocumentoRequest(Request $request): array
    {
        $out = [];
        if ($request->hasFile('documento')) {
            $out['legacy'] = $request->file('documento');
        }
        $files = $request->file('documentos', []);
        if (! is_array($files)) {
            $files = $files ? [$files] : [];
        }
        foreach ($files as $i => $file) {
            if ($file) {
                $out[(int) $i] = $file;
            }
        }

        return $out;
    }

    /**
     * @return array<int|string, \Illuminate\Http\UploadedFile>
     */
    private function archivosMultimediaRequest(Request $request): array
    {
        $out = [];
        if ($request->hasFile('video')) {
            $out['legacy'] = $request->file('video');
        }
        $files = $request->file('multimedia', []);
        if (! is_array($files)) {
            $files = $files ? [$files] : [];
        }
        foreach ($files as $i => $file) {
            if ($file) {
                $out[(int) $i] = $file;
            }
        }

        return $out;
    }

    /**
     * @return list<array{url?: string, titulo?: string}>
     */
    private function enlacesRequest(Request $request): array
    {
        $raw = $request->input('enlaces', []);
        if (! is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $item) {
            if (is_string($item)) {
                $out[] = ['url' => $item, 'titulo' => ''];
            } elseif (is_array($item)) {
                $out[] = [
                    'url' => (string) ($item['url'] ?? ''),
                    'titulo' => (string) ($item['titulo'] ?? ''),
                ];
            }
        }
        if ($request->filled('urlAdicional')) {
            $out[] = ['url' => (string) $request->urlAdicional, 'titulo' => ''];
        }
        if ($request->filled('urlVideo') && MaterialCompetenciaEnlace::youtubeId((string) $request->urlVideo)) {
            $out[] = ['url' => (string) $request->urlVideo, 'titulo' => ''];
        }

        return $out;
    }

    private function assertDocumentoCompetenciaFile($validator, ?\Illuminate\Http\UploadedFile $file, string $attribute): void
    {
        if (! $file || ! $file->isValid()) {
            return;
        }
        $ext = strtolower((string) $file->getClientOriginalExtension());
        if ($ext === 'csv') {
            $mime = strtolower((string) ($file->getMimeType() ?? ''));
            $allowed = [
                'text/csv',
                'application/csv',
                'application/vnd.ms-excel',
                'application/octet-stream',
            ];
            if ($mime !== '' && ! in_array($mime, $allowed, true)) {
                $validator->errors()->add($attribute, 'El archivo CSV no tiene un tipo válido.');
            }

            return;
        }
        $this->assertMaterialDocumentoFile($validator, $file, $attribute);
    }

    /**
     * @return array<string, string>
     */
    private function mensajesErrorCargaRequest(Request $request): array
    {
        $out = [];
        foreach ($this->archivosDocumentoRequest($request) as $i => $file) {
            if (! $file || $file->isValid()) {
                continue;
            }
            $attr = $i === 'legacy' ? 'documento' : 'documentos.'.$i;
            $out[$attr.'.uploaded'] = $this->mensajeErrorCarga($file, false);
        }
        foreach ($this->archivosMultimediaRequest($request) as $i => $file) {
            if (! $file || $file->isValid()) {
                continue;
            }
            $attr = $i === 'legacy' ? 'video' : 'multimedia.'.$i;
            $out[$attr.'.uploaded'] = $this->mensajeErrorCarga($file, true);
        }

        return $out;
    }

    private function mensajeErrorCarga(\Illuminate\Http\UploadedFile $file, bool $esMultimedia): string
    {
        return match ($file->getError()) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'El archivo supera el tamaño máximo permitido de 50 MB.',
            UPLOAD_ERR_PARTIAL => 'El archivo no pudo cargarse completamente. Inténtalo nuevamente.',
            UPLOAD_ERR_NO_FILE => 'No se recibió el archivo.',
            UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE, UPLOAD_ERR_EXTENSION => $esMultimedia
                ? 'El servidor no pudo recibir el archivo multimedia.'
                : 'El servidor no pudo recibir el archivo.',
            default => $esMultimedia
                ? 'El servidor no pudo recibir el archivo multimedia.'
                : 'El servidor no pudo recibir el archivo.',
        };
    }

    private function assertMultimediaFile($validator, ?\Illuminate\Http\UploadedFile $file, string $attribute): void
    {
        if (! $file || ! $file->isValid()) {
            return;
        }
        $ext = strtolower((string) $file->getClientOriginalExtension());
        if (! in_array($ext, $this->extensionesMultimedia(), true)) {
            $validator->errors()->add($attribute, 'Tipo de archivo no permitido. Solo se permiten: MP4, WebM, MOV, AVI o MP3.');

            return;
        }
        $secondary = Validator::make([$attribute => $file], [
            $attribute => 'mimes:'.implode(',', $this->extensionesMultimedia()),
        ]);
        if ($secondary->fails()) {
            $validator->errors()->add($attribute, 'Tipo de archivo no permitido. Solo se permiten: MP4, WebM, MOV, AVI o MP3.');
        }
    }

    /**
     * @return list<array{tipo: string, titulo: ?string, ruta: string, formato: ?string}>
     */
    private function almacenarArchivosNuevos(Request $request): array
    {
        $out = [];
        foreach ($this->archivosDocumentoRequest($request) as $file) {
            $ext = strtolower((string) $file->getClientOriginalExtension());
            $out[] = [
                'tipo' => MaterialCompetenciaRecurso::TIPO_DOCUMENTO,
                'titulo' => $file->getClientOriginalName(),
                'ruta' => $this->storeFileInPublicDir($file, 'material-competencia/documentos'),
                'formato' => $ext ?: 'documento',
            ];
        }
        foreach ($this->archivosMultimediaRequest($request) as $file) {
            $ext = strtolower((string) $file->getClientOriginalExtension());
            $dir = in_array($ext, $this->extensionesAudio(), true)
                ? 'material-competencia/audio'
                : 'material-competencia/videos';
            $out[] = [
                'tipo' => MaterialCompetenciaRecurso::TIPO_MULTIMEDIA,
                'titulo' => $file->getClientOriginalName(),
                'ruta' => $this->storeFileInPublicDir($file, $dir),
                'formato' => $ext ?: 'video',
            ];
        }

        return $out;
    }

    /**
     * @return list<array{tipo: string, titulo: ?string, ruta: string, formato: ?string}>
     */
    private function enlacesValidados(Request $request): array
    {
        $out = [];
        $vistos = [];
        foreach ($this->enlacesRequest($request) as $enlace) {
            $url = trim((string) ($enlace['url'] ?? ''));
            if ($url === '') {
                continue;
            }
            $clasif = MaterialCompetenciaEnlace::clasificar($url);
            if (! ($clasif['ok'] ?? false)) {
                continue;
            }
            $ruta = $clasif['url'];
            if (isset($vistos[$ruta])) {
                continue;
            }
            $vistos[$ruta] = true;
            $titulo = trim((string) ($enlace['titulo'] ?? ''));
            $out[] = [
                'tipo' => MaterialCompetenciaRecurso::TIPO_ENLACE,
                'titulo' => $titulo !== '' ? $titulo : null,
                'ruta' => $ruta,
                'formato' => $clasif['formato'] ?? MaterialCompetenciaEnlace::FORMATO_ENLACE,
            ];
        }

        return $out;
    }

    /**
     * @param  list<array{tipo: string, titulo: ?string, ruta: string, formato: ?string}>  $pendientes
     * @param  list<array{tipo: string, titulo: ?string, ruta: string, formato: ?string}>  $enlaces
     */
    private function persistirRecursos(MaterialCompetencia $material, array $pendientes, array $enlaces): void
    {
        if (! $this->tablaRecursosLista()) {
            return;
        }
        $orden = (int) $material->recursos()->max('orden');
        $existentes = [];
        foreach ($material->recursos()->get(['tipo', 'ruta']) as $row) {
            $existentes[mb_strtolower($row->tipo.'|'.$row->ruta)] = true;
        }
        foreach (array_merge($pendientes, $enlaces) as $item) {
            $clave = mb_strtolower(($item['tipo'] ?? '').'|'.($item['ruta'] ?? ''));
            if ($clave === '|' || isset($existentes[$clave])) {
                continue;
            }
            $orden++;
            $material->recursos()->create([
                'tipo' => $item['tipo'],
                'titulo' => $item['titulo'] ?? null,
                'descripcion' => null,
                'ruta' => $item['ruta'],
                'formato' => $item['formato'] ?? null,
                'orden' => $orden,
            ]);
            $existentes[$clave] = true;
        }
        $this->sincronizarCamposLegacy($material);
    }

    private function actualizarEnlacesExistentes(MaterialCompetencia $material, Request $request): void
    {
        $items = $request->input('enlacesActualizar', []);
        if (! is_array($items) || $items === []) {
            return;
        }
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }
            $idRecurso = (int) ($item['id'] ?? 0);
            if ($idRecurso <= 0) {
                continue;
            }
            $recurso = $material->recursos()->whereKey($idRecurso)->where('tipo', MaterialCompetenciaRecurso::TIPO_ENLACE)->first();
            if (! $recurso) {
                continue;
            }
            $url = trim((string) ($item['url'] ?? $recurso->ruta));
            $clasif = MaterialCompetenciaEnlace::clasificar($url);
            if ($clasif['ok'] ?? false) {
                $recurso->ruta = $clasif['url'];
                $recurso->formato = $clasif['formato'] ?? $recurso->formato;
            }
            if (array_key_exists('titulo', $item)) {
                $titulo = trim((string) $item['titulo']);
                $recurso->titulo = $titulo !== '' ? $titulo : null;
            }
            $recurso->save();
        }
    }

    private function sincronizarCamposLegacy(MaterialCompetencia $material): void
    {
        if (! $this->tablaRecursosLista()) {
            return;
        }
        $material->load('recursos');
        $doc = $material->recursos->firstWhere('tipo', MaterialCompetenciaRecurso::TIPO_DOCUMENTO);
        $media = $material->recursos->firstWhere('tipo', MaterialCompetenciaRecurso::TIPO_MULTIMEDIA);
        $enlace = $material->recursos->firstWhere('tipo', MaterialCompetenciaRecurso::TIPO_ENLACE);
        $youtube = $material->recursos->first(function ($r) {
            return $r->tipo === MaterialCompetenciaRecurso::TIPO_ENLACE && $r->formato === 'youtube';
        });
        $material->urlDocumento = $doc?->ruta;
        $material->urlAdicional = $enlace?->ruta;
        if ($this->tieneColumnaUrlVideo()) {
            $material->urlVideo = $media?->ruta ?: $youtube?->ruta;
        }
        $material->save();
    }

    /**
     * @param  list<array{tipo: string, ruta: string}>  $items
     */
    private function primeraRutaTipo(array $items, string $tipo): ?string
    {
        foreach ($items as $item) {
            if (($item['tipo'] ?? '') === $tipo) {
                return $item['ruta'] ?? null;
            }
        }

        return null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function recursosPublicos(MaterialCompetencia $m): array
    {
        $out = [];
        if ($this->tablaRecursosLista() && $m->relationLoaded('recursos') && $m->recursos->isNotEmpty()) {
            foreach ($m->recursos as $r) {
                $out[] = $this->toRecursoPublico(
                    (int) $r->id,
                    (string) $r->tipo,
                    $r->titulo,
                    $r->ruta,
                    $r->formato,
                    false
                );
            }
            $this->fusionarRecursosLegacyFaltantes($m, $out);

            return $out;
        }

        return $this->recursosLegacyPublicos($m);
    }

    /**
     * @param  list<array<string, mixed>>  $out
     */
    private function fusionarRecursosLegacyFaltantes(MaterialCompetencia $m, array &$out): void
    {
        $claves = [];
        foreach ($out as $item) {
            $ruta = (string) (($item['ruta'] ?? null) ?: ($item['urlExterna'] ?? ''));
            $claves[mb_strtolower(($item['tipo'] ?? '').'|'.$ruta)] = true;
        }
        foreach ($this->recursosLegacyPublicos($m) as $item) {
            $ruta = (string) (($item['ruta'] ?? null) ?: ($item['urlExterna'] ?? ''));
            $clave = mb_strtolower(($item['tipo'] ?? '').'|'.$ruta);
            if ($ruta === '' || isset($claves[$clave])) {
                continue;
            }
            $out[] = $item;
            $claves[$clave] = true;
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function recursosLegacyPublicos(MaterialCompetencia $m): array
    {
        $out = [];
        $legacy = $this->resolverRecurso($m);
        if (! empty($legacy['path'])) {
            $out[] = $this->toRecursoPublico(null, MaterialCompetenciaRecurso::TIPO_DOCUMENTO, $m->titulo, $legacy['path'], MaterialCompetenciaEnlace::formatoDesdeRuta($legacy['path']), true);
        }
        if (! empty($legacy['urlVideo'])) {
            $clasif = MaterialCompetenciaEnlace::clasificar($legacy['urlVideo']);
            if (($clasif['formato'] ?? null) === MaterialCompetenciaEnlace::FORMATO_YOUTUBE) {
                $out[] = $this->toRecursoPublico(null, MaterialCompetenciaRecurso::TIPO_ENLACE, $m->titulo, $clasif['url'], 'youtube', true);
            } else {
                $out[] = $this->toRecursoPublico(null, MaterialCompetenciaRecurso::TIPO_MULTIMEDIA, $m->titulo, $legacy['urlVideo'], MaterialCompetenciaEnlace::formatoDesdeRuta($legacy['urlVideo']), true);
            }
        }
        if (! empty($legacy['urlAdicional'])) {
            $clasif = MaterialCompetenciaEnlace::clasificar($legacy['urlAdicional']);
            $ruta = $clasif['ok'] ?? false ? $clasif['url'] : $legacy['urlAdicional'];
            $formato = $clasif['ok'] ?? false ? $clasif['formato'] : 'enlace';
            $out[] = $this->toRecursoPublico(null, MaterialCompetenciaRecurso::TIPO_ENLACE, $m->titulo, $ruta, $formato, true);
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    private function toRecursoPublico(?int $id, string $tipo, ?string $titulo, ?string $ruta, ?string $formato, bool $legacy): array
    {
        $formato = $formato ?: MaterialCompetenciaEnlace::formatoDesdeRuta($ruta);
        $esLocal = $ruta && ! str_starts_with($ruta, 'http://') && ! str_starts_with($ruta, 'https://');
        $youtubeId = MaterialCompetenciaEnlace::youtubeId($ruta);
        $naturaleza = 'enlace';
        if ($tipo === MaterialCompetenciaRecurso::TIPO_DOCUMENTO) {
            $naturaleza = 'documento';
        } elseif ($tipo === MaterialCompetenciaRecurso::TIPO_MULTIMEDIA) {
            $naturaleza = in_array(strtolower((string) $formato), $this->extensionesAudio(), true) ? 'audio' : 'video';
        } elseif ($youtubeId) {
            $naturaleza = 'youtube';
        } elseif ($formato === MaterialCompetenciaEnlace::FORMATO_PDF_EXTERNO) {
            $naturaleza = 'pdf_externo';
        }

        $nombre = $titulo;
        if (! $nombre && $ruta) {
            $nombre = $esLocal ? basename($ruta) : $ruta;
        }

        return [
            'id' => $id,
            'tipo' => $tipo,
            'titulo' => $titulo,
            'nombre' => $nombre,
            'ruta' => $esLocal ? $ruta : null,
            'url' => $this->publicUrl($ruta),
            'urlExterna' => $esLocal ? null : $ruta,
            'formato' => $formato,
            'naturaleza' => $naturaleza,
            'youtubeEmbedUrl' => MaterialCompetenciaEnlace::youtubeEmbedUrl($ruta),
            'youtubeWatchUrl' => MaterialCompetenciaEnlace::youtubeWatchUrl($ruta),
            'legacy' => $legacy,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function recursoDeMaterial(MaterialCompetencia $material, int $idRecurso): ?array
    {
        foreach ($this->recursosPublicos($material) as $item) {
            if ((int) ($item['id'] ?? 0) === $idRecurso) {
                return $item;
            }
        }

        return null;
    }

    private function pareceRutaLocal(string $path): bool
    {
        return str_starts_with($path, 'material-competencia/') || str_starts_with($path, 'material-rap/');
    }

    private function esViolacionUnica(QueryException $e): bool
    {
        $code = (string) $e->getCode();
        $msg = $e->getMessage();

        return $code === '23000'
            || str_contains($msg, 'materialcompetencia_comp_clave_unique')
            || str_contains($msg, 'materialrap_rap_clave_unique')
            || str_contains($msg, 'Duplicate');
    }
}
