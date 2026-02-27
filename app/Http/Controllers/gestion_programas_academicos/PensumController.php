<?php

namespace App\Http\Controllers\gestion_programas_academicos;


use App\Http\Controllers\Controller;
use App\Models\NivelEducativo;
use App\Models\TipoFormacion;
use App\Models\EstadoPrograma;
use App\Models\Programa;
use App\Util\KeyUtil;
use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;


class PensumController extends Controller
{
    public function getMetadata()
    {
        return response()->json([
            'status' => 'success',
            'data' => [
                'niveles_educativos' => NivelEducativo::where('activo', 1)
                    ->select('id', 'nombreNivel as nombre')
                    ->get(),

                'tipos_formacion'    => TipoFormacion::where('activo', 1)
                    ->select('id', 'nombreTipoFormacion as nombre')
                    ->get(),
                'estados_programa'   => EstadoPrograma::all()
            ]
        ], 200);
    }

    public function storeNivelEducativo(Request $request)
    {
        try {
            $request->validate([
                'nombreNivel' => 'required|string|max:100'
            ]);

            $nombreNivel = strtoupper(trim($request->nombreNivel));

            // Buscar si ya existe
            $nivelExistente = NivelEducativo::where('nombreNivel', $nombreNivel)->first();

            if ($nivelExistente) {
                // Si existe, retornar el existente
                return response()->json([
                    'status' => 'success',
                    'message' => 'Nivel educativo ya existe',
                    'data' => [
                        'id' => $nivelExistente->id,
                        'nombre' => $nivelExistente->nombreNivel
                    ]
                ], 200);
            }

            // Si no existe, crear nuevo
            $nivelEducativo = NivelEducativo::create([
                'nombreNivel' => $nombreNivel,
                'activo' => true
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Nivel educativo creado correctamente',
                'data' => [
                    'id' => $nivelEducativo->id,
                    'nombre' => $nivelEducativo->nombreNivel
                ]
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al crear nivel educativo',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function index()
    {
        try {
            $programas = Programa::with(['nivel', 'tipoFormacion', 'estado', 'grados'])->get();
            return response()->json([
                'status' => 'success',
                'data' => $programas
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    public function indexByRegional(int $idRegional)
    {
        try {
            $programas = Programa::with(['nivel', 'tipoFormacion', 'estado'])
                ->get();

            return response()->json([
                'status' => 'success',
                'data' => $programas
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function indexByRed(Request $request, int $idRed)
    {
        try {
            // Verificamos si se pasó el centro
            if (!$request->filled('centro')) {
                return response()->json([
                    'status' => 'success',
                    'data' => [] // Retorna vacío si no hay centro
                ]);
            }

            $idCentro = $request->centro;

            $programas = Programa::with(['nivel', 'tipoFormacion', 'estado', 'red'])
                ->where('idRed', $idRed)
                ->withCount([
                    'fichasActivas as fichas_activas_count' => function ($q) use ($idCentro) {
                        $q->whereHas('aperturarPrograma.sede', function ($sub) use ($idCentro) {
                            $sub->where('idCentroFormacion', $idCentro);
                        });
                    }
                ])
                ->orderByDesc('fichas_activas_count')
                ->orderBy('nombrePrograma')
                ->get();

            return response()->json([
                'status' => 'success',
                'data' => $programas
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }


    public function store(Request $request)
    {
        $request->validate([
            'nombrePrograma'   => 'required|string|max:255',
            'codigoPrograma'   => 'required|string|max:255',
            'descripcionPrograma' => 'nullable|string',
            'idNivelEducativo' => 'required|exists:nivelEducativo,id',
            'idTipoFormacion'  => 'required|exists:tipoFormacion,id',
            'idEstadoPrograma' => 'required|exists:estadoPrograma,id',
            'documento'        => 'nullable|file|mimes:pdf|max:5120',
            'idRed' => 'required|integer|exists:red,id',
        ]);

        try {
            $nuevoPrograma = Programa::create([
                'nombrePrograma'      => $request->nombrePrograma,
                'codigoPrograma'      => $request->codigoPrograma,
                'descripcionPrograma' => $request->descripcionPrograma,
                'documento'           => null,
                'idNivelEducativo'    => $request->idNivelEducativo,
                'idTipoFormacion'     => $request->idTipoFormacion,
                'idEstadoPrograma'    => $request->idEstadoPrograma,
                'idCompany'           => KeyUtil::idCompany(),
                'idRed' => $request->idRed,
            ]);

            $sanitize = function ($string) {
                $string = trim($string); // quita espacios al inicio y fin
                $string = preg_replace('/\s+/', '_', $string); // espacios → _
                return preg_replace(
                    '/[^a-zA-Z0-9áéíóúÁÉÍÓÚüÜñÑ\-_]/u',
                    '',
                    $string
                );
            };

            $programaName = $sanitize($request->nombrePrograma);


            // Crear estructura de carpetas para el programa
            $carpetasPrograma = [
                "documentos/programas/{$programaName}/documentosGenerales/actividades",
                "documentos/programas/{$programaName}/fichas",
            ];

            foreach ($carpetasPrograma as $carpeta) {
                if (!Storage::disk('public')->exists($carpeta)) {
                    Storage::disk('public')->makeDirectory($carpeta);
                }
            }
            // Crear la carpeta para la ficha usando el código

            if ($request->hasFile('documento')) {

                $ruta = "programas/documentos";

                if (!Storage::disk('public')->exists($ruta)) {
                    Storage::disk('public')->makeDirectory($ruta);
                }

                $nombreArchivo = Str::limit($programaName, 80) . '_' . $nuevoPrograma->id . '.pdf';

                $request->file('documento')->storeAs(
                    $ruta,
                    $nombreArchivo,
                    'public'
                );

                $nuevoPrograma->update([
                    'documento' => '/storage/' . $ruta . '/' . $nombreArchivo
                ]);
            }


            $nuevoPrograma->load(['nivel', 'tipoFormacion', 'estado']);

            return response()->json([
                'status' => 'success',
                'message' => '¡Programa guardado con éxito!',
                'data' => $nuevoPrograma
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al guardar el programa: ' . $e->getMessage()
            ], 500);
        }
    }


    public function update(Request $request, $id)
    {
        $request->validate([
            'nombrePrograma'   => 'required|string|max:255',
            'codigoPrograma'   => 'required|string|max:255',
            'descripcionPrograma' => 'nullable|string',
            'idNivelEducativo' => 'required|exists:nivelEducativo,id',
            'idTipoFormacion'  => 'required|exists:tipoFormacion,id',
            'idEstadoPrograma' => 'required|exists:estadoPrograma,id',
            'documento'        => 'nullable|file|mimes:pdf|max:5120',
            'idRed' => 'nullable|integer'
        ]);

        try {
            $programa = Programa::findOrFail($id);

            $data = [
                'nombrePrograma'      => $request->nombrePrograma,
                'codigoPrograma'      => $request->codigoPrograma,
                'descripcionPrograma' => $request->descripcionPrograma,
                'idNivelEducativo'    => $request->idNivelEducativo,
                'idTipoFormacion'     => $request->idTipoFormacion,
                'idEstadoPrograma'    => $request->idEstadoPrograma,
                'idRed' => $request->idRed
            ];

            if ($request->hasFile('documento')) {
                $sanitize = function ($string) {
                    $string = trim($string); // quita espacios al inicio y fin
                    $string = preg_replace('/\s+/', '_', $string); // espacios → _
                    return preg_replace(
                        '/[^a-zA-Z0-9áéíóúÁÉÍÓÚüÜñÑ\-_]/u',
                        '',
                        $string
                    );
                };

                $programaName = $sanitize($request->nombrePrograma);

                // Eliminar documento anterior si existe
                if ($programa->documento) {
                    $rutaVieja = preg_replace('#^/?(storage/)?#', '', $programa->documento);
                    Storage::disk('public')->delete($rutaVieja);
                }

                $ruta = "programas/documentos";

                if (!Storage::disk('public')->exists($ruta)) {
                    Storage::disk('public')->makeDirectory($ruta);
                }

                $nombreArchivo = Str::limit($programaName, 80) . '_' . $programa->id . '.pdf';

                $request->file('documento')->storeAs(
                    $ruta,
                    $nombreArchivo,
                    'public'
                );

                $data['documento'] = '/storage/' . $ruta . '/' . $nombreArchivo;
            }

            $programa->update($data);

            $programa->load(['nivel', 'tipoFormacion', 'estado']);

            return response()->json([
                'status' => 'success',
                'message' => '¡Programa actualizado con éxito!',
                'data' => $programa
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al actualizar: ' . $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $programa = Programa::findOrFail($id);

            // construir nombre carpeta
            $sanitize = function ($string) {
                $string = trim($string);
                $string = preg_replace('/\s+/', '_', $string);
                return preg_replace('/[^a-zA-Z0-9áéíóúÁÉÍÓÚüÜñÑ\-_]/u', '', $string);
            };

            $programaName = $sanitize($programa->nombrePrograma);
            $rutaPrograma = "documentos/programas/{$programaName}";

            // 1️⃣ borrar BD primero
            $programa->delete();

            // 2️⃣ borrar archivos después
            Storage::disk('public')->deleteDirectory($rutaPrograma);

            return response()->json([
                'status' => 'success',
                'message' => 'Programa eliminado correctamente'
            ], 200);
        } catch (QueryException $e) {
            if ($e->errorInfo[1] == 1451) {
                return response()->json([
                    'status' => 'warning',
                    'message' => 'No se puede eliminar el programa porque tiene información asociada'
                ], 409);
            }

            return response()->json([
                'status' => 'error',
                'message' => 'Error al eliminar el programa'
            ], 500);
        }
    }


    /**
     * Para gestión de programas académicos
     * Carga la información de la apertura de un programa
     */
    public function getInformacionApertura($idPrograma)
    {
        try {

            $asignacion = \App\Models\AsignacionPeriodoPrograma::with(['programa', 'periodo', 'sede'])
                ->where('idPrograma', $idPrograma)
                ->firstOrFail();

            return response()->json([
                'status' => 'success',
                'data' => $asignacion
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'No se encontró una apertura configurada para este programa (ID: ' . $idPrograma . ')'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error interno: ' . $e->getMessage()
            ], 500);
        }
    }


    public function updateInformacionApertura(Request $request, $id)
    {
        try {
            $asignacion = \App\Models\AsignacionPeriodoPrograma::findOrFail($id);

            $asignacion->update($request->only([
                'observacion',
                'estado',
                'pension',
                'diaCobro',
                'fechaInicialClases',
                'fechaFinalClases',
                'fechaInicialInscripciones',
                'fechaFinalInscripciones',
                'fechaInicialMatriculas',
                'fechaFinalMatriculas',
                'valorPension',
                'diasMoraMatricula',
                'diasMoraPension',
                'porcentajeMoraMatricula',
                'porcentajeMoraPension'
            ]));

            return response()->json([
                'status' => 'success',
                'message' => 'Configuración de asignación actualizada con éxito',
                'data' => $asignacion
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al actualizar: ' . $e->getMessage()
            ], 500);
        }
    }
}
