<?php

namespace App\Http\Controllers\ambiente_virtual;

use App\Http\Controllers\Controller;
use App\Models\AsignacionMaterialApoyoActividad;
use App\Models\Ficha;
use App\Models\MaterialApoyoActividad;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/** CRUD de material en materialApoyoActividad con idFicha = ficha actual; la asignación a actividades sigue en asignacionMaterialApoyoActividad. */
class MaterialApoyoFichaController extends Controller
{
    public function index(int $idFicha): JsonResponse
    {
        try {
            Ficha::findOrFail($idFicha);
            if (!Schema::hasTable((new MaterialApoyoActividad())->getTable())) {
                return response()->json([]);
            }
            if (!Schema::hasColumn((new MaterialApoyoActividad())->getTable(), 'idFicha')) {
                return response()->json([]);
            }

            $rows = MaterialApoyoActividad::query()
                ->where('idFicha', $idFicha)
                ->orderByDesc('id')
                ->get();

            return response()->json($rows->map(fn ($m) => $this->toResource($m))->values());
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function store(Request $request, int $idFicha): JsonResponse
    {
        try {
            Ficha::findOrFail($idFicha);
            $tabMat = (new MaterialApoyoActividad())->getTable();
            if (!Schema::hasColumn($tabMat, 'idFicha')) {
                return response()->json(['error' => 'Ejecute migraciones para habilitar material de apoyo por ficha.'], 503);
            }

            $request->validate([
                'idMateria' => 'required|integer|exists:materia,id',
                'titulo' => 'required|string|max:255',
                'descripcion' => 'nullable|string|max:3000',
                'documento' => 'nullable|file|mimes:pdf|max:10240',
                'urlAdicional' => 'nullable|string|max:500',
            ]);

            $path = null;
            if ($request->hasFile('documento')) {
                $file = $request->file('documento');
                $dir = "fichas/{$idFicha}/material-apoyo-general";
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
                'urlAdicional' => $request->urlAdicional ? trim((string) $request->urlAdicional) : null,
                'idMateria' => (int) $request->idMateria,
                'idFicha' => $idFicha,
            ]);

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
            $tabMat = (new MaterialApoyoActividad())->getTable();
            if (!Schema::hasColumn($tabMat, 'idFicha')) {
                return response()->json(['error' => 'Ejecute migraciones para habilitar material de apoyo por ficha.'], 503);
            }
            $material = MaterialApoyoActividad::where('idFicha', $idFicha)->whereKey($id)->firstOrFail();

            $request->validate([
                'titulo' => 'sometimes|required|string|max:255',
                'descripcion' => 'nullable|string|max:3000',
                'documento' => 'nullable|file|mimes:pdf|max:10240',
                'urlAdicional' => 'nullable|string|max:500',
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

            if ($request->hasFile('documento')) {
                if ($material->urlDocumento && Storage::disk('public')->exists($material->urlDocumento)) {
                    Storage::disk('public')->delete($material->urlDocumento);
                }
                $file = $request->file('documento');
                $dir = "fichas/{$idFicha}/material-apoyo-general";
                if (!Storage::disk('public')->exists($dir)) {
                    Storage::disk('public')->makeDirectory($dir, 0755, true);
                }
                $filename = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $file->getClientOriginalName());
                $material->urlDocumento = $file->storeAs($dir, $filename, 'public');
            }

            if (!$material->urlDocumento && empty($material->urlAdicional)) {
                return response()->json(['errors' => ['Se requiere documento PDF o enlace']], 422);
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
            $tabMat = (new MaterialApoyoActividad())->getTable();
            if (!Schema::hasColumn($tabMat, 'idFicha')) {
                return response()->json(['error' => 'Ejecute migraciones para habilitar material de apoyo por ficha.'], 503);
            }
            $material = MaterialApoyoActividad::where('idFicha', $idFicha)->whereKey($id)->firstOrFail();

            AsignacionMaterialApoyoActividad::where('idMaterialApoyo', $material->id)->delete();

            if ($material->urlDocumento && Storage::disk('public')->exists($material->urlDocumento)) {
                Storage::disk('public')->delete($material->urlDocumento);
            }
            $material->delete();

            return response()->json(['message' => 'Material de apoyo eliminado']);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    private function toResource(MaterialApoyoActividad $m): array
    {
        return [
            'id' => $m->id,
            'titulo' => $m->titulo,
            'descripcion' => $m->descripcion,
            'urlDocumento' => $m->urlDocumento,
            'urlDocumentoUrl' => $this->publicUrl($m->urlDocumento),
            'urlAdicional' => $m->urlAdicional,
            'idMateria' => $m->idMateria,
            'idFicha' => $m->idFicha,
            'created_at' => $m->created_at,
            'updated_at' => $m->updated_at,
        ];
    }

    private function publicUrl(?string $path): ?string
    {
        if (!$path) {
            return null;
        }
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return Storage::disk('public')->url($path);
    }
}
