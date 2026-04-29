<?php

namespace App\Http\Controllers\ambiente_virtual;

use App\Http\Controllers\Controller;
use App\Models\Ficha;
use App\Models\MaterialApoyoRap;
use App\Models\Materia;
use App\Util\KeyUtil;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/** CRUD de material de apoyo de consulta por ficha + RAP (tabla materialApoyoRap). */
class MaterialApoyoFichaController extends Controller
{
    public function index(int $idFicha): JsonResponse
    {
        try {
            Ficha::findOrFail($idFicha);
            if (! Schema::hasTable((new MaterialApoyoRap())->getTable())) {
                return response()->json([]);
            }

            $query = MaterialApoyoRap::query()->where('idFicha', $idFicha);

            $idMateria = (int) request()->query('idMateria', 0);
            $idRap = (int) request()->query('idRap', 0);
            if ($idMateria > 0) {
                $query->where('idMateria', $idMateria);
            }
            if ($idRap > 0) {
                $query->where('idRap', $idRap);
            }

            $rows = $query->with('persona')->orderByDesc('id')->get();

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

    public function store(Request $request, int $idFicha): JsonResponse
    {
        try {
            Ficha::findOrFail($idFicha);
            if (! Schema::hasTable((new MaterialApoyoRap())->getTable())) {
                return response()->json(['error' => 'Ejecute migraciones para habilitar material de apoyo por ficha y RAP.'], 503);
            }

            $request->validate([
                'idMateria' => 'required|integer|exists:materia,id',
                'idRap' => 'required|integer|exists:materia,id',
                'titulo' => 'required|string|max:255',
                'descripcion' => 'nullable|string|max:3000',
                'documento' => 'nullable|file|mimes:pdf|max:10240',
                'urlAdicional' => 'nullable|string|max:500',
            ]);

            $materia = Materia::findOrFail((int) $request->idMateria);
            $rap = Materia::findOrFail((int) $request->idRap);
            if ($this->competenciaId($materia) !== $this->competenciaId($rap)) {
                return response()->json(['error' => 'El RAP seleccionado no pertenece a la misma competencia/materia base.'], 422);
            }

            $path = null;
            if ($request->hasFile('documento')) {
                $file = $request->file('documento');
                $dir = "fichas/{$idFicha}/material-apoyo-rap";
                if (! Storage::disk('public')->exists($dir)) {
                    Storage::disk('public')->makeDirectory($dir, 0755, true);
                }
                $filename = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $file->getClientOriginalName());
                $path = $file->storeAs($dir, $filename, 'public');
            }

            if (! $path && empty($request->urlAdicional)) {
                return response()->json(['errors' => ['Se requiere documento PDF o enlace']], 422);
            }

            $user = KeyUtil::user();
            $idPersona = $user?->idpersona ? (int) $user->idpersona : null;

            $payloadCreate = [
                'titulo' => $request->titulo,
                'descripcion' => $request->descripcion ?? null,
                'urlDocumento' => $path,
                'urlAdicional' => $request->urlAdicional ? trim((string) $request->urlAdicional) : null,
                'idMateria' => (int) $request->idMateria,
                'idFicha' => $idFicha,
                'idRap' => (int) $request->idRap,
            ];
            if (Schema::hasColumn((new MaterialApoyoRap())->getTable(), 'idPersona')) {
                $payloadCreate['idPersona'] = $idPersona;
            }
            if (Schema::hasColumn((new MaterialApoyoRap())->getTable(), 'activo')) {
                $payloadCreate['activo'] = true;
            }

            $material = MaterialApoyoRap::create($payloadCreate);
            $material->load('persona');

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
            $material = MaterialApoyoRap::where('idFicha', $idFicha)->whereKey($id)->firstOrFail();

            $request->validate([
                'titulo' => 'sometimes|required|string|max:255',
                'descripcion' => 'nullable|string|max:3000',
                'documento' => 'nullable|file|mimes:pdf|max:10240',
                'urlAdicional' => 'nullable|string|max:500',
                'idMateria' => 'sometimes|required|integer|exists:materia,id',
                'idRap' => 'sometimes|required|integer|exists:materia,id',
                'activo' => 'sometimes|boolean',
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
            if ($request->has('activo') && Schema::hasColumn((new MaterialApoyoRap())->getTable(), 'activo')) {
                $material->activo = filter_var($request->activo, FILTER_VALIDATE_BOOLEAN);
            }

            $materia = Materia::findOrFail((int) $material->idMateria);
            $rap = Materia::findOrFail((int) $material->idRap);
            if ($this->competenciaId($materia) !== $this->competenciaId($rap)) {
                return response()->json(['error' => 'El RAP seleccionado no pertenece a la misma competencia/materia base.'], 422);
            }

            if ($request->hasFile('documento')) {
                if ($material->urlDocumento && Storage::disk('public')->exists($material->urlDocumento)) {
                    Storage::disk('public')->delete($material->urlDocumento);
                }
                $file = $request->file('documento');
                $dir = "fichas/{$idFicha}/material-apoyo-rap";
                if (! Storage::disk('public')->exists($dir)) {
                    Storage::disk('public')->makeDirectory($dir, 0755, true);
                }
                $filename = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $file->getClientOriginalName());
                $material->urlDocumento = $file->storeAs($dir, $filename, 'public');
            }

            if (! $material->urlDocumento && empty($material->urlAdicional)) {
                return response()->json(['errors' => ['Se requiere documento PDF o enlace']], 422);
            }

            $material->save();
            $material->load('persona');

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
            $material = MaterialApoyoRap::where('idFicha', $idFicha)->whereKey($id)->firstOrFail();

            if ($material->urlDocumento && Storage::disk('public')->exists($material->urlDocumento)) {
                Storage::disk('public')->delete($material->urlDocumento);
            }
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

        $creadoPorNombre = null;
        if ($m->relationLoaded('persona') && $m->persona) {
            $p = $m->persona;
            $creadoPorNombre = trim(implode(' ', array_filter([$p->nombre1, $p->nombre2, $p->apellido1, $p->apellido2])));
        }

        $out = [
            'id' => $m->id,
            'titulo' => $m->titulo,
            'descripcion' => $m->descripcion,
            'urlDocumento' => $m->urlDocumento,
            'urlDocumentoUrl' => $this->publicUrl($m->urlDocumento),
            'urlAdicional' => $m->urlAdicional,
            'idMateria' => $m->idMateria,
            'idFicha' => $m->idFicha,
            'idRap' => $m->idRap,
            'rap' => $rap,
            'created_at' => $m->created_at,
            'updated_at' => $m->updated_at,
        ];

        if (Schema::hasColumn($m->getTable(), 'idPersona')) {
            $out['idPersona'] = $m->idPersona;
            $out['creadoPorNombre'] = $creadoPorNombre;
        }
        if (Schema::hasColumn($m->getTable(), 'activo')) {
            $out['activo'] = (bool) $m->activo;
        }

        return $out;
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
