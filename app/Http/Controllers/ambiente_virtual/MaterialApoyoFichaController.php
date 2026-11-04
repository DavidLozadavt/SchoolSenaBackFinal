<?php

namespace App\Http\Controllers\ambiente_virtual;

use App\Http\Controllers\Concerns\ValidatesMaterialDocumentUpload;
use App\Http\Controllers\Controller;
use App\Models\Ficha;
use App\Models\MaterialApoyoRap;
use App\Models\Materia;
use App\Models\Person;
use App\Services\ambiente_virtual\RapAccessService;
use App\Util\KeyUtil;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

/**
 * CRUD de biblioteca de conocimiento (tabla materialApoyoRap).
 * La vista general (menú Biblioteca) lista por Competencia/RAP.
 * Las rutas por ficha se conservan para el detalle de clase.
 * idFicha en la tabla es contexto legado de persistencia, no el dueño del recurso.
 */
class MaterialApoyoFichaController extends Controller
{
    use ValidatesMaterialDocumentUpload;

    public function __construct(private RapAccessService $rapAccess)
    {
    }

    /**
     * Fichas que comparten el mismo programa que la ficha dada (idAsignacion → idPrograma).
     *
     * @return array<int, int>
     */
    private function fichaIdsMismoPrograma(int $idFicha): array
    {
        $base = [(int) $idFicha];
        if (! Schema::hasTable('ficha') || ! Schema::hasTable('aperturarprograma')) {
            return $base;
        }
        $idAsignacion = Ficha::query()->whereKey($idFicha)->value('idAsignacion');
        if (! $idAsignacion) {
            return $base;
        }
        $idPrograma = DB::table('aperturarprograma')->where('id', $idAsignacion)->value('idPrograma');
        if (! $idPrograma) {
            return $base;
        }
        $ids = DB::table('ficha as f')
            ->join('aperturarprograma as ap', 'f.idAsignacion', '=', 'ap.id')
            ->where('ap.idPrograma', $idPrograma)
            ->pluck('f.id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        return $ids !== [] ? $ids : $base;
    }

    private function usuarioPuedeMutarMaterialApoyoRap(MaterialApoyoRap $material): bool
    {
        $user = KeyUtil::user();
        if (! $user || ! $user->idpersona) {
            return false;
        }
        if ($material->idPersona !== null && (int) $material->idPersona === (int) $user->idpersona) {
            return true;
        }
        $permKeys = collect(KeyUtil::permissions())->map(fn ($k) => (string) $k)->all();

        return in_array('GESTION_USUARIO', $permKeys, true);
    }

    /**
     * Listado legado por ficha (detalle de clase histórico).
     * El menú Biblioteca usa indexGeneral: Competencia/RAP, sin selector de ficha.
     */
    public function index(int $idFicha): JsonResponse
    {
        try {
            Ficha::findOrFail($idFicha);
            $tabla = (new MaterialApoyoRap())->getTable();
            if (! Schema::hasTable($tabla)) {
                return response()->json([]);
            }

            $fichaIds = $this->fichaIdsMismoPrograma($idFicha);
            $query = MaterialApoyoRap::query()->whereIn('idFicha', $fichaIds);

            // Filtros opcionales (misma semántica que Actividades: competencia + RAP).
            $idCompetencia = (int) request()->query('idCompetencia', 0);
            $idRap = (int) request()->query('idRap', 0);
            if ($idRap > 0) {
                $query->where('idRap', $idRap);
            }
            if ($idCompetencia > 0) {
                $query->whereHas('rap', function ($q) use ($idCompetencia) {
                    $q->where('idMateriaPadre', $idCompetencia);
                });
            }

            $rows = $query->orderByDesc('id')->get();

            return response()->json($rows->map(fn ($m) => $this->toResource($m))->values());
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function raps(int $idFicha): JsonResponse
    {
        try {
            Ficha::findOrFail($idFicha);
            $idMateria = (int) request()->query('idMateria', 0);
            if ($idMateria <= 0) {
                return response()->json([]);
            }
            $materia = Materia::findOrFail($idMateria);
            $idCompetencia = $this->competenciaId($materia);
            $raps = Materia::query()
                ->where('idMateriaPadre', $idCompetencia)
                ->orderBy('nombreMateria')
                ->get(['id', 'nombreMateria', 'idMateriaPadre']);

            if ($raps->isEmpty() && $materia->idMateriaPadre !== null) {
                return response()->json([[
                    'id' => (int) $materia->id,
                    'nombre' => $materia->nombreMateria,
                    'idCompetencia' => (int) $materia->idMateriaPadre,
                    'nombreCompetencia' => $materia->padre?->nombreMateria,
                ]]);
            }

            $competenciaNombre = Materia::query()->whereKey($idCompetencia)->value('nombreMateria');

            return response()->json($raps->map(fn ($r) => [
                'id' => (int) $r->id,
                'nombre' => $r->nombreMateria,
                'idCompetencia' => (int) ($r->idMateriaPadre ?? 0),
                'nombreCompetencia' => $competenciaNombre,
            ])->values());
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Biblioteca general del instructor: recursos de sus RAP, sin filtrar por ficha seleccionada.
     */
    public function indexGeneral(Request $request): JsonResponse
    {
        try {
            $gate = $this->asegurarInstructorBiblioteca();
            if ($gate instanceof JsonResponse) {
                return $gate;
            }

            $tabla = (new MaterialApoyoRap())->getTable();
            if (! Schema::hasTable($tabla)) {
                return response()->json([]);
            }

            $idsRap = $this->rapAccess->idsRapAccesibles();
            if ($idsRap === []) {
                return response()->json([]);
            }

            $query = MaterialApoyoRap::query()->whereIn('idRap', $idsRap);

            $idCompetencia = (int) $request->query('idCompetencia', 0);
            $idRap = (int) $request->query('idRap', 0);
            $buscar = trim((string) $request->query('buscar', ''));
            if ($idRap > 0) {
                $query->where('idRap', $idRap);
            }
            if ($idCompetencia > 0) {
                $query->whereHas('rap', function ($q) use ($idCompetencia) {
                    $q->where('idMateriaPadre', $idCompetencia);
                });
            }
            if ($buscar !== '') {
                $like = '%'.$buscar.'%';
                $query->where(function ($q) use ($like) {
                    $q->where('titulo', 'like', $like)
                        ->orWhere('descripcion', 'like', $like);
                });
            }

            $rows = $query->orderByDesc('id')->get()->unique('id')->values();

            return response()->json($rows->map(fn ($m) => $this->toResource($m))->values());
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function rapsGeneral(): JsonResponse
    {
        try {
            $gate = $this->asegurarInstructorBiblioteca();
            if ($gate instanceof JsonResponse) {
                return $gate;
            }

            $raps = $this->rapAccess->rapsAccesibles();

            return response()->json(array_values(array_map(static function (array $r) {
                return [
                    'id' => (int) ($r['id'] ?? 0),
                    'nombre' => (string) ($r['nombreMateria'] ?? ''),
                    'codigo' => $r['codigo'] ?? null,
                    'idCompetencia' => isset($r['idCompetencia']) ? (int) $r['idCompetencia'] : 0,
                    'nombreCompetencia' => $r['competenciaNombre'] ?? null,
                    'codigoCompetencia' => $r['competenciaCodigo'] ?? null,
                ];
            }, $raps)));
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function storeGeneral(Request $request): JsonResponse
    {
        try {
            $gate = $this->asegurarInstructorBiblioteca();
            if ($gate instanceof JsonResponse) {
                return $gate;
            }

            $idRap = (int) $request->input('idRap', 0);
            if ($idRap <= 0) {
                return response()->json(['error' => 'Indique el RAP del recurso.'], 422);
            }
            if (! $this->rapAccess->usuarioPuedeAccederRap($idRap)) {
                return response()->json(['error' => 'No autorizado para este RAP.'], 403);
            }

            $rap = Materia::find($idRap);
            if (! $rap) {
                return response()->json(['error' => 'El RAP indicado no existe.'], 404);
            }
            if (! $request->filled('idMateria')) {
                $request->merge(['idMateria' => $this->competenciaId($rap)]);
            }

            $idFicha = $this->idFichaContextoParaRap($idRap);
            if ($idFicha <= 0) {
                return response()->json(['error' => 'No hay contexto académico para guardar el recurso.'], 422);
            }

            return $this->store($request, $idFicha);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function updateGeneral(Request $request, int $id): JsonResponse
    {
        try {
            $gate = $this->asegurarInstructorBiblioteca();
            if ($gate instanceof JsonResponse) {
                return $gate;
            }
            $material = $this->materialGeneralOFail($id);
            if ($material instanceof JsonResponse) {
                return $material;
            }

            return $this->update($request, (int) $material->idFicha, $id);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function destroyGeneral(int $id): JsonResponse
    {
        try {
            $gate = $this->asegurarInstructorBiblioteca();
            if ($gate instanceof JsonResponse) {
                return $gate;
            }
            $material = $this->materialGeneralOFail($id);
            if ($material instanceof JsonResponse) {
                return $material;
            }

            return $this->destroy((int) $material->idFicha, $id);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function store(Request $request, int $idFicha): JsonResponse
    {
        try {
            Ficha::findOrFail($idFicha);
            if (! Schema::hasTable((new MaterialApoyoRap())->getTable())) {
                return response()->json(['error' => 'Ejecute migraciones para habilitar material de apoyo por ficha y RAP.'], 503);
            }

            $this->inspectMaterialUploads($request);
            $validator = Validator::make($request->all(), array_merge([
                'idMateria' => 'required|integer|exists:materia,id',
                'idRap' => 'required|integer|exists:materia,id',
                'titulo' => 'required|string|max:255',
                'descripcion' => 'nullable|string|max:3000',
                'tipoMaterial' => 'nullable|string|in:'.implode(',', MaterialApoyoRap::TIPOS_MATERIAL),
                'urlAdicional' => 'nullable|string|max:500',
                'urlVideo' => 'nullable|string|max:500',
            ], $this->materialDocumentoFileRules(true), $this->materialVideoFileRules(true)), array_merge(
                $this->materialDocumentoValidationMessages(),
                $this->materialVideoValidationMessages(),
                $this->phpUploadValidationMessages($request)
            ));
            $validator->after(function ($v) use ($request) {
                $this->assertMaterialDocumentoFile($v, $request->file('documento'));
                $this->assertMaterialVideoFile($v, $request->file('video'));
            });
            $validator->validate();

            $materia = Materia::findOrFail((int) $request->idMateria);
            $rap = Materia::findOrFail((int) $request->idRap);
            if ($this->competenciaId($materia) !== $this->competenciaId($rap)) {
                return response()->json(['error' => 'El RAP seleccionado no pertenece a la misma competencia/materia base.'], 422);
            }

            $path = null;
            if ($request->hasFile('documento')) {
                $path = $this->storeFileInPublicDir($request->file('documento'), 'material-apoyo-rap/documentos');
            }

            $urlVideoValue = null;
            if ($request->hasFile('video')) {
                $urlVideoValue = $this->storeFileInPublicDir($request->file('video'), 'material-apoyo-rap/videos');
            } elseif ($request->filled('urlVideo')) {
                $urlVideoValue = trim((string) $request->urlVideo);
            }

            if (! $path && empty($request->urlAdicional) && empty($urlVideoValue)) {
                return response()->json(['errors' => [
                    'recursos' => ['Ingrese al menos un recurso: documento, enlace o video.'],
                ]], 422);
            }

            $user = KeyUtil::user();
            $idPersonaCreador = $user?->idpersona ? (int) $user->idpersona : null;

            $payloadCreate = [
                'titulo' => $request->titulo ?? null,
                'descripcion' => $request->descripcion ?? null,
                'urlDocumento' => $path,
                'urlAdicional' => $request->urlAdicional ? trim((string) $request->urlAdicional) : null,
                'urlVideo' => $urlVideoValue,
                'idFicha' => $idFicha,
                'idMateria' => (int) $request->idMateria,
                'idRap' => (int) $request->idRap,
                'idPersona' => $idPersonaCreador,
            ];

            $tabla = (new MaterialApoyoRap())->getTable();
            if (Schema::hasColumn($tabla, 'tipoMaterial') && $request->filled('tipoMaterial')) {
                $payloadCreate['tipoMaterial'] = (string) $request->tipoMaterial;
            }

            $material = MaterialApoyoRap::create($payloadCreate);

            return response()->json($this->toResource($material), 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            $msg = $e->getMessage();
            if (stripos($msg, 'storage') !== false || stripos($msg, 'unable to write') !== false) {
                return response()->json(['error' => 'No fue posible guardar el archivo.'], 500);
            }
            return response()->json(['error' => $msg], 500);
        }
    }

    public function update(Request $request, int $idFicha, int $id): JsonResponse
    {
        try {
            if (! Schema::hasTable((new MaterialApoyoRap())->getTable())) {
                return response()->json(['error' => 'Ejecute migraciones para habilitar material de apoyo por ficha y RAP.'], 503);
            }
            $fichaIds = $this->fichaIdsMismoPrograma($idFicha);
            $material = MaterialApoyoRap::whereIn('idFicha', $fichaIds)->whereKey($id)->firstOrFail();

            if (! $this->usuarioPuedeMutarMaterialApoyoRap($material)) {
                return response()->json(['error' => 'No autorizado para editar este material.'], 403);
            }

            $this->inspectMaterialUploads($request);
            $validator = Validator::make($request->all(), array_merge([
                'titulo' => 'sometimes|required|string|max:255',
                'descripcion' => 'nullable|string|max:3000',
                'tipoMaterial' => 'nullable|string|in:'.implode(',', MaterialApoyoRap::TIPOS_MATERIAL),
                'urlAdicional' => 'nullable|string|max:500',
                'urlVideo' => 'nullable|string|max:500',
                'idMateria' => 'sometimes|required|integer|exists:materia,id',
                'idRap' => 'sometimes|required|integer|exists:materia,id',
            ], $this->materialDocumentoFileRules(true), $this->materialVideoFileRules(true)), array_merge(
                $this->materialDocumentoValidationMessages(),
                $this->materialVideoValidationMessages(),
                $this->phpUploadValidationMessages($request)
            ));
            $validator->after(function ($v) use ($request) {
                $this->assertMaterialDocumentoFile($v, $request->file('documento'));
                $this->assertMaterialVideoFile($v, $request->file('video'));
            });
            $validator->validate();

            if ($request->has('titulo')) {
                $material->titulo = $request->titulo;
            }
            if ($request->has('descripcion')) {
                $material->descripcion = $request->descripcion;
            }
            $tabla = (new MaterialApoyoRap())->getTable();
            if (Schema::hasColumn($tabla, 'tipoMaterial') && $request->has('tipoMaterial')) {
                $rawTipo = $request->input('tipoMaterial');
                $material->tipoMaterial = ($rawTipo === null || $rawTipo === '')
                    ? null
                    : (string) $rawTipo;
            }
            if ($request->has('urlAdicional')) {
                $material->urlAdicional = $request->urlAdicional ? trim((string) $request->urlAdicional) : null;
            }
            if ($request->has('idMateria')) {
                $material->idMateria = (int) $request->idMateria;
            }
            if ($request->has('idRap')) {
                $material->idRap = (int) $request->idRap;
            }

            $materia = Materia::findOrFail((int) $material->idMateria);
            $rap = Materia::findOrFail((int) $material->idRap);
            if ($this->competenciaId($materia) !== $this->competenciaId($rap)) {
                return response()->json(['error' => 'El RAP seleccionado no pertenece a la misma competencia/materia base.'], 422);
            }

            if ($request->hasFile('documento')) {
                $this->deleteStoredPublicFileIfLocal($material->urlDocumento);
                $material->urlDocumento = $this->storeFileInPublicDir($request->file('documento'), 'material-apoyo-rap/documentos');
            }

            if ($request->hasFile('video')) {
                $this->deleteStoredPublicFileIfLocal($material->urlVideo);
                $material->urlVideo = $this->storeFileInPublicDir($request->file('video'), 'material-apoyo-rap/videos');
            } elseif ($request->has('urlVideo')) {
                $trimmed = trim((string) ($request->input('urlVideo') ?? ''));
                if ($trimmed === '') {
                    $this->deleteStoredPublicFileIfLocal($material->urlVideo);
                    $material->urlVideo = null;
                } else {
                    // Se permite texto para facilitar pruebas con URLs externas no estandarizadas.
                    $this->deleteStoredPublicFileIfLocal($material->urlVideo);
                    $material->urlVideo = $trimmed;
                }
            }

            if (! $material->urlDocumento && empty($material->urlAdicional) && empty($material->urlVideo)) {
                return response()->json(['errors' => [
                    'recursos' => ['Ingrese al menos un recurso: documento, enlace o video.'],
                ]], 422);
            }

            $material->save();

            return response()->json($this->toResource($material));
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            $msg = $e->getMessage();
            if (stripos($msg, 'storage') !== false || stripos($msg, 'unable to write') !== false) {
                return response()->json(['error' => 'No fue posible guardar el archivo.'], 500);
            }
            return response()->json(['error' => $msg], 500);
        }
    }

    public function destroy(int $idFicha, int $id): JsonResponse
    {
        try {
            if (! Schema::hasTable((new MaterialApoyoRap())->getTable())) {
                return response()->json(['error' => 'Ejecute migraciones para habilitar material de apoyo por ficha y RAP.'], 503);
            }
            $fichaIds = $this->fichaIdsMismoPrograma($idFicha);
            $material = MaterialApoyoRap::whereIn('idFicha', $fichaIds)->whereKey($id)->firstOrFail();

            if (! $this->usuarioPuedeMutarMaterialApoyoRap($material)) {
                return response()->json(['error' => 'No autorizado para eliminar este material.'], 403);
            }

            $this->deleteStoredPublicFileIfLocal($material->urlDocumento);
            $this->deleteStoredPublicFileIfLocal($material->urlVideo);
            $material->delete();

            return response()->json(['message' => 'Material de apoyo eliminado']);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    private function toResource(MaterialApoyoRap $m): array
    {
        $rapModel = Materia::query()->select(['id', 'nombreMateria', 'codigo', 'idMateriaPadre'])->find($m->idRap);
        $rap = null;
        $idCompetencia = 0;
        if ($rapModel) {
            $idCompetencia = (int) ($rapModel->idMateriaPadre ?? 0);
            $rap = [
                'id' => (int) $rapModel->id,
                'nombre' => $rapModel->nombreMateria,
                'codigo' => $rapModel->codigo,
                'idCompetencia' => $idCompetencia,
            ];
        }

        $matModel = Materia::query()->select(['id', 'nombreMateria', 'codigo', 'idMateriaPadre'])->find($m->idMateria);
        $materiaNombre = $matModel?->nombreMateria;
        if ($idCompetencia <= 0 && $matModel) {
            $idCompetencia = ! empty($matModel->idMateriaPadre)
                ? (int) $matModel->idMateriaPadre
                : (int) $matModel->id;
        }
        $compModel = $idCompetencia > 0
            ? Materia::query()->select(['nombreMateria', 'codigo'])->find($idCompetencia)
            : null;
        $competenciaNombre = $compModel?->nombreMateria ?? $materiaNombre;
        $competenciaCodigo = $compModel?->codigo;

        $creador = null;
        if ($m->idPersona) {
            $p = Person::query()->select(['id', 'nombre1', 'nombre2', 'apellido1', 'apellido2', 'email', 'rutaFoto'])->find($m->idPersona);
            if ($p) {
                $creador = [
                    'idPersona' => (int) $p->id,
                    'nombreCompleto' => trim(implode(' ', array_filter([$p->nombre1, $p->nombre2, $p->apellido1, $p->apellido2]))),
                    'email' => $p->email,
                    'rutaFoto' => $p->rutaFoto,
                    'rutaFotoUrl' => $p->rutaFotoUrl ?? null,
                ];
            }
        }

        $tabla = (new MaterialApoyoRap())->getTable();
        $tipoMaterial = Schema::hasColumn($tabla, 'tipoMaterial')
            ? ($m->tipoMaterial ?? null)
            : null;

        $out = [
            'id' => $m->id,
            'titulo' => $m->titulo,
            'descripcion' => $m->descripcion,
            'tipoMaterial' => $tipoMaterial,
            'urlDocumento' => $m->urlDocumento,
            'urlDocumentoUrl' => $this->publicUrl($m->urlDocumento),
            'urlAdicional' => $m->urlAdicional,
            'urlVideo' => $m->urlVideo,
            'urlVideoUrl' => $this->publicUrl($m->urlVideo),
            'idFicha' => $m->idFicha,
            'idMateria' => $m->idMateria,
            'idRap' => $m->idRap,
            'idPersona' => $m->idPersona,
            'idCompetencia' => $idCompetencia > 0 ? $idCompetencia : null,
            'materiaNombre' => $materiaNombre,
            'competenciaNombre' => $competenciaNombre,
            'competenciaCodigo' => $competenciaCodigo ?? null,
            'rap' => $rap,
            'creador' => $creador,
            'created_at' => $m->created_at,
            'updated_at' => $m->updated_at,
        ];

        return $out;
    }

    private function storeFileInPublicDir(\Illuminate\Http\UploadedFile $file, string $dir): string
    {
        if (! Storage::disk('public')->exists($dir)) {
            Storage::disk('public')->makeDirectory($dir, 0755, true);
        }
        $original = preg_replace('/[^a-zA-Z0-9._-]/', '_', $file->getClientOriginalName()) ?: 'archivo';
        $original = trim((string) $original, '._') ?: 'archivo';
        $filename = time().'_'.bin2hex(random_bytes(4)).'_'.$original;

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
        if (Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }

    private function asegurarInstructorBiblioteca(): ?JsonResponse
    {
        if (! $this->rapAccess->usuarioPuedeAdministrar()) {
            return response()->json(['error' => 'No autorizado para administrar la biblioteca de conocimiento.'], 403);
        }

        return null;
    }

    /**
     * idFicha legado para persistir en materialApoyoRap. No se expone como filtro de la vista general.
     */
    private function idFichaContextoParaRap(int $idRap): int
    {
        $ids = $this->rapAccess->idsFichasHorarioDeRap($idRap);
        if ($ids !== []) {
            return (int) $ids[0];
        }
        $fichas = $this->rapAccess->fichasInstructor();
        if ($fichas === []) {
            return 0;
        }

        return (int) ($fichas[0]['id'] ?? 0);
    }

    /**
     * @return MaterialApoyoRap|JsonResponse
     */
    private function materialGeneralOFail(int $id)
    {
        if ($id <= 0 || ! Schema::hasTable((new MaterialApoyoRap())->getTable())) {
            return response()->json(['error' => 'El recurso indicado no existe.'], 404);
        }
        $material = MaterialApoyoRap::query()->whereKey($id)->first();
        if (! $material) {
            return response()->json(['error' => 'El recurso indicado no existe.'], 404);
        }
        $idRap = (int) $material->idRap;
        if ($idRap > 0 && ! $this->rapAccess->usuarioPuedeAccederRap($idRap) && ! $this->usuarioPuedeMutarMaterialApoyoRap($material)) {
            return response()->json(['error' => 'No autorizado para este recurso.'], 403);
        }

        return $material;
    }

    private function competenciaId(Materia $materia): int
    {
        return ! empty($materia->idMateriaPadre) ? (int) $materia->idMateriaPadre : (int) $materia->id;
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
}
