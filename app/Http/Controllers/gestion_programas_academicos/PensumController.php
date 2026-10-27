<?php

namespace App\Http\Controllers\gestion_programas_academicos;


use App\Http\Controllers\Controller;
use App\Models\NivelEducativo;
use App\Models\TipoFormacion;
use App\Models\EstadoPrograma;
use App\Models\NivelPrograma;
use App\Models\Programa;
use App\Util\KeyUtil;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;


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
                'estados_programa'   => EstadoPrograma::all(),
                'sedes'              => DB::table('sedes')
                    ->where('idEmpresa', KeyUtil::idCompany())
                    ->select('id', 'nombre')
                    ->orderBy('nombre')
                    ->get(),
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
            $programas = Programa::with(['nivel', 'tipoFormacion', 'estado', 'red', 'sede', 'nivelesPrograma'])
                ->where('idRed', $idRed)
                ->where('idCompany', KeyUtil::idCompany());

            if ($request->filled('centro')) {
                $idCentro = $request->centro;
                $programas->withCount([
                    'aperturarProgramas as aperturas_activas_count' => function ($q) use ($idCentro) {
                        $q->where('fechaFinalPlanMejoramiento', '>=', Carbon::today()->toDateString())
                            ->whereHas('sede', function ($sub) use ($idCentro) {
                                $sub->where('idCentroFormacion', $idCentro);
                            });
                    },
                ]);
            }

            $programas = $programas->orderBy('nombrePrograma')->get();

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
        if ($request->input('clasePrograma') === 'ACADEMICO') {
            return $this->guardarAcademico($request);
        }

        $this->normalizarNiveles($request);

        $request->validate([
            'nombrePrograma'   => 'required|string|max:255',
            'codigoPrograma'   => 'required|string|max:255',
            'descripcionPrograma' => 'nullable|string',
            'idNivelEducativo' => 'required|exists:nivelEducativo,id',
            'idTipoFormacion'  => 'required|exists:tipoFormacion,id',
            'idEstadoPrograma' => 'required|exists:estadoPrograma,id',
            'idSede' => ['required', Rule::exists('sedes', 'id')->where(fn ($q) => $q->where('idEmpresa', KeyUtil::idCompany()))],
            'duracion' => 'required|integer|min:1',
            'unidadDuracion' => 'required|in:ANOS,SEMESTRES,CICLOS,MESES,HORAS',
            'vigenciaDesde' => 'required|date',
            'niveles' => 'required|array|min:1',
            'niveles.*.nombre' => 'required|string|max:120',
            'niveles.*.tipoNivel' => 'required|in:GRADO,SEMESTRE,CICLO,MODULO',
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
                'idSede' => $request->idSede,
                'duracion' => $request->duracion,
                'unidadDuracion' => $request->unidadDuracion,
                'vigenciaDesde' => $request->vigenciaDesde,
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


            $this->guardarNiveles($nuevoPrograma->id, $request->input('niveles', []));

            $nuevoPrograma->load(['nivel', 'tipoFormacion', 'estado', 'red', 'sede', 'nivelesPrograma']);

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
        if ($request->input('clasePrograma') === 'ACADEMICO') {
            return $this->guardarAcademico($request, (int) $id);
        }

        $this->normalizarNiveles($request);

        $request->validate([
            'nombrePrograma'   => 'required|string|max:255',
            'codigoPrograma'   => 'required|string|max:255',
            'descripcionPrograma' => 'nullable|string',
            'idNivelEducativo' => 'required|exists:nivelEducativo,id',
            'idTipoFormacion'  => 'required|exists:tipoFormacion,id',
            'idEstadoPrograma' => 'required|exists:estadoPrograma,id',
            'idSede' => ['required', Rule::exists('sedes', 'id')->where(fn ($q) => $q->where('idEmpresa', KeyUtil::idCompany()))],
            'duracion' => 'required|integer|min:1',
            'unidadDuracion' => 'required|in:ANOS,SEMESTRES,CICLOS,MESES,HORAS',
            'vigenciaDesde' => 'required|date',
            'niveles' => 'required|array|min:1',
            'niveles.*.nombre' => 'required|string|max:120',
            'niveles.*.tipoNivel' => 'required|in:GRADO,SEMESTRE,CICLO,MODULO',
            'documento'        => 'nullable|file|mimes:pdf|max:5120',
            'idRed' => 'nullable|integer'
        ]);

        try {
            $programa = Programa::where('idCompany', KeyUtil::idCompany())->findOrFail($id);

            $data = [
                'nombrePrograma'      => $request->nombrePrograma,
                'codigoPrograma'      => $request->codigoPrograma,
                'descripcionPrograma' => $request->descripcionPrograma,
                'idNivelEducativo'    => $request->idNivelEducativo,
                'idTipoFormacion'     => $request->idTipoFormacion,
                'idEstadoPrograma'    => $request->idEstadoPrograma,
                'idRed' => $request->idRed,
                'idSede' => $request->idSede,
                'duracion' => $request->duracion,
                'unidadDuracion' => $request->unidadDuracion,
                'vigenciaDesde' => $request->vigenciaDesde,
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
            $this->guardarNiveles($programa->id, $request->input('niveles', []));

            $programa->load(['nivel', 'tipoFormacion', 'estado', 'red', 'sede', 'nivelesPrograma']);

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
            $programa = Programa::where('idCompany', KeyUtil::idCompany())->findOrFail($id);

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

    private function guardarAcademico(Request $request, ?int $id = null)
    {
        if (is_string($request->input('niveles'))) {
            $request->merge(['niveles' => json_decode($request->input('niveles'), true) ?: []]);
        }

        $unicoNombre = Rule::unique('programa', 'nombrePrograma')->where(fn ($q) => $q->where('clasePrograma', 'ACADEMICO'));
        $unicoCodigo = Rule::unique('programa', 'codigoPrograma')->where(fn ($q) => $q->where('clasePrograma', 'ACADEMICO'));
        if ($id) {
            $unicoNombre = $unicoNombre->ignore($id);
            $unicoCodigo = $unicoCodigo->ignore($id);
        }

        $validated = $request->validate([
            'nombrePrograma' => ['required', 'string', 'max:255', $unicoNombre],
            'codigoPrograma' => ['required', 'string', 'max:255', $unicoCodigo],
            'idNivelEducativo' => 'required|exists:nivelEducativo,id',
            'idTipoFormacion' => 'required|exists:tipoFormacion,id',
            'idEstadoPrograma' => 'required|exists:estadoPrograma,id',
            'idSede' => ['required', Rule::exists('sedes', 'id')->where(fn ($q) => $q->where('idEmpresa', KeyUtil::idCompany()))],
            'duracion' => 'required|integer|min:1',
            'unidadDuracion' => 'required|in:ANOS,SEMESTRES,CICLOS,MESES,HORAS',
            'vigenciaDesde' => 'required|date',
            'idSistemaEvaluacion' => 'nullable|exists:sistemaEvaluacion,id',
            'idEscalaCalificacion' => 'nullable|exists:escalaCalificacion,id',
            'idRed' => 'required|integer|exists:red,id',
            'descripcionPrograma' => 'nullable|string',
            'documento' => 'nullable|file|mimes:pdf|max:5120',
            'niveles' => 'required|array|min:1',
            'niveles.*.nombre' => 'required|string|max:120',
            'niveles.*.tipoNivel' => 'required|in:GRADO,SEMESTRE,CICLO,MODULO',
        ], [
            'nombrePrograma.unique' => 'El nombre del programa académico ya existe.',
            'codigoPrograma.unique' => 'El código del programa académico ya existe.',
            'niveles.required' => 'Agrega al menos un nivel del programa.',
        ]);

        try {
            $data = [
                'nombrePrograma' => $validated['nombrePrograma'],
                'codigoPrograma' => $validated['codigoPrograma'],
                'descripcionPrograma' => $validated['descripcionPrograma'] ?? null,
                'idNivelEducativo' => $validated['idNivelEducativo'],
                'idTipoFormacion' => $validated['idTipoFormacion'],
                'idEstadoPrograma' => $validated['idEstadoPrograma'],
                'idSede' => $validated['idSede'],
                'duracion' => $validated['duracion'],
                'unidadDuracion' => $validated['unidadDuracion'],
                'vigenciaDesde' => $validated['vigenciaDesde'],
                'idSistemaEvaluacion' => $validated['idSistemaEvaluacion'],
                'idEscalaCalificacion' => $validated['idEscalaCalificacion'],
                'idRed' => $validated['idRed'],
                'clasePrograma' => 'ACADEMICO',
                'idCompany' => KeyUtil::idCompany(),
            ];

            $programa = $id ? Programa::where('idCompany', KeyUtil::idCompany())->findOrFail($id) : new Programa();
            if (!$id) {
                $data['documento'] = null;
            }
            $programa->fill($data);
            $programa->save();

            NivelPrograma::where('idPrograma', $programa->id)->delete();
            foreach (array_values($request->input('niveles', [])) as $orden => $nivel) {
                NivelPrograma::create([
                    'idPrograma' => $programa->id,
                    'nombre' => $nivel['nombre'],
                    'tipoNivel' => $nivel['tipoNivel'],
                    'orden' => $orden + 1,
                ]);
            }

            if ($request->hasFile('documento')) {
                $nombre = preg_replace('/[^a-zA-Z0-9áéíóúÁÉÍÓÚüÜñÑ\-_]/u', '', preg_replace('/\s+/', '_', trim($programa->nombrePrograma)));
                $ruta = 'programas/documentos';
                if (!Storage::disk('public')->exists($ruta)) {
                    Storage::disk('public')->makeDirectory($ruta);
                }
                $archivo = Str::limit($nombre, 80) . '_' . $programa->id . '.pdf';
                $request->file('documento')->storeAs($ruta, $archivo, 'public');
                $programa->update(['documento' => '/storage/' . $ruta . '/' . $archivo]);
            }

            $programa->load(['nivel', 'tipoFormacion', 'estado', 'red', 'sede', 'nivelesPrograma']);

            return response()->json([
                'status' => 'success',
                'message' => $id ? 'Programa académico actualizado.' : 'Programa académico guardado.',
                'data' => $programa,
            ], $id ? 200 : 201);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al guardar el programa académico: ' . $e->getMessage(),
            ], 500);
        }
    }

    private function normalizarNiveles(Request $request): void
    {
        if (is_string($request->input('niveles'))) {
            $request->merge(['niveles' => json_decode($request->input('niveles'), true) ?: []]);
        }
    }

    private function guardarNiveles(int $idPrograma, array $niveles): void
    {
        NivelPrograma::where('idPrograma', $idPrograma)->delete();
        foreach (array_values($niveles) as $orden => $nivel) {
            NivelPrograma::create([
                'idPrograma' => $idPrograma,
                'nombre' => $nivel['nombre'],
                'tipoNivel' => $nivel['tipoNivel'],
                'orden' => $orden + 1,
            ]);
        }
    }
}
