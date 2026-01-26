<?php

namespace App\Http\Controllers\gestion_programas_academicos;

use App\Http\Controllers\Controller;
use App\Models\FichaPrograma;
use App\Models\Proceso;
use App\Models\Programa;
use App\Models\TipoDocumento;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Documentos por ficha (FichaPrograma).
 * - Listar/asignar tipos de documento.
 */
class DocumentoFichaController extends Controller
{
    /**
     * Lista todos los tipos de documento con flag "asignado" y "activo" para la ficha.
     * Usado en el modal "Asignar tipos de documentos" (toggles + GUARDAR).
     *
     * GET /ficha/{idGradoPrograma}/tipos-documento
     */
    public function tiposPorFicha(int $idGradoPrograma): JsonResponse
    {
        $ficha = FichaPrograma::findOrFail($idGradoPrograma);
        $asignaciones = $ficha->tiposDocumento()
            ->get()
            ->mapWithKeys(function ($tipo) {
                return [$tipo->id => (bool) $tipo->pivot->activo];
            })
            ->toArray();
        $asignados = array_keys($asignaciones);

        $tipos = TipoDocumento::select('id', 'tituloDocumento', 'descripcion')
            ->orderBy('tituloDocumento')
            ->get()
            ->map(function ($t) use ($asignados, $asignaciones) {
                $asignado = in_array($t->id, $asignados, true);
                return [
                    'id' => $t->id,
                    'codigo' => $t->id,
                    'tipoDocumento' => $t->tituloDocumento,
                    'descripcion' => $t->descripcion ?? '',
                    'asignado' => $asignado,
                    'activo' => $asignado ? ($asignaciones[$t->id] ?? true) : false,
                ];
            });

        return response()->json([
            'status' => 'success',
            'data' => [
                'tipos' => $tipos,
                'asignados' => $asignados,
            ],
        ], 200);
    }

    /**
     * Guardar asignación de tipos de documento a la ficha (toggles).
     * Body: { "ids": [1, 2, 3] } — solo los que quedan activos.
     *
     * PUT /ficha/{idGradoPrograma}/tipos-documento
     */
    public function guardarAsignacion(Request $request, int $idGradoPrograma): JsonResponse
    {
        $request->validate([
            'ids' => 'array',
            'ids.*' => 'integer|exists:tipoDocumento,id',
        ]);

        $ficha = FichaPrograma::findOrFail($idGradoPrograma);
        $ids = $request->input('ids', []);
        
        // Sincronizar con activo=true por defecto
        $syncData = [];
        foreach ($ids as $id) {
            $syncData[$id] = ['activo' => true];
        }
        $ficha->tiposDocumento()->sync($syncData);

        return response()->json([
            'status' => 'success',
            'message' => 'Asignación guardada correctamente.',
            'data' => [
                'asignados' => $ficha->tiposDocumento()->get()->pluck('id')->toArray(),
            ],
        ], 200);
    }

    /**
     * Documentos que pide esta ficha (para visualizar).
     *
     * GET /ficha/{idGradoPrograma}/documentos-pide
     */
    public function documentosPide(int $idGradoPrograma): JsonResponse
    {
        $ficha = FichaPrograma::with('tiposDocumento')->findOrFail($idGradoPrograma);
        $tipos = $ficha->tiposDocumento()
            ->orderBy('tituloDocumento')
            ->get()
            ->map(fn ($t) => [
                'id' => $t->id,
                'codigo' => $t->id,
                'nombre' => $t->tituloDocumento,
                'descripcion' => $t->descripcion ?? '',
            ]);

        return response()->json([
            'status' => 'success',
            'data' => [
                'ficha_id' => $ficha->id,
                'documentos' => $tipos,
            ],
        ], 200);
    }

    /**
     * Tipos de documento para un proceso (toggles + GUARDAR).
     * GET /proceso/{idProceso}/tipos-documento
     */
    public function tiposPorProceso(int $idProceso): JsonResponse
    {
        $proceso = Proceso::findOrFail($idProceso);
        $asignados = $proceso->tiposDocumento()->get()->pluck('id')->toArray();

        $tipos = TipoDocumento::select('id', 'tituloDocumento', 'descripcion')
            ->orderBy('tituloDocumento')
            ->get()
            ->map(function ($t) use ($asignados) {
                return [
                    'id' => $t->id,
                    'codigo' => $t->id,
                    'tipoDocumento' => $t->tituloDocumento,
                    'descripcion' => $t->descripcion ?? '',
                    'asignado' => in_array($t->id, $asignados, true),
                ];
            });

        return response()->json([
            'status' => 'success',
            'data' => [
                'tipos' => $tipos,
                'asignados' => $asignados,
            ],
        ], 200);
    }

    /**
     * Guardar asignación de tipos de documento al proceso.
     * PUT /proceso/{idProceso}/tipos-documento
     * Body: { "ids": [1, 2, 3] }
     */
    public function guardarAsignacionProceso(Request $request, int $idProceso): JsonResponse
    {
        $request->validate([
            'ids' => 'array',
            'ids.*' => 'integer|exists:tipoDocumento,id',
        ]);

        $proceso = Proceso::findOrFail($idProceso);
        $ids = $request->input('ids', []);
        $proceso->tiposDocumento()->sync($ids);

        return response()->json([
            'status' => 'success',
            'message' => 'Asignación guardada correctamente.',
            'data' => ['asignados' => $proceso->tiposDocumento()->get()->pluck('id')->toArray()],
        ], 200);
    }

    /**
     * Documentos que pide este proceso (para Ver).
     * GET /proceso/{idProceso}/documentos-pide
     */
    public function documentosPideProceso(int $idProceso): JsonResponse
    {
        $proceso = Proceso::with('tiposDocumento')->findOrFail($idProceso);
        $tipos = $proceso->tiposDocumento()
            ->orderBy('tituloDocumento')
            ->get()
            ->map(fn ($t) => [
                'id' => $t->id,
                'codigo' => $t->id,
                'nombre' => $t->tituloDocumento,
                'descripcion' => $t->descripcion ?? '',
            ]);

        return response()->json([
            'status' => 'success',
            'data' => [
                'proceso_id' => $proceso->id,
                'documentos' => $tipos,
            ],
        ], 200);
    }

    /**
     * Lista todos los tipos de documento con estado activo/inactivo para el proceso.
     * Usado para mostrar documentos dentro del proceso con toggles de activación.
     *
     * GET /proceso/{idProceso}/documentos-autorizados
     */
    public function documentosAutorizadosProceso(int $idProceso): JsonResponse
    {
        $proceso = Proceso::findOrFail($idProceso);
        
        // Obtener todos los tipos de documento asignados al proceso
        $asignados = $proceso->tiposDocumento()
            ->orderBy('tituloDocumento')
            ->get()
            ->map(function ($tipo) {
                return [
                    'id' => $tipo->id,
                    'codigo' => $tipo->id,
                    'tipoDocumento' => $tipo->tituloDocumento,
                    'descripcion' => $tipo->descripcion ?? '',
                    'activo' => true, // Por defecto activo si está asignado
                ];
            });

        return response()->json([
            'status' => 'success',
            'data' => [
                'proceso' => [
                    'id' => $proceso->id,
                    'nombreProceso' => $proceso->nombreProceso,
                ],
                'documentos' => $asignados,
            ],
        ], 200);
    }

    /**
     * Eliminar documento de un proceso (quitar asignación).
     *
     * DELETE /proceso/{idProceso}/documento/{idTipoDocumento}
     */
    public function eliminarDocumentoProceso(int $idProceso, int $idTipoDocumento): JsonResponse
    {
        $proceso = Proceso::findOrFail($idProceso);
        $tipoDocumento = TipoDocumento::findOrFail($idTipoDocumento);

        // Quitar la asignación
        $proceso->tiposDocumento()->detach($idTipoDocumento);

        return response()->json([
            'status' => 'success',
            'message' => 'Documento eliminado del proceso correctamente.',
        ], 200);
    }

    /**
     * Lista simple de todos los tipos de documento (para crear/agregar).
     * GET /tipos-documento-listado
     */
    public function listadoTipos(): JsonResponse
    {
        $tipos = TipoDocumento::select('id', 'tituloDocumento', 'descripcion')
            ->orderBy('tituloDocumento')
            ->get()
            ->map(fn ($t) => [
                'id' => $t->id,
                'codigo' => $t->id,
                'tipoDocumento' => $t->tituloDocumento,
                'descripcion' => $t->descripcion ?? '',
            ]);

        return response()->json([
            'status' => 'success',
            'data' => $tipos,
        ], 200);
    }

    /**
     * Fichas (GradoPrograma) de un programa. Para malla curricular.
     *
     * GET /programa/{idPrograma}/fichas
     */
    public function fichasPorPrograma(int $idPrograma): JsonResponse
    {
        $fichas = FichaPrograma::where('idPrograma', $idPrograma)
            ->with(['grado', 'programa'])
            ->get()
            ->map(fn ($f) => [
                'id' => $f->id,
                'idPrograma' => $f->idPrograma,
                'idGrado' => $f->idGrado,
                'cupos' => $f->cupos,
                'grado' => $f->grado ? [
                    'id' => $f->grado->id,
                    'nombreGrado' => $f->grado->nombreGrado,
                    'numeroGrado' => $f->grado->numeroGrado,
                ] : null,
                'programa' => $f->programa ? [
                    'id' => $f->programa->id,
                    'nombrePrograma' => $f->programa->nombrePrograma,
                    'codigoPrograma' => $f->programa->codigoPrograma,
                ] : null,
            ]);

        return response()->json([
            'status' => 'success',
            'data' => $fichas,
        ], 200);
    }

    /**
     * Lista todos los tipos de documento con estado activo/inactivo para el programa.
     * Usado en "Documentos – [PROGRAM]" para mostrar toggles de autorización.
     *
     * GET /programa/{idPrograma}/documentos-autorizados
     */
    public function documentosAutorizados(int $idPrograma): JsonResponse
    {
        $programa = Programa::findOrFail($idPrograma);
        
        // Obtener todos los tipos de documento
        $todosTipos = TipoDocumento::select('id', 'tituloDocumento', 'descripcion')
            ->orderBy('tituloDocumento')
            ->get();

        // Obtener asignaciones del programa (con estado activo)
        $asignaciones = DB::table('asignacion_programa_tipo_documento')
            ->where('idPrograma', $idPrograma)
            ->pluck('activo', 'idTipoDocumento')
            ->toArray();

        $documentos = $todosTipos->map(function ($tipo) use ($asignaciones) {
            $activo = isset($asignaciones[$tipo->id]) ? (bool) $asignaciones[$tipo->id] : false;
            return [
                'id' => $tipo->id,
                'codigo' => $tipo->id,
                'tipoDocumento' => $tipo->tituloDocumento,
                'descripcion' => $tipo->descripcion ?? '',
                'activo' => $activo,
            ];
        });

        return response()->json([
            'status' => 'success',
            'data' => [
                'programa' => [
                    'id' => $programa->id,
                    'nombrePrograma' => $programa->nombrePrograma,
                    'codigoPrograma' => $programa->codigoPrograma,
                ],
                'documentos' => $documentos,
            ],
        ], 200);
    }

    /**
     * Activar o inactivar un documento para el programa.
     *
     * PUT /programa/{idPrograma}/documento/{idTipoDocumento}/autorizar
     * Body: { "activo": true|false }
     */
    public function autorizarDocumento(Request $request, int $idPrograma, int $idTipoDocumento): JsonResponse
    {
        $request->validate([
            'activo' => 'required|boolean',
        ]);

        $programa = Programa::findOrFail($idPrograma);
        $tipoDocumento = TipoDocumento::findOrFail($idTipoDocumento);
        $activo = $request->input('activo');

        // Usar updateOrInsert para crear o actualizar
        DB::table('asignacion_programa_tipo_documento')
            ->updateOrInsert(
                [
                    'idPrograma' => $idPrograma,
                    'idTipoDocumento' => $idTipoDocumento,
                ],
                [
                    'activo' => $activo,
                    'updated_at' => now(),
                    'created_at' => DB::raw('COALESCE(created_at, NOW())'),
                ]
            );

        return response()->json([
            'status' => 'success',
            'message' => $activo 
                ? 'Documento autorizado correctamente.' 
                : 'Documento desautorizado correctamente.',
            'data' => [
                'idTipoDocumento' => $idTipoDocumento,
                'activo' => $activo,
            ],
        ], 200);
    }

    /**
     * Lista todos los tipos de documento con estado activo/inactivo para la ficha.
     * Usado en "Fichas del programa" para mostrar toggles de autorización.
     *
     * GET /ficha/{idGradoPrograma}/documentos-autorizados
     */
    public function documentosAutorizadosFicha(int $idGradoPrograma): JsonResponse
    {
        $ficha = FichaPrograma::findOrFail($idGradoPrograma);
        
        // Obtener todos los tipos de documento
        $todosTipos = TipoDocumento::select('id', 'tituloDocumento', 'descripcion')
            ->orderBy('tituloDocumento')
            ->get();

        // Obtener asignaciones de la ficha (con estado activo)
        $asignaciones = DB::table('asignacion_ficha_tipo_documento')
            ->where('idGradoPrograma', $idGradoPrograma)
            ->pluck('activo', 'idTipoDocumento')
            ->toArray();

        $documentos = $todosTipos->map(function ($tipo) use ($asignaciones) {
            $activo = isset($asignaciones[$tipo->id]) ? (bool) $asignaciones[$tipo->id] : false;
            return [
                'id' => $tipo->id,
                'codigo' => $tipo->id,
                'tipoDocumento' => $tipo->tituloDocumento,
                'descripcion' => $tipo->descripcion ?? '',
                'activo' => $activo,
            ];
        });

        return response()->json([
            'status' => 'success',
            'data' => [
                'ficha' => [
                    'id' => $ficha->id,
                    'grado' => $ficha->grado ? [
                        'id' => $ficha->grado->id,
                        'nombreGrado' => $ficha->grado->nombreGrado,
                    ] : null,
                ],
                'documentos' => $documentos,
            ],
        ], 200);
    }

    /**
     * Activar o inactivar un documento para la ficha.
     *
     * PUT /ficha/{idGradoPrograma}/documento/{idTipoDocumento}/autorizar
     * Body: { "activo": true|false }
     */
    public function autorizarDocumentoFicha(Request $request, int $idGradoPrograma, int $idTipoDocumento): JsonResponse
    {
        $request->validate([
            'activo' => 'required|boolean',
        ]);

        $ficha = FichaPrograma::findOrFail($idGradoPrograma);
        $tipoDocumento = TipoDocumento::findOrFail($idTipoDocumento);
        $activo = $request->input('activo');

        // Usar updateOrInsert para crear o actualizar
        DB::table('asignacion_ficha_tipo_documento')
            ->updateOrInsert(
                [
                    'idGradoPrograma' => $idGradoPrograma,
                    'idTipoDocumento' => $idTipoDocumento,
                ],
                [
                    'activo' => $activo,
                    'updated_at' => now(),
                    'created_at' => DB::raw('COALESCE(created_at, NOW())'),
                ]
            );

        return response()->json([
            'status' => 'success',
            'message' => $activo 
                ? 'Documento autorizado correctamente.' 
                : 'Documento desautorizado correctamente.',
            'data' => [
                'idTipoDocumento' => $idTipoDocumento,
                'activo' => $activo,
            ],
        ], 200);
    }
}
