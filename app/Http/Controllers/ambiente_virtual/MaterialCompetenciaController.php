<?php

namespace App\Http\Controllers\ambiente_virtual;

use App\Http\Controllers\Concerns\ValidatesMaterialDocumentUpload;
use App\Http\Controllers\Controller;
use App\Models\Actividad;
use App\Models\AsignacionMaterialApoyoActividad;
use App\Models\MaterialApoyoActividad;
use App\Models\MaterialCompetencia;
use App\Models\Materia;
use App\Models\Person;
use App\Services\ambiente_virtual\RapAccessService;
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
                return response()->json(['error' => 'No autorizado para Actividades competencias RAP.'], 403);
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

            $validator = Validator::make($request->all(), array_merge([
                'titulo' => 'required|string|max:255',
                'descripcion' => 'nullable|string|max:3000',
                'tipoMaterial' => 'nullable|string|in:'.implode(',', MaterialCompetencia::TIPOS_MATERIAL),
                'urlAdicional' => 'nullable|string|max:500',
            ], $this->materialDocumentoFileRules(true)), $this->materialDocumentoValidationMessages());
            $validator->after(function ($v) use ($request) {
                if ($request->hasFile('documento')) {
                    $this->assertMaterialDocumentoFile($v, $request->file('documento'));
                }
            });
            $validator->validate();

            $path = null;
            if ($request->hasFile('documento')) {
                $path = $this->storeFileInPublicDir($request->file('documento'), 'material-competencia/documentos');
            }
            $urlAdicional = $request->filled('urlAdicional') ? trim((string) $request->urlAdicional) : null;
            if (! $path && ! $urlAdicional) {
                return response()->json(['errors' => ['Se requiere documento o enlace']], 422);
            }

            $material = DB::transaction(function () use ($request, $idCompetencia, $path, $urlAdicional) {
                return MaterialCompetencia::create([
                    'idCompetencia' => $idCompetencia,
                    'idPersona' => $this->idPersonaActual(),
                    'idActividad' => null,
                    'idMaterialApoyoActividad' => null,
                    'origen' => MaterialCompetencia::ORIGEN_DIRECTO,
                    'titulo' => $request->titulo,
                    'descripcion' => $request->descripcion ?? null,
                    'tipoMaterial' => $request->filled('tipoMaterial') ? (string) $request->tipoMaterial : null,
                    'urlDocumento' => $path,
                    'urlAdicional' => $urlAdicional,
                    'activo' => true,
                ]);
            });

            $material->load($this->relacionesMaterial());

            return response()->json($this->toResource($material, true), 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
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
                if ($material->esDirecto()) {
                    $this->deleteStoredPublicFileIfLocal($material->urlDocumento);
                }
                $material->delete();
            });

            return response()->json(['message' => 'Material desvinculado de la competencia.']);
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
            return response()->json(['error' => 'No autorizado para Actividades competencias RAP.'], 403);
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
        return Schema::hasTable((new MaterialCompetencia())->getTable());
    }

    private function mensajeMigracion(): string
    {
        return 'Ejecute la migración de materialCompetencia para habilitar el material por competencia.';
    }

    /**
     * @return array<int, string|array<string, string>>
     */
    private function relacionesMaterial(): array
    {
        return [
            'persona:id,nombre1,nombre2,apellido1,apellido2,email,rutaFoto',
            'actividad:id,tituloActividad,idPersona,idMateria,pathDocumentoActividad',
            'actividad.persona:id,nombre1,nombre2,apellido1,apellido2,email,rutaFoto',
            'actividad.materia:id,nombreMateria,idMateriaPadre,codigo',
            'materialApoyoActividad',
            'competencia:id,nombreMateria,idMateriaPadre,codigo',
        ];
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
     * @return array{path: ?string, urlAdicional: ?string, urlExterna: ?string}
     */
    private function resolverRecurso(MaterialCompetencia $m): array
    {
        $path = $m->urlDocumento;
        $urlAdicional = $m->urlAdicional;
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
        ];
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
        $filename = time().'_'.preg_replace('/[^a-zA-Z0-9._-]/', '_', $file->getClientOriginalName());

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
