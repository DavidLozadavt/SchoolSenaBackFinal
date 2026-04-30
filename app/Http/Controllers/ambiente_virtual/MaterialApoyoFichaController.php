<?php

namespace App\Http\Controllers\ambiente_virtual;

use App\Http\Controllers\Controller;
use App\Models\MaterialApoyoRap;
use App\Models\Materia;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/** CRUD de material de apoyo de consulta por RAP (tabla materialapoyorap). */
class MaterialApoyoFichaController extends Controller
{
    public function index(int $idFicha): JsonResponse
    {
        try {
            if (! Schema::hasTable((new MaterialApoyoRap())->getTable())) {
                return response()->json([]);
            }

            $query = MaterialApoyoRap::query();

            $idMateria = (int) request()->query('idMateria', 0);
            $idRap = (int) request()->query('idRap', 0);
            if ($idMateria > 0) {
                $query->where('idMateria', $idMateria);
            }
            if ($idRap > 0) {
                $query->where('idRap', $idRap);
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

    public function store(Request $request, int $idFicha): JsonResponse
    {
        try {
            if (! Schema::hasTable((new MaterialApoyoRap())->getTable())) {
                return response()->json(['error' => 'Ejecute migraciones para habilitar material de apoyo por ficha y RAP.'], 503);
            }

            $request->validate([
                'idMateria' => 'required|integer|exists:materia,id',
                'idRap' => 'required|integer|exists:materia,id',
                'titulo' => 'nullable|string|max:255',
                'descripcion' => 'nullable|string|max:3000',
                'documento' => 'nullable|file|mimes:pdf|max:10240',
                'urlAdicional' => 'nullable|string|max:500',
                'video' => 'nullable|file|mimes:mp4,webm,mov,avi|max:51200',
                'urlVideo' => 'nullable|string|max:65535',
            ]);

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
                return response()->json(['errors' => ['Se requiere al menos un recurso: documento PDF, enlace o video']], 422);
            }

            $payloadCreate = [
                'titulo' => $request->titulo ?? null,
                'descripcion' => $request->descripcion ?? null,
                'urlDocumento' => $path,
                'urlAdicional' => $request->urlAdicional ? trim((string) $request->urlAdicional) : null,
                'urlVideo' => $urlVideoValue,
                'idMateria' => (int) $request->idMateria,
                'idRap' => (int) $request->idRap,
            ];

            $material = MaterialApoyoRap::create($payloadCreate);

            return response()->json($this->toResource($material), 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function update(Request $request, int $idFicha, int $id): JsonResponse
    {
        try {
            if (! Schema::hasTable((new MaterialApoyoRap())->getTable())) {
                return response()->json(['error' => 'Ejecute migraciones para habilitar material de apoyo por ficha y RAP.'], 503);
            }
            $material = MaterialApoyoRap::whereKey($id)->firstOrFail();

            $request->validate([
                'titulo' => 'sometimes|nullable|string|max:255',
                'descripcion' => 'nullable|string|max:3000',
                'documento' => 'nullable|file|mimes:pdf|max:10240',
                'urlAdicional' => 'nullable|string|max:500',
                'video' => 'nullable|file|mimes:mp4,webm,mov,avi|max:51200',
                'urlVideo' => 'nullable|string|max:65535',
                'idMateria' => 'sometimes|required|integer|exists:materia,id',
                'idRap' => 'sometimes|required|integer|exists:materia,id',
            ]);

            if ($request->has('titulo')) {
                $material->titulo = $request->titulo;
            }
            if ($request->has('descripcion')) {
                $material->descripcion = $request->descripcion;
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
                return response()->json(['errors' => ['Se requiere al menos un recurso: documento PDF, enlace o video']], 422);
            }

            $material->save();

            return response()->json($this->toResource($material));
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function destroy(int $idFicha, int $id): JsonResponse
    {
        try {
            if (! Schema::hasTable((new MaterialApoyoRap())->getTable())) {
                return response()->json(['error' => 'Ejecute migraciones para habilitar material de apoyo por ficha y RAP.'], 503);
            }
            $material = MaterialApoyoRap::whereKey($id)->firstOrFail();

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
        $rapModel = Materia::query()->select(['id', 'nombreMateria', 'idMateriaPadre'])->find($m->idRap);
        $rap = null;
        if ($rapModel) {
            $rap = [
                'id' => (int) $rapModel->id,
                'nombre' => $rapModel->nombreMateria,
                'idCompetencia' => (int) ($rapModel->idMateriaPadre ?? 0),
            ];
        }

        $out = [
            'id' => $m->id,
            'titulo' => $m->titulo,
            'descripcion' => $m->descripcion,
            'urlDocumento' => $m->urlDocumento,
            'urlDocumentoUrl' => $this->publicUrl($m->urlDocumento),
            'urlAdicional' => $m->urlAdicional,
            'urlVideo' => $m->urlVideo,
            'urlVideoUrl' => $this->publicUrl($m->urlVideo),
            'idMateria' => $m->idMateria,
            'idRap' => $m->idRap,
            'rap' => $rap,
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
        $filename = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $file->getClientOriginalName());

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
