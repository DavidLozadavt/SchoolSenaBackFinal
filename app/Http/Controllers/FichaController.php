<?php

namespace App\Http\Controllers;

use App\Models\Ficha;
use App\Models\AperturarPrograma;
use App\Models\Company;
use App\Models\Contract;
use App\Models\HorarioMateria;
use App\Models\Programa;
use App\Models\Sede;
use App\Models\Status;
use App\Models\SesionMateria;
use App\Enums\EstadoSesionMateria;
use App\Util\KeyUtil;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class FichaController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            // Apertura programa
            'observacion' => 'nullable|string|max:1000',
            'idPeriodo' => 'required|exists:periodo,id',
            'idPrograma' => 'required|exists:programa,id',
            'estado' => 'nullable|string',
            'idSede' => 'required|exists:sedes,id',
            'idInfraestructura' => 'nullable|exists:infraestructura,id',
            'tipoCalificacion' => 'nullable|in:NUMERICO,DESEMPEÑO',

            // apertura Fechas:
            'fechaInicialClases' => 'required|date',
            'fechaFinalClases' => 'required|date|after_or_equal:fechaInicialClases',
            'fechaInicialPlanMejoramiento' => 'required|date',
            'fechaFinalPlanMejoramiento' => 'required|date|after_or_equal:fechaInicialPlanMejoramiento',
            'fechaInicialInscripciones' => 'required|date',
            'fechaFinalInscripciones' => 'required|date|after_or_equal:fechaInicialInscripciones',
            'fechaInicialMatriculas' => 'required|date',
            'fechaFinalMatriculas' => 'required|date|after_or_equal:fechaInicialMatriculas',

            // Ficha
            'idJornada' => 'required|exists:jornadas,id',
            'idRegional' => 'required|exists:empresa,id',
            'codigo' => 'required|string|unique:ficha,codigo',
            'porcentajeEjecucion' => 'nullable|numeric|min:1|max:100',
            'documento' => 'nullable|file|mimes:pdf|max:5120',
        ]);

        DB::beginTransaction();

        try {
            $apertura = AperturarPrograma::create([
                'observacion' => $validated['observacion'] ?? null,
                'idPeriodo' => $validated['idPeriodo'],
                'idPrograma' => $validated['idPrograma'],
                'estado' => $validated['estado'] ?? 'EN CURSO',
                'idSede' => $validated['idSede'],
                'tipoCalificacion' => $validated['tipoCalificacion'] ?? 'NUMERICO',

                'fechaInicialClases' => $validated['fechaInicialClases'],
                'fechaFinalClases' => $validated['fechaFinalClases'],
                'fechaInicialPlanMejoramiento' => $validated['fechaInicialPlanMejoramiento'],
                'fechaFinalPlanMejoramiento' => $validated['fechaFinalPlanMejoramiento'],
                'fechaInicialInscripciones' => $validated['fechaInicialInscripciones'],
                'fechaFinalInscripciones' => $validated['fechaFinalInscripciones'],
                'fechaInicialMatriculas' => $validated['fechaInicialMatriculas'],
                'fechaFinalMatriculas' => $validated['fechaFinalMatriculas'],
            ]);

            // Crear la carpeta para la ficha usando el código
            $sanitize = function ($string) {
                $string = trim($string); // quita espacios al inicio y fin
                $string = preg_replace('/\s+/', '_', $string); // espacios → _
                return preg_replace(
                    '/[^a-zA-Z0-9áéíóúÁÉÍÓÚüÜñÑ\-_]/u',
                    '',
                    $string
                );
            };

            $sede = Sede::findOrFail($validated['idSede']);
            $programa = Programa::findOrFail($validated['idPrograma']);
            $codigoFicha = $validated['codigo'];
            //Limpiar los nombres:
            $sedeName = $sanitize($sede->nombre);
            $programaName = $sanitize($programa->nombrePrograma);

            $carpetaFicha = "documentos/programas/{$programaName}/fichas/{$sedeName}/{$codigoFicha}/evidenciasFicha";

            // Crear la carpeta en el disco public si no existe:

            Storage::disk('public')->makeDirectory($carpetaFicha);

            $rutaDocumento = null;

            if ($request->hasFile('documento')) {
                $rutaDocumento = '/storage/' . $request->file('documento')
                    ->store("documentos/programas/{$programaName}/fichas/{$sedeName}/{$codigoFicha}/documento", 'public');
            }

            $ficha = Ficha::create([
                'idJornada' => $validated['idJornada'],
                'idAsignacion' => $apertura->id,
                'codigo' => $validated['codigo'],
                'idSede' => $validated['idSede'],
                'documento' => $rutaDocumento,
                'idInfraestructura' => $validated['idInfraestructura'] ?? null,
                'idRegional' => $validated['idRegional'],
                'porcentajeEjecucion' => $validated['porcentajeEjecucion'] ?? 100,
            ]);


            DB::commit();

            return response()->json([
                'message' => 'Ficha creada correctamente',
                'data' => [
                    'ficha' => $ficha,
                    'apertura' => $apertura
                ]
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Error al crear la ficha',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    public function index()
    {
        $fichas = Ficha::with([
            'jornada:id,nombreJornada',
            'asignacion:id,observacion,idPeriodo,idPrograma,estado,idSede,fechaInicialClases,fechaFinalClases,fechaInicialInscripciones,fechaFinalInscripciones,fechaInicialMatriculas,fechaFinalMatriculas,fechaInicialPlanMejoramiento,fechaFinalPlanMejoramiento,tipoCalificacion',
            'infraestructura',
            'asignacion.programa:id,nombrePrograma',
            'sede:id,nombre',
            'regional:id,razonSocial',
            'instructorLider:id,idpersona',
            'instructorLider.persona', // Cargar persona completa para que funcione rutaFotoUrl
        ])->get();

        return response()->json($fichas);
    }
    public function fichasPorRegional(
        Request $request,
        int $idRegional
    ): JsonResponse {

        $estadosPermitidos = [
            'ACTIVO',
            'INACTIVO',
            'OCULTO',
            'PENDIENTE',
            'RECHAZADO',
            'APROBADO',
            'CANCELADO',
            'REPROBADO',
            'CERRADO',
            'ACEPTADO',
            'LEIDO',
            'EN ESPERA',
            'INSCRIPCION',
            'MATRICULADO',
            'ABIERTO',
            'EN CURSO',
            'POR ACTUALIZAR',
            'CURSANDO',
            'ENTREVISTA',
            'SIN ENTREVISTA',
            'JUSTIFICADO',
        ];

        $estado = $request->query('estado', 'EN CURSO'); // default

        if (!in_array($estado, $estadosPermitidos, true)) {
            return response()->json([
                'message' => 'Estado no permitido'
            ], 422);
        }

        $fichas = Ficha::query()
            ->where('idRegional', $idRegional)
            ->whereHas('aperturaPrograma', function ($query) use ($estado) {
                $query->where('estado', $estado);
            })
            ->with([
                'regional:id,razonSocial',
                'sede:id,nombre',
                'aperturaPrograma:id,estado,idPrograma'
            ])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'estado' => $estado,
            'idRegional' => $idRegional,
            'total' => $fichas->count(),
            'data' => $fichas
        ]);
    }
    public function fichasPorPrograma(int $idPrograma, int $idCentro): JsonResponse
    {
        $fichas = Ficha::query()
            ->whereHas('asignacion', function ($query) use ($idPrograma) {
                $query->where('idPrograma', $idPrograma);
            })
            ->whereHas('sede', function ($sede) use ($idCentro) {
                $sede->where('idCentroFormacion', $idCentro);
            })
            ->with([
                'jornada:id,nombreJornada',
                'sede:id,nombre,idCentroFormacion',
                'regional:id,razonSocial',
                'asignacion:id,estado,fechaInicialClases,fechaFinalClases,idPrograma',
                'asignacion.programa:id,nombrePrograma',
                'asignacion.programa.grados', //Ya puedo capturar en idgrado
                'instructorLider:id,idpersona', // Especificar campos para que Laravel resuelva correctamente la relación
                'instructorLider.persona', // Luego cargar persona completa para que funcione rutaFotoUrl y todos los campos
            ])
            ->orderBy('created_at', 'desc')
            ->get();

        // Forzar carga manual de instructorLider si el eager loading falló
        $idsInstructores = $fichas->pluck('idInstructorLider')->filter()->unique();
        if ($idsInstructores->isNotEmpty()) {
            $contracts = Contract::with('persona')
                ->whereIn('id', $idsInstructores)
                ->get()
                ->keyBy('id');

            foreach ($fichas as $ficha) {
                if ($ficha->idInstructorLider && isset($contracts[$ficha->idInstructorLider])) {
                    $ficha->setRelation('instructorLider', $contracts[$ficha->idInstructorLider]);
                }
            }
        }

        // Forzar serialización explícita para asegurar que las relaciones se incluyan
        $fichasArray = $fichas->map(function ($ficha) {
            $fichaArray = $ficha->toArray();
            // Asegurar que instructorLider esté en el array
            if ($ficha->instructorLider) {
                $fichaArray['instructorLider'] = $ficha->instructorLider->toArray();
                if ($ficha->instructorLider->persona) {
                    $fichaArray['instructorLider']['persona'] = $ficha->instructorLider->persona->toArray();
                }
            }
            return $fichaArray;
        })->toArray();

        return response()->json([
            'idPrograma' => $idPrograma,
            'total' => $fichas->count(),
            'data' => $fichasArray
        ]);
    }

    /**
     * Filtra programas por regional y sede
     * Recibe idRegional e idSede en el request body
     * Incluye conteo real de fichas por programa en la sede seleccionada
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function filtrar(Request $request): JsonResponse
    {
        $idRegional = $request->input('idRegional');
        $idSede = $request->input('idSede');

        $query = Programa::with('nivel', 'tipoFormacion', 'estado');

        // Si se proporciona idSede, filtrar programas que tienen aperturas en esa sede
        if ($idSede) {
            $query->whereHas('aperturarProgramas', function ($q) use ($idSede) {
                $q->where('idSede', $idSede);
            });
        }
        // Si solo se proporciona idRegional, filtrar por sedes de esa regional
        elseif ($idRegional) {
            $query->whereHas('aperturarProgramas', function ($q) use ($idRegional) {
                $q->whereHas('sede', function ($q2) use ($idRegional) {
                    $q2->where('idEmpresa', $idRegional);
                });
            });
        }

        $programas = $query->orderBy('nombrePrograma', 'asc')->get();

        // Agregar conteo real de fichas para cada programa y filtrar solo los que tienen fichas
        $programasConDatos = $programas->map(function ($programa) use ($idSede) {
            // Contar fichas del programa en la sede específica
            $cantidadFichas = 0;

            if ($idSede) {
                // Contar fichas que pertenecen a aperturas de este programa en esta sede
                $cantidadFichas = Ficha::whereHas('asignacion', function ($q) use ($programa, $idSede) {
                    $q->where('idPrograma', $programa->id)
                        ->where('idSede', $idSede);
                })->count();
            } else {
                // Si no hay sede específica, contar todas las fichas del programa
                $cantidadFichas = Ficha::whereHas('asignacion', function ($q) use ($programa) {
                    $q->where('idPrograma', $programa->id);
                })->count();
            }

            // Agregar el conteo de fichas al programa
            $programa->cantidadFichas = $cantidadFichas;

            return $programa;
        })->filter(function ($programa) {
            // Filtrar solo programas que tienen al menos 1 ficha
            return $programa->cantidadFichas > 0;
        })->values(); // Reindexar el array después del filtro

        return response()->json([
            'status' => 'success',
            'data' => $programasConDatos
        ]);
    }

    /**
     * Obtiene los instructores disponibles para una ficha específica
     * Solo muestra instructores que tienen el programa de la ficha asignado en su contrato
     */
    public function getInstructoresDisponiblesPorFicha($idFicha): JsonResponse
    {
        try {
            $ficha = Ficha::with('asignacion.programa')->findOrFail($idFicha);

            if (!$ficha->asignacion || !$ficha->asignacion->idPrograma) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'La ficha no tiene un programa asignado',
                    'data' => []
                ], 422);
            }

            $idPrograma = $ficha->asignacion->idPrograma;

            \Log::info('Buscando instructores para programa ID: ' . $idPrograma);
            \Log::info('ID Empresa: ' . KeyUtil::idCompany());

            // Primero verificamos si hay contratos con este programa
            $contratosConPrograma = DB::table('asignacion_contrato_programa')
                ->where('idPrograma', $idPrograma)
                ->pluck('idContrato')
                ->toArray();

            \Log::info('Contratos con programa ' . $idPrograma . ': ' . count($contratosConPrograma));
            \Log::info('IDs de contratos: ' . json_encode($contratosConPrograma));

            // Usar método directo con whereIn para mayor confiabilidad
            if (!empty($contratosConPrograma)) {
                $instructores = Contract::with([
                    'persona', // Cargar persona completa para que funcione el accessor rutaFotoUrl
                    'nivelEducativo:id,nombreNivel',
                    'areasConocimiento:id,nombreAreaConocimiento'
                ])
                    ->whereIn('id', $contratosConPrograma)
                    ->where('idEstado', Status::ID_ACTIVE)
                    ->where('idempresa', KeyUtil::idCompany())
                    ->get();
            } else {
                // Si no hay contratos con el programa, retornar array vacío
                $instructores = collect([]);
            }

            \Log::info('Instructores encontrados para programa ' . $idPrograma . ': ' . $instructores->count());

            // Log adicional para debug
            if ($instructores->count() === 0) {
                // Verificar contratos activos de la empresa
                $totalContratosActivos = Contract::where('idEstado', Status::ID_ACTIVE)
                    ->where('idempresa', KeyUtil::idCompany())
                    ->count();
                \Log::info('Total contratos activos de la empresa: ' . $totalContratosActivos);

                // Verificar si hay algún contrato con programas asignados
                $contratosConProgramas = DB::table('asignacion_contrato_programa')
                    ->join('contrato', 'asignacion_contrato_programa.idContrato', '=', 'contrato.id')
                    ->where('contrato.idEstado', Status::ID_ACTIVE)
                    ->where('contrato.idempresa', KeyUtil::idCompany())
                    ->count();
                \Log::info('Contratos activos con programas asignados: ' . $contratosConProgramas);
            }

            return response()->json([
                'status' => 'success',
                'data' => $instructores,
                'programa' => [
                    'id' => $ficha->asignacion->programa->id,
                    'nombre' => $ficha->asignacion->programa->nombrePrograma
                ]
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Ficha no encontrada',
                'data' => []
            ], 404);
        } catch (\Exception $e) {
            \Log::error('Error en getInstructoresDisponiblesPorFicha: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Error al obtener instructores',
                'data' => []
            ], 500);
        }
    }

    /**
     * Asigna un instructor líder a una ficha
     */
    public function asignarInstructorLider(Request $request, $idFicha): JsonResponse
    {
        try {
            $validated = $request->validate([
                'idInstructorLider' => 'required|exists:contrato,id'
            ]);

            $ficha = Ficha::with('asignacion.programa')->findOrFail($idFicha);

            if (!$ficha->asignacion || !$ficha->asignacion->idPrograma) {
                return response()->json([
                    'message' => 'La ficha no tiene un programa asignado'
                ], 422);
            }

            $idPrograma = $ficha->asignacion->idPrograma;
            $idInstructorLider = $validated['idInstructorLider'];

            // Verificar que el instructor tenga el programa asignado
            $instructor = Contract::whereHas('programas', function ($query) use ($idPrograma) {
                $query->where('programa.id', $idPrograma);
            })
                ->where('id', $idInstructorLider)
                ->where('idEstado', Status::ID_ACTIVE)
                ->first();

            if (!$instructor) {
                return response()->json([
                    'message' => 'El instructor seleccionado no tiene el programa de la ficha asignado en su contrato'
                ], 422);
            }

            // Actualizar la ficha
            $ficha->idInstructorLider = $idInstructorLider;
            $ficha->save();

            // Cargar relaciones para la respuesta
            $ficha->load([
                'instructorLider:id,idpersona',
                'instructorLider.persona' // Cargar persona completa para que funcione rutaFotoUrl
            ]);

            return response()->json([
                'message' => 'Instructor líder asignado correctamente',
                'data' => $ficha
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al asignar instructor líder',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function validarCodigo($codigo)
    {
        $existe = Ficha::where('codigo', $codigo)->exists();

        return response()->json([
            'codigo' => $codigo,
            'existe' => $existe
        ]);
    }
    public function show($id): JsonResponse
    {
        try {
            // Buscar la ficha con todas sus relaciones
            $ficha = Ficha::with([
                'jornada',
                'asignacion' => function ($query) {
                    $query->with([
                        'periodo',
                        'programa',
                        'sede'
                    ]);
                },
                'sede',
                'infraestructura',
                'regional'
            ])->findOrFail($id);

            // Obtener la apertura relacionada
            $apertura = AperturarPrograma::findOrFail($ficha->idAsignacion);

            return response()->json([
                'message' => 'Ficha encontrada',
                'data' => [
                    'ficha' => $ficha,
                    'apertura' => $apertura
                ]
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Ficha no encontrada',
                'error' => 'No existe una ficha con el ID proporcionado'
            ], 404);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Error al obtener la ficha',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    public function update(Request $request, $id): JsonResponse
    {
        $validated = $request->validate([
            // Apertura programa
            'observacion' => 'nullable|string|max:1000',
            'idPeriodo' => 'required|exists:periodo,id',
            'idPrograma' => 'required|exists:programa,id',
            'estado' => 'nullable|string',
            'idSede' => 'required|exists:sedes,id',
            'idInfraestructura' => 'nullable|exists:infraestructura,id',
            'tipoCalificacion' => 'nullable|in:NUMERICO,DESEMPEÑO',

            // Apertura Fechas
            'fechaInicialClases' => 'required|date',
            'fechaFinalClases' => 'required|date|after_or_equal:fechaInicialClases',
            'fechaInicialPlanMejoramiento' => 'required|date',
            'fechaFinalPlanMejoramiento' => 'required|date|after_or_equal:fechaInicialPlanMejoramiento',
            'fechaInicialInscripciones' => 'required|date',
            'fechaFinalInscripciones' => 'required|date|after_or_equal:fechaInicialInscripciones',
            'fechaInicialMatriculas' => 'required|date',
            'fechaFinalMatriculas' => 'required|date|after_or_equal:fechaInicialMatriculas',

            // Ficha
            'idJornada' => 'required|exists:jornadas,id',
            'idRegional' => 'required|exists:empresa,id',
            'porcentajeEjecucion' => 'nullable|numeric|min:1|max:100',
            'codigo' => [
                'required',
                'string',
                // Validar que el código sea único excepto para esta ficha
                Rule::unique('ficha', 'codigo')->ignore($id)
            ],
            'documento' => 'nullable|file|mimes:pdf|max:5120', // 5MB
        ]);

        DB::beginTransaction();

        try {
            // Buscar la ficha
            $ficha = Ficha::findOrFail($id);

            // Buscar la apertura relacionada
            $apertura = AperturarPrograma::findOrFail($ficha->idAsignacion);


            // Guardar valores antiguos (ANTES de actualizar)
            $oldCodigo = $ficha->codigo;
            $oldSede = $ficha->idSede;
            $oldPrograma = $apertura->idPrograma;

            // Actualizar la apertura
            $apertura->update([
                'observacion' => $validated['observacion'] ?? null,
                'idPeriodo' => $validated['idPeriodo'],
                'idPrograma' => $validated['idPrograma'],
                'estado' => $validated['estado'] ?? 'EN CURSO',
                'idSede' => $validated['idSede'],
                'tipoCalificacion' => $validated['tipoCalificacion'] ?? 'NUMERICO',

                'fechaInicialClases' => $validated['fechaInicialClases'],
                'fechaFinalClases' => $validated['fechaFinalClases'],
                'fechaInicialPlanMejoramiento' => $validated['fechaInicialPlanMejoramiento'],
                'fechaFinalPlanMejoramiento' => $validated['fechaFinalPlanMejoramiento'],
                'fechaInicialInscripciones' => $validated['fechaInicialInscripciones'],
                'fechaFinalInscripciones' => $validated['fechaFinalInscripciones'],
                'fechaInicialMatriculas' => $validated['fechaInicialMatriculas'],
                'fechaFinalMatriculas' => $validated['fechaFinalMatriculas'],
            ]);

            $rutaDocumento = $ficha->documento;

            if ($request->hasFile('documento')) {

                // eliminar el anterior
                if ($ficha->documento) {
                    $rutaAnterior = str_replace('/storage/', '', $ficha->documento);
                    Storage::disk('public')->delete($rutaAnterior);
                }

                // volver a construir la ruta como en el store
                $sanitize = function ($string) {
                    $string = trim($string);
                    $string = preg_replace('/\s+/', '_', $string);
                    return preg_replace(
                        '/[^a-zA-Z0-9áéíóúÁÉÍÓÚüÜñÑ\-_]/u',
                        '',
                        $string
                    );
                };

                $sede = Sede::findOrFail($validated['idSede']);
                $programa = Programa::findOrFail($validated['idPrograma']);

                $sedeName = $sanitize($sede->nombre);
                $programaName = $sanitize($programa->nombrePrograma);
                $codigoFicha = $validated['codigo'];

                $ruta = "documentos/programas/{$programaName}/fichas/{$sedeName}/{$codigoFicha}/documento";

                $nombreArchivo = "ficha_{$codigoFicha}.pdf";

                $request->file('documento')->storeAs(
                    $ruta,
                    $nombreArchivo,
                    'public'
                );

                $rutaDocumento = "/storage/{$ruta}/{$nombreArchivo}";
            }


            // 🔁 MOVER DOCUMENTO SI CAMBIA PROGRAMA / SEDE / CÓDIGO
            // (solo si NO suben un nuevo PDF)
            if (
                !$request->hasFile('documento') &&
                $ficha->documento &&
                (
                    $validated['codigo'] !== $oldCodigo ||
                    $validated['idSede'] !== $oldSede ||
                    $validated['idPrograma'] !== $oldPrograma
                )
            ) {
                // función sanitize
                $sanitize = function ($string) {
                    $string = trim($string);
                    $string = preg_replace('/\s+/', '_', $string);
                    return preg_replace(
                        '/[^a-zA-Z0-9áéíóúÁÉÍÓÚüÜñÑ\-_]/u',
                        '',
                        $string
                    );
                };

                // 🔹 RUTA VIEJA
                $rutaVieja = str_replace('/storage/', '', $ficha->documento);

                // 🔹 NUEVA RUTA
                $sedeNueva = Sede::findOrFail($validated['idSede']);
                $programaNuevo = Programa::findOrFail($validated['idPrograma']);

                $sedeName = $sanitize($sedeNueva->nombre);
                $programaName = $sanitize($programaNuevo->nombrePrograma);
                $codigoFicha = $validated['codigo'];

                $nuevaCarpeta = "documentos/programas/{$programaName}/fichas/{$sedeName}/{$codigoFicha}/documento";

                // crear carpeta si no existe
                Storage::disk('public')->makeDirectory($nuevaCarpeta);

                // ⚠️ conservar nombre original del archivo
                $nombreArchivo = basename($rutaVieja);

                $rutaNueva = "{$nuevaCarpeta}/{$nombreArchivo}";

                // 🚚 mover archivo
                if (Storage::disk('public')->exists($rutaVieja)) {
                    Storage::disk('public')->move($rutaVieja, $rutaNueva);

                    // actualizar ruta final
                    $rutaDocumento = "/storage/{$rutaNueva}";
                }
            }





            // Actualizar la ficha
            $ficha->update([
                'idJornada' => $validated['idJornada'],
                'codigo' => $validated['codigo'],
                'idSede' => $validated['idSede'],
                'idInfraestructura' => $validated['idInfraestructura'] ?? null,
                'idRegional' => $validated['idRegional'],
                'porcentajeEjecucion' => $validated['porcentajeEjecucion'] ?? 100,
                'documento' => $rutaDocumento,
            ]);



            DB::commit();

            // Recargar las relaciones
            $ficha->load([
                'jornada',
                'asignacion',
                'sede',
                'infraestructura',
                'regional'
            ]);

            return response()->json([
                'message' => 'Ficha actualizada correctamente',
                'data' => [
                    'ficha' => $ficha,
                    'apertura' => $apertura
                ]
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Ficha no encontrada',
                'error' => 'No existe una ficha con el ID proporcionado'
            ], 404);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al actualizar la ficha',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    /**
     * Elimina una ficha solo si no está siendo utilizada en otras partes
     */
    public function destroy($id): JsonResponse
    {
        DB::beginTransaction();

        try {
            // Buscar la ficha
            $ficha = Ficha::findOrFail($id);

            // Array para almacenar las relaciones que impiden eliminar
            $relacionesActivas = [];

            // 1. Verificar si tiene instructor líder asignado
            if ($ficha->idInstructorLider) {
                $relacionesActivas[] = 'instructor líder asignado';
            }

            // 2. Verificar si tiene aprendices voceros o suplentes
            if ($ficha->idAprendizVocero) {
                $relacionesActivas[] = 'aprendiz vocero asignado';
            }

            if ($ficha->idAprendizSuplente) {
                $relacionesActivas[] = 'aprendiz suplente asignado';
            }

            // 3. Verificar si tiene horarios de materias asignados
            $cantidadHorarios = DB::table('horarioMateria')
                ->where('idFicha', $id)
                ->count();

            if ($cantidadHorarios > 0) {
                $relacionesActivas[] = "{$cantidadHorarios} horario(s) de materia(s)";
            }

            // Si hay relaciones activas, no permitir eliminar
            if (!empty($relacionesActivas)) {
                DB::rollBack();

                $mensaje = 'No se puede eliminar la ficha porque tiene las siguientes relaciones activas: '
                    . implode(', ', $relacionesActivas);

                return response()->json([
                    'message' => $mensaje,
                    'relaciones' => $relacionesActivas
                ], 422);
            }

            // Si llegamos aquí, no hay relaciones que impidan la eliminación
            // Guardar el ID de la apertura antes de eliminar la ficha
            $idApertura = $ficha->idAsignacion;

            // Eliminar documento físico si existe
            if ($ficha->documento) {
                $rutaDocumento = str_replace('/storage/', '', $ficha->documento);
                if (Storage::disk('public')->exists($rutaDocumento)) {
                    Storage::disk('public')->delete($rutaDocumento);
                }
            }

            // Eliminar la ficha (que tiene FK hacia apertura)
            $ficha->delete();

            // Eliminar la apertura asociada
            if ($idApertura) {
                AperturarPrograma::where('id', $idApertura)->delete();
            }

            DB::commit();

            return response()->json([
                'message' => 'Ficha eliminada correctamente'
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Ficha no encontrada'
            ], 404);
        } catch (\Throwable $e) {
            DB::rollBack();

            // Log del error para debugging
            Log::error('Error al eliminar ficha', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Verificar si es error de foreign key constraint
            if (
                str_contains($e->getMessage(), 'foreign key constraint') ||
                str_contains($e->getMessage(), 'Cannot delete or update a parent row')
            ) {
                return response()->json([
                    'message' => 'No se puede eliminar la ficha porque está siendo utilizada en otros registros del sistema',
                    'error' => 'Violación de restricción de integridad referencial'
                ], 422);
            }

            return response()->json([
                'message' => 'Error al eliminar la ficha',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener clases asignadas de un instructor
     * Devuelve materias individuales con sus horarios específicos
     * Si no se proporciona idInstructor, se obtiene del usuario autenticado
     *
     * La respuesta se deduplica por slot lógico (ficha, día, franja, jornada); la regla debe coincidir con
     * `schoolSenaFrontFinal/src/utils/clasesAsignadasLogica.ts` y con `claveLogicaClaseAsignadaInstructor` aquí.
     *
     * @param Request $request
     * @param int|null $idInstructor ID del contrato (instructor) - opcional
     * @return JsonResponse
     */
    public function clasesAsignadasInstructor(Request $request, ?int $idInstructor = null): JsonResponse
    {
        try {
            // Si no se proporciona el ID del instructor, obtenerlo del usuario autenticado
            if (!$idInstructor) {
                try {
                    $contratoActivo = KeyUtil::lastContractActive();
                    if ($contratoActivo && $contratoActivo->id) {
                        $idInstructor = $contratoActivo->id;
                    } else {
                        return response()->json([
                            'message' => 'No se encontró un contrato activo para el usuario autenticado',
                            'data' => [],
                            'total' => 0
                        ], 200);
                    }
                } catch (\Throwable $e) {
                    return response()->json([
                        'message' => 'Error al obtener el contrato del usuario autenticado',
                        'error' => $e->getMessage(),
                        'data' => [],
                        'total' => 0
                    ], 400);
                }
            }

            $tieneAsignacionSesion = Schema::hasTable('asignacionSesion');

            $selectFechas = $tieneAsignacionSesion
                ? [
                    DB::raw("COALESCE(CASE WHEN asig.tipoAsignacion = 'REEMPLAZO' THEN asig.fechaInicio END, hm.fechaInicial) as fechaInicial"),
                    DB::raw("COALESCE(CASE WHEN asig.tipoAsignacion = 'REEMPLAZO' THEN asig.fechaFin END, hm.fechaFinal) as fechaFinal"),
                ]
                : [
                    DB::raw('hm.fechaInicial as fechaInicial'),
                    DB::raw('hm.fechaFinal as fechaFinal'),
                ];

            $hasInfraestructura = Schema::hasColumn('horarioMateria', 'idInfraestructura');
            $selectAula = $hasInfraestructura
                ? ['inf.nombreInfraestructura as aula_nombre']
                : [DB::raw('NULL as aula_nombre')];

            // Grano: una fila por horarioMateria.id (PK). Sin GROUP BY amplio que duplique hm.id.
            $qb = DB::table('horarioMateria as hm')
                ->select(array_merge([
                    'f.id as ficha_id',
                    'f.codigo as ficha_codigo',
                    'p.nombrePrograma as programa_nombre',
                    'm.nombreMateria as materia_nombre',
                ], $this->selectCompetenciaRapIdPadreMateriaClase(), [
                    'j.nombreJornada as jornada_nombre',
                    'j.nombreJornada as jornada_tipo',
                    'd.dia as dia_semana',
                    'ap.fechaInicialClases as periodo_fecha_inicial_clases',
                    'ap.fechaFinalClases as periodo_fecha_final_clases',
                ], $this->selectHoraTimeHorarioMateria(), $selectFechas, [
                    'hm.idDia',
                    'c.id as contrato_id',
                    DB::raw("CONCAT(per.nombre1, ' ', per.apellido1) as instructor_nombre"),
                    'gp.id as idGradoPrograma',
                    'g.nombreGrado as grado_nombre',
                    'hm.id as idHorarioMateria',
                    'hm.idGradoMateria as idGradoMateria',
                    'gm.idMateria as idMateria',
                ], $selectAula));
            $qb = $qb
                ->join('ficha as f', 'hm.idFicha', '=', 'f.id')
                ->join('jornadas as j', 'f.idJornada', '=', 'j.id')
                ->join('aperturarprograma as ap', 'f.idAsignacion', '=', 'ap.id')
                ->join('programa as p', 'ap.idPrograma', '=', 'p.id')
                ->join('gradoMateria as gm', 'hm.idGradoMateria', '=', 'gm.id')
                ->join('materia as m', 'gm.idMateria', '=', 'm.id');
            $qb = $this->aplicarJoinsMateriaCompetenciaRapSeguimiento($qb);
            $qb = $qb
                ->leftJoin('gradoPrograma as gp', 'gm.idGradoPrograma', '=', 'gp.id')
                ->leftJoin('grado as g', 'gp.idGrado', '=', 'g.id')
                ->leftJoin('dia as d', 'hm.idDia', '=', 'd.id');

            if ($hasInfraestructura) {
                $qb = $qb->leftJoin('infraestructura as inf', 'hm.idInfraestructura', '=', 'inf.id');
            }

            if ($tieneAsignacionSesion) {
                $qb->leftJoin('asignacionSesion as asig', function ($join) use ($idInstructor) {
                    $join->on('hm.id', '=', 'asig.idHorarioMateria')
                        ->where('asig.idContrato', '=', $idInstructor);
                });
            }

            $clases = $qb
                ->join('contrato as c', function ($join) use ($idInstructor) {
                    $join->on('c.id', '=', DB::raw((int) $idInstructor));
                })
                ->join('persona as per', 'c.idpersona', '=', 'per.id')
                ->where(function ($query) use ($idInstructor, $tieneAsignacionSesion) {
                    $query->where('hm.idContrato', $idInstructor);
                    if ($tieneAsignacionSesion) {
                        $query->orWhereNotNull('asig.id');
                    }
                })
                ->whereNotNull('hm.idDia')
                ->whereNotNull('hm.horaInicial')
                ->whereNotNull('hm.horaFinal')
                ->orderBy('hm.fechaInicial', 'asc')
                ->orderBy('hm.horaInicial', 'asc')
                ->get();

            // Una fila por PK horarioMateria (entero; evita colisión string/int al agrupar).
            $clases = collect($clases)
                ->keyBy(fn($row) => (int) ($row->idHorarioMateria ?? 0))
                ->values();

            // Procesar resultados para calcular estado, total_sesiones y sesiones_restantes
            $clases = $clases->map(function ($clase) use ($idInstructor) {
                // Calcular estado usando el método helper
                $estado = $this->calcularEstadoHorario(
                    $clase->fechaInicial,
                    $clase->fechaFinal,
                    $clase->horaInicial,
                    $clase->horaFinal,
                    $clase->idDia
                );

                // Calcular total de sesiones basado en fechaInicial y fechaFinal
                $totalSesiones = $this->calcularTotalSesiones(
                    $clase->fechaInicial,
                    $clase->fechaFinal,
                    $clase->idDia
                );

                // Sincronizar sesiones completadas automáticamente (crear registros en BD cuando comienza la clase)
                $this->sincronizarSesionesCompletadas(
                    $clase->idHorarioMateria,
                    $clase->fechaInicial,
                    $clase->fechaFinal,
                    $clase->horaInicial,
                    $clase->horaFinal,
                    $clase->idDia
                );

                // Calcular sesiones dadas basándose en registros reales de sesionMateria
                $sesionesDadas = $this->calcularSesionesDadas(
                    $clase->idHorarioMateria,
                    $clase->fechaInicial,
                    $clase->fechaFinal,
                    $clase->horaFinal,
                    $clase->idDia
                );

                // Obtener sesiones completadas con fechas específicas
                $sesionesCompletadas = $this->obtenerSesionesCompletadas($clase->idHorarioMateria);

                // Calcular sesiones restantes
                $sesionesRestantes = max(0, $totalSesiones - $sesionesDadas);

                // Agregar los campos calculados al objeto
                $clase->estado = $estado;
                $clase->total_sesiones = $totalSesiones;
                $clase->sesiones_dadas = $sesionesDadas;
                $clase->sesiones_restantes = $sesionesRestantes;
                $clase->sesiones_completadas = $sesionesCompletadas;

                try {
                    $idHm = (int) ($clase->idHorarioMateria ?? 0);
                    if ($idHm > 0) {
                        foreach ($this->resolverModalidadRap($idHm, (int) $idInstructor) as $k => $v) {
                            $clase->{$k} = $v;
                        }
                    }
                } catch (\Throwable $e) {
                    // silenciar error de modalidad para no romper el listado
                }

                return $clase;
            });

            $clases = $this->dedupeClasesAsignadasInstructorPorClaveLogica($clases)->values();

            return response()->json([
                'message' => 'Clases asignadas obtenidas correctamente',
                'data' => $clases,
                'total' => $clases->count()
            ], 200);
        } catch (\Throwable $e) {
            Log::error('Error al obtener clases asignadas del instructor', [
                'idInstructor' => $idInstructor,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'message' => 'Error al obtener las clases asignadas',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Historial completo de sesiones dictadas por el instructor (Mis formaciones → Completado).
     * Incluye todas las filas en sesionMateria, aunque el RAP ya no tenga sesiones restantes en el horario.
     */
    public function historialSesionesInstructor(Request $request, ?int $idInstructor = null): JsonResponse
    {
        try {
            if (!$idInstructor) {
                try {
                    $contratoActivo = KeyUtil::lastContractActive();
                    if ($contratoActivo && $contratoActivo->id) {
                        $idInstructor = $contratoActivo->id;
                    } else {
                        return response()->json([
                            'message' => 'No se encontró un contrato activo para el usuario autenticado',
                            'data' => [],
                            'total' => 0,
                        ], 200);
                    }
                } catch (\Throwable $e) {
                    return response()->json([
                        'message' => 'Error al obtener el contrato del usuario autenticado',
                        'error' => $e->getMessage(),
                        'data' => [],
                        'total' => 0,
                    ], 400);
                }
            }

            $tieneAsignacionSesion = Schema::hasTable('asignacionSesion');
            $hasInfraestructura = Schema::hasColumn('horarioMateria', 'idInfraestructura');
            $selectAula = $hasInfraestructura
                ? ['inf.nombreInfraestructura as aula_nombre']
                : [DB::raw('NULL as aula_nombre')];

            // Alias `sesm`: `aplicarJoinsMateriaCompetenciaRapSeguimiento` usa `sm` para seguimientoMateria.
            $qb = DB::table('sesionMateria as sesm')
                ->join('horarioMateria as hm', 'sesm.idHorarioMateria', '=', 'hm.id')
                ->select(array_merge([
                    'sesm.id as sesion_id',
                    'sesm.numeroSesion as sesion_numeroSesion',
                    'sesm.fechaSesion as sesion_fechaSesion',
                    'sesm.estado as sesion_estado',
                    'sesm.observacion as sesion_observacion',
                    'f.id as ficha_id',
                    'f.codigo as ficha_codigo',
                    'p.nombrePrograma as programa_nombre',
                    'm.nombreMateria as materia_nombre',
                ], $this->selectCompetenciaRapIdPadreMateriaClase(), [
                    'j.nombreJornada as jornada_nombre',
                    'j.nombreJornada as jornada_tipo',
                    'd.dia as dia_semana',
                    'hm.horaInicial',
                    'hm.horaFinal',
                    'hm.fechaInicial',
                    'hm.fechaFinal',
                    'hm.idDia',
                    'hm.id as idHorarioMateria',
                    'hm.idGradoMateria',
                    'hm.idContrato as contrato_id',
                    'gm.idMateria as idMateria',
                    DB::raw("TRIM(CONCAT(COALESCE(per_hm.nombre1,''), ' ', COALESCE(per_hm.apellido1,''))) as instructor_nombre"),
                ], $selectAula))
                ->join('ficha as f', 'hm.idFicha', '=', 'f.id')
                ->join('jornadas as j', 'f.idJornada', '=', 'j.id')
                ->join('aperturarprograma as ap', 'f.idAsignacion', '=', 'ap.id')
                ->join('programa as p', 'ap.idPrograma', '=', 'p.id')
                ->join('gradoMateria as gm', 'hm.idGradoMateria', '=', 'gm.id')
                ->join('materia as m', 'gm.idMateria', '=', 'm.id');
            $qb = $this->aplicarJoinsMateriaCompetenciaRapSeguimiento($qb);
            $qb = $qb
                ->leftJoin('dia as d', 'hm.idDia', '=', 'd.id')
                ->leftJoin('contrato as c_hm', 'hm.idContrato', '=', 'c_hm.id')
                ->leftJoin('persona as per_hm', 'c_hm.idpersona', '=', 'per_hm.id');

            if ($hasInfraestructura) {
                $qb = $qb->leftJoin('infraestructura as inf', 'hm.idInfraestructura', '=', 'inf.id');
            }

            if ($tieneAsignacionSesion) {
                $qb->leftJoin('asignacionSesion as asig', function ($join) use ($idInstructor) {
                    $join->on('hm.id', '=', 'asig.idHorarioMateria')
                        ->where('asig.idContrato', '=', $idInstructor);
                });
            }

            $qb->whereNotNull('sesm.fechaSesion')
                ->where(function ($query) use ($idInstructor, $tieneAsignacionSesion) {
                    $query->where('hm.idContrato', $idInstructor);
                    if ($tieneAsignacionSesion) {
                        $query->orWhereNotNull('asig.id');
                    }
                });

            $rows = $qb
                ->orderByDesc('sesm.fechaSesion')
                ->orderByDesc('sesm.numeroSesion')
                ->get();

            $vistos = [];
            $data = [];
            foreach ($rows as $row) {
                $idHm = (int) ($row->idHorarioMateria ?? 0);
                $fechaYmd = Carbon::parse((string) $row->sesion_fechaSesion)->format('Y-m-d');
                $num = (int) ($row->sesion_numeroSesion ?? 0);
                $sid = (int) ($row->sesion_id ?? 0);
                $fichaId = (int) ($row->ficha_id ?? 0);
                $idDia = (int) ($row->idDia ?? 0);
                $claveLogica = $this->claveLogicaSesionHistorial(
                    $fichaId,
                    $idDia,
                    (string) ($row->horaInicial ?? ''),
                    (string) ($row->horaFinal ?? ''),
                    $fechaYmd,
                    $num
                );
                $key = "slot:{$claveLogica}";
                if (isset($vistos[$key])) {
                    continue;
                }
                $vistos[$key] = true;

                $fecha = Carbon::parse((string) $row->sesion_fechaSesion);
                $materiaNombre = (string) ($row->materia_nombre ?? '');
                $competenciaRaw = isset($row->competencia_nombre) ? trim((string) $row->competencia_nombre) : '';
                $competenciaNombre = $competenciaRaw !== '' ? $competenciaRaw : $materiaNombre;
                $rapRaw = isset($row->rap_nombre) ? trim((string) $row->rap_nombre) : '';
                $rapNombre = $rapRaw !== '' && strtolower($rapRaw) !== 'null' ? $rapRaw : null;

                $clasePayload = [
                    'ficha_id' => $fichaId,
                    'ficha_codigo' => (string) ($row->ficha_codigo ?? ''),
                    'programa_nombre' => (string) ($row->programa_nombre ?? ''),
                    'materia_nombre' => $materiaNombre,
                    'competencia_nombre' => $competenciaNombre,
                    'rap_nombre' => $rapNombre,
                    'jornada_nombre' => (string) ($row->jornada_nombre ?? ''),
                    'jornada_tipo' => (string) ($row->jornada_tipo ?? ''),
                    'dia_semana' => (string) ($row->dia_semana ?? ''),
                    'idDia' => $idDia,
                    'horaInicial' => (string) ($row->horaInicial ?? ''),
                    'horaFinal' => (string) ($row->horaFinal ?? ''),
                    'fechaInicial' => (string) ($row->fechaInicial ?? ''),
                    'fechaFinal' => $row->fechaFinal != null ? (string) $row->fechaFinal : null,
                    'idHorarioMateria' => $idHm,
                    'idGradoMateria' => (int) ($row->idGradoMateria ?? 0),
                    'idMateria' => (int) ($row->idMateria ?? 0),
                    'contrato_id' => (int) ($row->contrato_id ?? 0),
                    'instructor_nombre' => trim((string) ($row->instructor_nombre ?? '')),
                    'aula_nombre' => $row->aula_nombre ?? null,
                ];

                try {
                    foreach ($this->resolverModalidadRap($idHm, (int) $idInstructor, $fechaYmd) as $k => $v) {
                        $clasePayload[$k] = $v;
                    }
                } catch (\Throwable $e) {
                    // silenciar error de modalidad
                }

                $data[] = [
                    'sesion' => [
                        'id' => $sid,
                        'numeroSesion' => $num,
                        'fechaSesion' => $fechaYmd,
                        'fechaFormateada' => $fecha->locale('es')->isoFormat('dddd, D [de] MMMM [de] YYYY'),
                        'fechaCorta' => $fecha->format('d/m/Y'),
                        'estado' => (string) ($row->sesion_estado ?? ''),
                        'observacion' => $row->sesion_observacion,
                        'evaluador_nombre' => null,
                    ],
                    'clase' => $clasePayload,
                ];
            }

            return response()->json([
                'message' => 'Historial de sesiones obtenido correctamente',
                'data' => $data,
                'total' => count($data),
            ], 200);
        } catch (\Throwable $e) {
            Log::error('Error al obtener historial de sesiones del instructor', [
                'idInstructor' => $idInstructor,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Error al obtener el historial de sesiones',
                'error' => $e->getMessage(),
                'data' => [],
                'total' => 0,
            ], 500);
        }
    }

    /**
     * Obtener todas las clases con instructores asignados (sin filtro)
     * Devuelve materias individuales con sus horarios específicos
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function todasClasesAsignadas(Request $request): JsonResponse
    {
        try {
            $clases = DB::table('ficha as f')
                ->select([
                    'f.id as ficha_id',
                    'f.codigo as ficha_codigo',
                    'p.nombrePrograma as programa_nombre',
                    'm.nombreMateria as materia_nombre',
                    'j.nombreJornada as jornada_nombre',
                    'j.nombreJornada as jornada_tipo',
                    'd.dia as dia_semana',
                    'hm.horaInicial',
                    'hm.horaFinal',
                    'hm.fechaInicial as fechaInicial',
                    'hm.fechaFinal as fechaFinal',
                    'hm.idDia',
                    'c.id as contrato_id',
                    DB::raw("CONCAT(per.nombre1, ' ', per.apellido1) as instructor_nombre"),
                    'gp.id as idGradoPrograma',
                    'g.nombreGrado as grado_nombre',
                    'hm.id as idHorarioMateria',
                    'hm.idGradoMateria as idGradoMateria',
                    'gm.idMateria as idMateria'
                ])
                ->join('jornadas as j', 'f.idJornada', '=', 'j.id')
                ->join('aperturarprograma as ap', 'f.idAsignacion', '=', 'ap.id')
                ->join('programa as p', 'ap.idPrograma', '=', 'p.id')
                ->join('horarioMateria as hm', 'f.id', '=', 'hm.idFicha')
                ->join('gradoMateria as gm', 'hm.idGradoMateria', '=', 'gm.id')
                ->join('materia as m', 'gm.idMateria', '=', 'm.id')
                ->leftJoin('gradoPrograma as gp', 'gm.idGradoPrograma', '=', 'gp.id')
                ->leftJoin('grado as g', 'gp.idGrado', '=', 'g.id')
                ->leftJoin('dia as d', 'hm.idDia', '=', 'd.id')
                ->join('contrato as c', 'hm.idContrato', '=', 'c.id')
                ->join('persona as per', 'c.idpersona', '=', 'per.id')
                ->whereNotNull('c.id')
                ->whereNotNull('hm.idDia')
                ->whereNotNull('hm.horaInicial')
                ->whereNotNull('hm.horaFinal')
                ->get();

            // Procesar resultados para calcular estado, total_sesiones y sesiones_restantes
            $clases = $clases->map(function ($clase) {
                // Calcular estado usando el método helper
                $estado = $this->calcularEstadoHorario(
                    $clase->fechaInicial,
                    $clase->fechaFinal,
                    $clase->horaInicial,
                    $clase->horaFinal,
                    $clase->idDia
                );

                // Calcular total de sesiones basado en fechaInicial y fechaFinal
                $totalSesiones = $this->calcularTotalSesiones(
                    $clase->fechaInicial,
                    $clase->fechaFinal,
                    $clase->idDia
                );

                // Sincronizar sesiones completadas automáticamente (crear registros en BD cuando comienza la clase)
                $this->sincronizarSesionesCompletadas(
                    $clase->idHorarioMateria,
                    $clase->fechaInicial,
                    $clase->fechaFinal,
                    $clase->horaInicial,
                    $clase->horaFinal,
                    $clase->idDia
                );

                // Calcular sesiones dadas basándose en registros reales de sesionMateria
                $sesionesDadas = $this->calcularSesionesDadas(
                    $clase->idHorarioMateria,
                    $clase->fechaInicial,
                    $clase->fechaFinal,
                    $clase->horaFinal,
                    $clase->idDia
                );

                // Calcular sesiones restantes
                $sesionesRestantes = max(0, $totalSesiones - $sesionesDadas);

                // Agregar los campos calculados al objeto
                $clase->estado = $estado;
                $clase->total_sesiones = $totalSesiones;
                $clase->sesiones_dadas = $sesionesDadas;
                $clase->sesiones_restantes = $sesionesRestantes;

                return $clase;
            });

            return response()->json([
                'message' => 'Clases asignadas obtenidas correctamente',
                'data' => $clases,
                'total' => $clases->count()
            ], 200);
        } catch (\Throwable $e) {
            Log::error('Error al obtener todas las clases asignadas', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'message' => 'Error al obtener las clases asignadas',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener detalles de una clase por idHorarioMateria
     * 
     * @param Request $request
     * @param int $idHorarioMateria
     * @return JsonResponse
     */
    public function detalleClasePorHorario(Request $request, int $idHorarioMateria): JsonResponse
    {
        try {
            // Primero obtener los datos de la clase usando el idHorarioMateria
            // Consulta base (la que ya les devolvía datos). Sin GROUP BY: hm.id es PK → una fila;
            // ONLY_FULL_GROUP_BY en prod rompía con groupBy + columnas del SELECT.
            $claseData = DB::table('horarioMateria as hm')
                ->select(array_merge([
                    'f.id as ficha_id',
                    'f.codigo as ficha_codigo',
                    'p.nombrePrograma as programa_nombre',
                    'm.nombreMateria as materia_nombre',
                    'gm.idMateria as idMateria',
                ], $this->selectCompetenciaRapIdPadreMateriaClase(), [
                    'j.nombreJornada as jornada_nombre',
                    'j.nombreJornada as jornada_tipo',
                    'd.dia as dia_semana',
                ], $this->selectHoraTimeHorarioMateria(), [
                    'hm.fechaInicial as fechaInicial',
                    'hm.fechaFinal as fechaFinal',
                    'hm.idDia',
                    'c.id as contrato_id',
                    DB::raw("CONCAT(per.nombre1, ' ', per.apellido1) as instructor_nombre"),
                    'gp.id as idGradoPrograma',
                    'g.nombreGrado as grado_nombre',
                    'g.id as idGrado',
                    'hm.id as idHorarioMateria',
                    'gm.id as idGradoMateria',
                    'ap.fechaInicialClases as periodo_fecha_inicial_clases',
                    'ap.fechaFinalClases as periodo_fecha_final_clases',
                    'ap.id as idAsignacion',
                ]))
                ->join('ficha as f', 'hm.idFicha', '=', 'f.id')
                ->join('jornadas as j', 'f.idJornada', '=', 'j.id')
                ->join('aperturarprograma as ap', 'f.idAsignacion', '=', 'ap.id')
                ->join('programa as p', 'ap.idPrograma', '=', 'p.id')
                ->join('gradoMateria as gm', 'hm.idGradoMateria', '=', 'gm.id')
                ->join('materia as m', 'gm.idMateria', '=', 'm.id');
            $claseData = $this->aplicarJoinsMateriaCompetenciaRapSeguimiento($claseData)
                ->leftJoin('gradoPrograma as gp', 'gm.idGradoPrograma', '=', 'gp.id')
                ->leftJoin('grado as g', 'gp.idGrado', '=', 'g.id')
                ->leftJoin('dia as d', 'hm.idDia', '=', 'd.id')
                ->leftJoin('contrato as c', 'hm.idContrato', '=', 'c.id')
                ->leftJoin('persona as per', 'c.idpersona', '=', 'per.id')
                ->where('hm.id', $idHorarioMateria)
                ->first();

            if (!$claseData) {
                return response()->json([
                    'message' => 'Clase no encontrada',
                    'error' => 'No existe una clase con el ID proporcionado'
                ], 404);
            }

            // Calcular estado, total_sesiones y sesiones_restantes
            $estado = $this->calcularEstadoHorario(
                $claseData->fechaInicial,
                $claseData->fechaFinal,
                $claseData->horaInicial,
                $claseData->horaFinal,
                $claseData->idDia
            );

            $totalSesiones = $this->calcularTotalSesiones(
                $claseData->fechaInicial,
                $claseData->fechaFinal,
                $claseData->idDia
            );

            // Sincronizar sesiones completadas automáticamente (crear registros en BD cuando comienza la clase)
            $this->sincronizarSesionesCompletadas(
                $idHorarioMateria,
                $claseData->fechaInicial,
                $claseData->fechaFinal,
                $claseData->horaInicial,
                $claseData->horaFinal,
                $claseData->idDia
            );

            // Calcular sesiones dadas basándose en registros reales de sesionMateria
            $sesionesDadas = $this->calcularSesionesDadas(
                $idHorarioMateria,
                $claseData->fechaInicial,
                $claseData->fechaFinal,
                $claseData->horaFinal,
                $claseData->idDia
            );

            $sesionesRestantes = max(0, $totalSesiones - $sesionesDadas);

            // Obtener sesiones completadas con fechas específicas
            $sesionesCompletadas = $this->obtenerSesionesCompletadas($idHorarioMateria);

            // Agregar campos calculados
            $claseData->estado = $estado;
            $claseData->total_sesiones = $totalSesiones;
            $claseData->sesiones_dadas = $sesionesDadas;
            $claseData->sesiones_restantes = $sesionesRestantes;
            $claseData->sesiones_completadas = $sesionesCompletadas;

            // Obtener la ficha completa para compatibilidad con el componente
            $ficha = Ficha::with([
                'jornada',
                'asignacion' => function ($query) {
                    $query->with([
                        'periodo',
                        'programa',
                        'sede'
                    ]);
                },
                'sede',
                'infraestructura',
                'regional',
                'instructorLider.persona'
            ])->find($claseData->ficha_id);

            if (!$ficha) {
                return response()->json([
                    'message' => 'Ficha no encontrada',
                    'error' => 'No existe una ficha asociada a esta clase'
                ], 404);
            }

            // Obtener datos completos del instructor asignado a esta clase específica
            $instructorClase = null;
            if ($claseData->contrato_id) {
                $contrato = \App\Models\Contract::with('persona')->find($claseData->contrato_id);
                if ($contrato && $contrato->persona) {
                    $instructorClase = [
                        'id' => $contrato->id,
                        'persona' => [
                            'id' => $contrato->persona->id,
                            'nombre1' => $contrato->persona->nombre1,
                            'nombre2' => $contrato->persona->nombre2,
                            'apellido1' => $contrato->persona->apellido1,
                            'apellido2' => $contrato->persona->apellido2,
                            'email' => $contrato->persona->email,
                            'rutaFotoUrl' => $contrato->persona->rutaFotoUrl ?? null
                        ]
                    ];
                }
            }

            // Agregar datos del instructor a claseData
            $claseDataArray = (array) $claseData;
            $claseDataArray['instructor'] = $instructorClase;

            try {
                $idContratoVista = null;
                try {
                    $cv = KeyUtil::lastContractActive();
                    if ($cv && $cv->id)
                        $idContratoVista = (int) $cv->id;
                } catch (\Throwable $e) {
                }
                if (!$idContratoVista && !empty($claseData->contrato_id))
                    $idContratoVista = (int) $claseData->contrato_id;
                foreach ($this->resolverModalidadRap($idHorarioMateria, $idContratoVista) as $k => $v) {
                    $claseDataArray[$k] = $v;
                }
            } catch (\Throwable $e) {
                // No romper el detalle si falla la modalidad
            }
            // Cast (array) de stdClass puede dejar claves raras según driver; fijar campos críticos para el calendario.
            $claseDataArray['idDia'] = isset($claseData->idDia) ? (int) $claseData->idDia : null;
            $claseDataArray['idHorarioMateria'] = (int) ($claseData->idHorarioMateria ?? $idHorarioMateria);
            if (isset($claseData->dia_semana)) {
                $claseDataArray['dia_semana'] = $claseData->dia_semana;
            }

            // Franjas del mismo contrato que la clase abierta (alineado con `clasesAsignadasInstructor`:
            // `hm.idContrato` = contrato del instructor O fila en `asignacionSesion` para ese contrato).
            // No usar solo idpersona: mezcla otros contratos del mismo docente y desvirtúa el calendario vs "Mi horario".
            $contratoClase = (int) ($claseData->contrato_id ?? 0);
            $tieneAsignacionSesionDetalle = Schema::hasTable('asignacionSesion');

            $qbTodasFechas = DB::table('horarioMateria as hm')
                ->select(array_merge([
                    'hm.id as idHorarioMateria',
                    'hm.fechaInicial',
                    'hm.fechaFinal',
                    'd.dia as dia_semana',
                    'hm.idDia',
                    'f.codigo as ficha_codigo',
                    'm.nombreMateria as materia_nombre',
                    'p.nombrePrograma as programa_nombre',
                ], $this->selectCompetenciaRapIdPadreMateriaClase(), [
                    'j.nombreJornada as jornada_nombre',
                    'j.nombreJornada as jornada_tipo',
                ], $this->selectHoraTimeHorarioMateria()))
                ->leftJoin('dia as d', 'hm.idDia', '=', 'd.id')
                ->join('ficha as f', 'hm.idFicha', '=', 'f.id')
                ->join('jornadas as j', 'f.idJornada', '=', 'j.id')
                ->leftJoin('aperturarprograma as ap', 'f.idAsignacion', '=', 'ap.id')
                ->leftJoin('programa as p', 'ap.idPrograma', '=', 'p.id')
                ->join('gradoMateria as gm', 'hm.idGradoMateria', '=', 'gm.id')
                ->join('materia as m', 'gm.idMateria', '=', 'm.id');
            $qbTodasFechas = $this->aplicarJoinsMateriaCompetenciaRapSeguimiento($qbTodasFechas);

            if ($tieneAsignacionSesionDetalle) {
                $qbTodasFechas->leftJoin('asignacionSesion as asig', function ($join) use ($contratoClase) {
                    $join->on('hm.id', '=', 'asig.idHorarioMateria')
                        ->where('asig.idContrato', '=', $contratoClase);
                });
            }

            $todasLasFechasClase = $qbTodasFechas
                ->where(function ($query) use ($contratoClase, $tieneAsignacionSesionDetalle) {
                    $query->where('hm.idContrato', $contratoClase);
                    if ($tieneAsignacionSesionDetalle) {
                        $query->orWhereNotNull('asig.id');
                    }
                })
                ->whereNotNull('hm.fechaInicial')
                ->whereNotNull('d.dia')
                ->orderBy('hm.fechaInicial', 'asc')
                ->orderBy('hm.horaInicial', 'asc')
                ->get();

            $apertura = AperturarPrograma::find($ficha->idAsignacion);

            // Obtener sesiones completadas con sus fechas específicas
            $sesionesCompletadas = $this->obtenerSesionesCompletadas($idHorarioMateria);

            $idsCalendario = $todasLasFechasClase->pluck('idHorarioMateria')->map(fn($v) => (int) $v)->unique()->values()->all();
            $sesionesCompletadasPorHorario = $this->obtenerSesionesCompletadasPorHorarios($idsCalendario);

            return response()->json([
                'message' => 'Clase encontrada',
                'data' => [
                    'clase' => $claseDataArray,
                    'ficha' => $ficha,
                    'apertura' => $apertura,
                    'todasLasFechasClase' => $todasLasFechasClase,
                    'sesionesCompletadas' => $sesionesCompletadas,
                    'sesionesCompletadasPorHorario' => $sesionesCompletadasPorHorario,
                ]
            ], 200);
        } catch (\Throwable $e) {
            Log::error('Error al obtener detalle de clase por horario', [
                'idHorarioMateria' => $idHorarioMateria,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'message' => 'Error al obtener los detalles de la clase',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * LEFT JOINs materia padre + seguimientoMateria (misma regla que `clasesAsignadasInstructor`).
     *
     * @param  \Illuminate\Database\Query\Builder  $qb
     * @return \Illuminate\Database\Query\Builder
     */
    private function aplicarJoinsMateriaCompetenciaRapSeguimiento($qb)
    {
        return $qb
            ->leftJoin('materia as m_padre', 'm.idMateriaPadre', '=', 'm_padre.id')
            ->leftJoin('seguimientoMateria as sm', function ($join) {
                $join->whereRaw(
                    'sm.id = (SELECT MAX(sm2.id) FROM seguimientoMateria sm2 WHERE sm2.idFicha = f.id AND sm2.idMateria = m.id)'
                );
            })
            ->leftJoin('materia as m_sm_padre', 'sm.idMateriaPadre', '=', 'm_sm_padre.id');
    }

    /**
     * @return array<int, mixed>
     */
    private function selectCompetenciaRapIdPadreMateriaClase(): array
    {
        return [
            'm.idMateriaPadre',
            DB::raw('COALESCE(
                CASE WHEN m.idMateriaPadre IS NOT NULL AND m.idMateriaPadre > 0 AND m_padre.id IS NOT NULL THEN m_padre.nombreMateria END,
                CASE WHEN sm.id IS NOT NULL AND sm.idMateriaPadre IS NOT NULL AND sm.idMateriaPadre > 0 AND m_sm_padre.id IS NOT NULL THEN m_sm_padre.nombreMateria END,
                m.nombreMateria
            ) as competencia_nombre'),
            DB::raw('CASE
                WHEN m.idMateriaPadre IS NOT NULL AND m.idMateriaPadre > 0 AND m_padre.id IS NOT NULL THEN m.nombreMateria
                WHEN sm.id IS NOT NULL AND sm.idMateriaPadre IS NOT NULL AND sm.idMateriaPadre > 0 AND m_sm_padre.id IS NOT NULL THEN m.nombreMateria
                ELSE NULL
            END as rap_nombre'),
        ];
    }

    /**
     * @return array<int, \Illuminate\Contracts\Database\Query\Expression>
     */
    private function selectHoraTimeHorarioMateria(): array
    {
        return [
            DB::raw('TIME(hm.horaInicial) as horaInicial'),
            DB::raw('TIME(hm.horaFinal) as horaFinal'),
        ];
    }

    /**
     * Hora normalizada para la clave de deduplicación de `clasesAsignadasInstructor`.
     * Debe coincidir con `schoolSenaFrontFinal/src/utils/clasesAsignadasLogica.ts` (`horaClaveClaseAsignada`).
     */
    private function horaClaveClaseAsignada(string $v): string
    {
        $s = trim($v);
        if ($s === '') {
            return '';
        }
        if (preg_match('/(\d{1,2}):(\d{2})/', $s, $m)) {
            return sprintf('%02d:%02d', (int) $m[1], (int) $m[2]);
        }

        return substr($s, 0, 5);
    }

    /**
     * Texto de jornada para la misma clave (preferir nombre; mayúsculas UTF-8).
     * Alineado con `jornadaClaveClaseAsignada` en el front.
     */
    private function jornadaClaveClaseAsignada(object $row): string
    {
        $n = trim((string) ($row->jornada_nombre ?? ''));
        $t = trim((string) ($row->jornada_tipo ?? ''));
        $s = $n !== '' ? $n : $t;

        return mb_strtoupper($s, 'UTF-8');
    }

    /**
     * Clave única por slot en listados de un instructor (no usar en listados multi-instructor).
     * Alineado con `claveLogicaClaseAsignadaInstructor` en el front.
     */
    private function claveLogicaClaseAsignadaInstructor(object $row): string
    {
        return implode('|', [
            (int) ($row->ficha_id ?? 0),
            (int) ($row->idDia ?? 0),
            $this->horaClaveClaseAsignada((string) ($row->horaInicial ?? '')),
            $this->horaClaveClaseAsignada((string) ($row->horaFinal ?? '')),
            $this->jornadaClaveClaseAsignada($row),
        ]);
    }

    /**
     * Prioriza franja vigente hoy y fechaFinal más reciente (alineado con el front).
     */
    private function puntajeClaseParaDedupeInstructor(object $row, Carbon $ref): int
    {
        $hoy = $ref->copy()->startOfDay();
        $score = 0;

        $ini = !empty($row->fechaInicial) ? Carbon::parse($row->fechaInicial)->startOfDay() : null;
        $fin = !empty($row->fechaFinal) ? Carbon::parse($row->fechaFinal)->startOfDay() : null;

        if ($ini && $fin && $ini->lte($hoy) && $fin->gte($hoy)) {
            $score += 1_000_000_000;
        }

        $rest = (int) ($row->sesiones_restantes ?? 0);
        if ($rest > 0) {
            $score += 100_000_000;
        }

        if ($fin) {
            $score += (int) $fin->timestamp;
        }

        $score += (int) ($row->idHorarioMateria ?? 0);

        return $score;
    }

    private function dedupeClasesAsignadasInstructorPorClaveLogica(\Illuminate\Support\Collection $clases): \Illuminate\Support\Collection
    {
        $hoy = Carbon::today();
        $porClave = [];

        foreach ($clases as $c) {
            $key = $this->claveLogicaClaseAsignadaInstructor($c);
            if (!isset($porClave[$key]) || $this->puntajeClaseParaDedupeInstructor($c, $hoy) > $this->puntajeClaseParaDedupeInstructor($porClave[$key], $hoy)) {
                $porClave[$key] = $c;
            }
        }

        return collect(array_values($porClave));
    }

    /**
     * Fechas consecutivas en [fechaInicial, fechaFinal] donde coincide el día de clase (idDia).
     * Una sola fuente de verdad para total de sesiones, sincronización y conteo de sesiones dadas.
     *
     * @return string[] Fechas en formato Y-m-d
     */
    private function obtenerFechasSesionesProgramadas(string $fechaInicial, ?string $fechaFinal, int $idDia): array
    {
        if (!$fechaFinal) {
            return [];
        }

        $inicio = Carbon::parse($fechaInicial);
        $fin = Carbon::parse($fechaFinal);

        // idDia BD: 1=Lunes … 7=Domingo → Carbon dayOfWeek (0=Domingo … 6=Sábado)
        $carbonDayOfWeek = $idDia === 7 ? 0 : $idDia;

        $fechas = [];
        $fechaActual = $inicio->copy();

        while ($fechaActual->lte($fin)) {
            if ($fechaActual->dayOfWeek === $carbonDayOfWeek) {
                $fechas[] = $fechaActual->format('Y-m-d');
            }
            $fechaActual->addDay();
        }

        return $fechas;
    }

    /**
     * Calcula el total de sesiones entre fechaInicial y fechaFinal
     * basado en el día de la semana (idDia)
     * 
     * @param string $fechaInicial Fecha inicial en formato Y-m-d
     * @param string|null $fechaFinal Fecha final en formato Y-m-d (puede ser null)
     * @param int $idDia ID del día de la semana (1=Lunes, 2=Martes, ..., 7=Domingo)
     * @return int Total de sesiones
     */
    private function calcularTotalSesiones(string $fechaInicial, ?string $fechaFinal, int $idDia): int
    {
        return count($this->obtenerFechasSesionesProgramadas($fechaInicial, $fechaFinal, $idDia));
    }

    /**
     * Horas y nombre de jornada del horario (para alinear sesionMateria con hora final real).
     */
    private function metaHorarioParaSesiones(int $idHorarioMateria): ?object
    {
        $row = DB::table('horarioMateria as hm')
            ->join('ficha as f', 'hm.idFicha', '=', 'f.id')
            ->join('jornadas as j', 'f.idJornada', '=', 'j.id')
            ->where('hm.id', $idHorarioMateria)
            ->select(['hm.horaInicial', 'hm.horaFinal', 'j.nombreJornada as jornada_nombre'])
            ->first();

        return $row ?: null;
    }

    /**
     * Momento de fin de la franja de clase en la fecha dada (horas literales de horarioMateria, 24h).
     */
    private function carbonFinVentanaClaseDia(
        string $fechaYmd,
        string $horaInicial,
        string $horaFinal,
        ?string $jornadaNombre
    ): ?Carbon {
        $horaIni = $this->parseHora($horaInicial);
        $horaFin = $this->parseHora($horaFinal);
        if (!$horaIni || !$horaFin) {
            return null;
        }

        $hIni = $horaIni->hour;
        $mIni = $horaIni->minute;
        $hFin = $horaFin->hour;
        $mFin = $horaFin->minute;

        $base = Carbon::parse($fechaYmd)->startOfDay();
        $inicio = $base->copy()->setTime($hIni, $mIni, 0);
        $fin = $base->copy()->setTime($hFin, $mFin, 0);
        if ($fin->lt($inicio)) {
            $fin->addDay();
        }

        return $fin;
    }

    /**
     * La fila en sesionMateria solo cuenta como sesión finalizada para totales/API
     * cuando el reloj actual ya pasó la hora final del horario ese día.
     */
    private function sesionMateriaContadaComoFinalizada(
        string $fechaSesion,
        string $horaInicial,
        string $horaFinal,
        ?string $jornadaNombre
    ): bool {
        $ymd = Carbon::parse($fechaSesion)->format('Y-m-d');
        $fin = $this->carbonFinVentanaClaseDia($ymd, $horaInicial, $horaFinal, $jornadaNombre);
        if ($fin === null) {
            return false;
        }

        return Carbon::now()->gte($fin);
    }

    /**
     * Calcula cuántas sesiones ya están completadas consultando la base de datos
     * Cuenta los registros reales en sesionMateria con fechaSesion no nula
     * 
     * @param int $idHorarioMateria ID del horario de materia
     * @param string $fechaInicial Fecha inicial en formato Y-m-d
     * @param string|null $fechaFinal Fecha final en formato Y-m-d (puede ser null)
     * @param string $horaFinal Hora final (formato H:i:s) - usado para validación
     * @param int $idDia ID del día de la semana (1=Lunes, 2=Martes, ..., 7=Domingo)
     * @return int Número de sesiones completadas
     */
    private function calcularSesionesDadas(
        int $idHorarioMateria,
        string $fechaInicial,
        ?string $fechaFinal,
        string $horaFinal,
        int $idDia
    ): int {
        $fechasProgramadas = $this->obtenerFechasSesionesProgramadas($fechaInicial, $fechaFinal, $idDia);
        if ($fechasProgramadas === []) {
            return 0;
        }

        $meta = $this->metaHorarioParaSesiones($idHorarioMateria);
        if ($meta === null) {
            return 0;
        }

        // Solo cuentan fechas con registro en sesionMateria y ya pasada la hora final de esa franja (reloj real).
        return SesionMateria::query()
            ->where('idHorarioMateria', $idHorarioMateria)
            ->whereNotNull('fechaSesion')
            ->whereIn(DB::raw('DATE(fechaSesion)'), $fechasProgramadas)
            ->get()
            ->filter(function ($s) use ($meta) {
                return $this->sesionMateriaContadaComoFinalizada(
                    (string) $s->fechaSesion,
                    (string) $meta->horaInicial,
                    (string) $meta->horaFinal,
                    $meta->jornada_nombre ?? null
                );
            })
            ->map(fn($s) => Carbon::parse($s->fechaSesion)->format('Y-m-d'))
            ->unique()
            ->count();
    }

    /**
     * Sincroniza automáticamente las sesiones completadas en la base de datos
     * Sincroniza automáticamente las sesiones completadas creando registros en BD
     * cuando la hora de inicio de la clase ya ha pasado
     * 
     * @param int $idHorarioMateria ID del horario de materia
     * @param string $fechaInicial Fecha inicial del horario (Y-m-d)
     * @param string|null $fechaFinal Fecha final del horario (Y-m-d) o null
     * @param string $horaInicial Hora inicial de la clase (H:i:s)
     * @param string $horaFinal Hora final de la clase (H:i:s)
     * @param int $idDia ID del día de la semana (1=Lunes, ..., 7=Domingo)
     * @return void
     */
    private function sincronizarSesionesCompletadas(
        int $idHorarioMateria,
        string $fechaInicial,
        ?string $fechaFinal,
        string $horaInicial,
        string $horaFinal,
        int $idDia
    ): void {
        if (!$fechaFinal) {
            return;
        }

        try {
            $ahora = Carbon::now();

            // Parsear horas
            $horaIni = $this->parseHora($horaInicial);
            $horaFin = $this->parseHora($horaFinal);
            if (!$horaIni || !$horaFin) {
                return;
            }

            // Obtener sesiones existentes como array de fechas para validación rápida
            $sesionesExistentes = SesionMateria::where('idHorarioMateria', $idHorarioMateria)
                ->whereNotNull('fechaSesion')
                ->pluck('fechaSesion')
                ->map(fn($fecha) => Carbon::parse($fecha)->format('Y-m-d'))
                ->toArray();

            // Misma lista de fechas que total_sesiones / calcularTotalSesiones (sin sesiones “fantasma” en días incorrectos)
            $todasLasFechasClase = $this->obtenerFechasSesionesProgramadas($fechaInicial, $fechaFinal, $idDia);

            // Crear mapa de fecha -> numeroSesion (orden cronológico)
            $mapaFechaNumeroSesion = [];
            foreach ($todasLasFechasClase as $index => $fechaClase) {
                $mapaFechaNumeroSesion[$fechaClase] = $index + 1;
            }

            // Crear sesiones para fechas pasadas que ya cumplieron su hora de inicio
            $nuevasSesiones = [];

            foreach ($todasLasFechasClase as $fechaClaseStr) {
                // Si ya existe, omitir
                if (in_array($fechaClaseStr, $sesionesExistentes)) {
                    continue;
                }

                // La sesión se crea cuando TERMINA la clase (horaFinal), no cuando comienza
                $fechaClase = Carbon::parse($fechaClaseStr);
                $fechaHoraFinSesion = $fechaClase->copy()
                    ->setTime($horaFin->hour, $horaFin->minute, $horaFin->second);

                // Si ya pasó la hora FINAL de la clase (cuando termina el cronómetro), crear la sesión
                // Esto asegura que la sesión solo se marca como completada cuando realmente terminó
                if ($ahora->gte($fechaHoraFinSesion)) {
                    $numeroSesion = $mapaFechaNumeroSesion[$fechaClaseStr] ?? null;
                    if ($numeroSesion !== null) {
                        $nuevasSesiones[] = [
                            'numeroSesion' => $numeroSesion,
                            'idHorarioMateria' => $idHorarioMateria,
                            'fechaSesion' => $fechaClaseStr,
                            'estado' => EstadoSesionMateria::APLICA,
                            'created_at' => Carbon::now(),
                            'updated_at' => Carbon::now(),
                        ];
                    }
                }
            }

            // Insertar todas las nuevas sesiones en una sola operación
            if (!empty($nuevasSesiones)) {
                SesionMateria::insert($nuevasSesiones);
            }
        } catch (\Exception $e) {
            Log::error('Error al sincronizar sesiones completadas', [
                'idHorarioMateria' => $idHorarioMateria,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Calcula el estado de un horario basado en fecha, hora y día de la semana
     * 
     * Lógica:
     * 1. Si fecha actual < fechaInicial → PENDIENTE (aún no ha comenzado)
     * 2. Si fecha actual > fechaFinal (y existe) → COMPLETADO (ya terminó)
     * 3. Si está en el rango de fechas:
     *    - Si NO es el día correcto de la semana → PENDIENTE
     *    - Si ES el día correcto:
     *      - Si hora < horaInicial → PENDIENTE
     *      - Si hora entre horaInicial y horaFinal → EN CURSO
     *      - Si hora > horaFinal → PENDIENTE (para la próxima clase)
     * 
     * @param string $fechaInicial Fecha inicial
     * @param string|null $fechaFinal Fecha final (puede ser null)
     * @param string $horaInicial Hora inicial (formato H:i:s)
     * @param string $horaFinal Hora final (formato H:i:s)
     * @param int $idDia ID del día de la semana (1=Lunes, 2=Martes, ..., 7=Domingo)
     * @return string Estado: 'PENDIENTE', 'EN CURSO', 'COMPLETADO'
     */
    private function calcularEstadoHorario(
        string $fechaInicial,
        ?string $fechaFinal,
        string $horaInicial,
        string $horaFinal,
        int $idDia
    ): string {
        $ahora = Carbon::now();
        $fechaIni = Carbon::parse($fechaInicial)->startOfDay();
        $fechaActual = $ahora->copy()->startOfDay();

        // PASO 1: Verificar si la fecha actual está ANTES de la fecha inicial
        // Si es así, la clase aún no ha comenzado → PENDIENTE
        if ($fechaActual->lt($fechaIni)) {
            return 'PENDIENTE';
        }

        // PASO 2: Verificar si la fecha actual está DESPUÉS de la fecha final
        // Si fechaFinal existe y ya pasó, la clase está COMPLETADA
        if ($fechaFinal) {
            $fechaFin = Carbon::parse($fechaFinal)->startOfDay();
            if ($fechaActual->gt($fechaFin)) {
                return 'COMPLETADO';
            }
        }

        // PASO 3: Estamos en el rango de fechas [fechaInicial, fechaFinal o indefinido]
        // Ahora verificamos el día de la semana
        // IMPORTANTE: Verificar que idDia sea válido
        if ($idDia < 1 || $idDia > 7) {
            return 'PENDIENTE';
        }

        // idDia BD: 1=Lunes, 2=Martes, 3=Miércoles, 4=Jueves, 5=Viernes, 6=Sábado, 7=Domingo
        // Carbon dayOfWeek: 0=Domingo, 1=Lunes, 2=Martes, 3=Miércoles, 4=Jueves, 5=Viernes, 6=Sábado
        $carbonDayOfWeek = $idDia === 7 ? 0 : $idDia;
        $esDiaCorrecto = $ahora->dayOfWeek === $carbonDayOfWeek;

        // Si NO es el día correcto de la semana, la clase está PENDIENTE
        if (!$esDiaCorrecto) {
            return 'PENDIENTE';
        }

        // PASO 4: Es el día correcto de la semana, ahora verificamos la hora
        // Parsear horas de forma segura
        $horaIni = $this->parseHora($horaInicial);
        $horaFin = $this->parseHora($horaFinal);

        if (!$horaIni || !$horaFin) {
            return 'PENDIENTE';
        }

        // Extraer solo la hora para comparar (minutos desde medianoche)
        $horaActualMinutos = ($ahora->hour * 60) + $ahora->minute;
        $horaIniMinutos = ($horaIni->hour * 60) + $horaIni->minute;
        $horaFinMinutos = ($horaFin->hour * 60) + $horaFin->minute;

        // Comparar horas
        if ($horaActualMinutos < $horaIniMinutos) {
            // Aún no ha llegado la hora inicial → PENDIENTE
            return 'PENDIENTE';
        }

        if ($horaActualMinutos >= $horaIniMinutos && $horaActualMinutos <= $horaFinMinutos) {
            // Está en el rango de horas de la clase → EN CURSO
            // (Ya verificamos que es el día correcto y está en el rango de fechas)
            return 'EN CURSO';
        }

        if ($horaActualMinutos > $horaFinMinutos) {
            // Ya pasó la hora final de la clase de hoy (ej: 8:15 AM)
            // Si es el día correcto y ya pasó la hora final, esa sesión específica está COMPLETADA
            // Retornamos COMPLETADO para indicar que esa sesión del día ya terminó
            return 'COMPLETADO';
        }

        // Por defecto, pendiente
        return 'PENDIENTE';
    }

    /**
     * Parsea una hora de forma segura desde diferentes formatos
     * 
     * @param string $hora Hora en formato H:i:s o H:i
     * @return Carbon|null Objeto Carbon con la hora o null si no se puede parsear
     */
    /**
     * Obtiene las sesiones completadas de un horario con sus fechas formateadas
     * (solo sesiones ya cerradas según hora final del horario y reloj actual).
     *
     * @param int $idHorarioMateria ID del horario de materia
     * @return array Array de sesiones completadas con fecha formateada
     */
    private function obtenerSesionesCompletadas(int $idHorarioMateria): array
    {
        $horario = DB::table('horarioMateria as hm')
            ->join('ficha as f', 'hm.idFicha', '=', 'f.id')
            ->join('jornadas as j', 'f.idJornada', '=', 'j.id')
            ->where('hm.id', $idHorarioMateria)
            ->select([
                'hm.fechaInicial',
                'hm.fechaFinal',
                'hm.idDia',
                'hm.horaInicial',
                'hm.horaFinal',
                'j.nombreJornada as jornada_nombre',
            ])
            ->first();

        $meta = $this->metaHorarioParaSesiones($idHorarioMateria);
        if ($meta === null) {
            return [];
        }

        // Buscar el evaluador vía HorarioMateria -> GradoMateria -> MatriculaAcademica -> Persona
        $evaluador = DB::table('horarioMateria as hm')
            ->join('gradoMateria as gm', 'hm.idGradoMateria', '=', 'gm.id')
            ->join('matriculaAcademica as ma', function ($join) {
                $join->on('hm.idFicha', '=', 'ma.idFicha')
                    ->on('gm.idMateria', '=', 'ma.idMateria');
            })
            ->join('persona as p', 'ma.idEvaluador', '=', 'p.id')
            ->where('hm.id', $idHorarioMateria)
            ->whereNotNull('ma.idEvaluador')
            ->select('p.nombre1', 'p.apellido1', 'p.nombre2', 'p.apellido2')
            ->first();

        $evaluadorNombre = null;
        if ($evaluador) {
            $evaluadorNombre = trim(preg_replace('/\s+/', ' ', "{$evaluador->nombre1} {$evaluador->nombre2} {$evaluador->apellido1} {$evaluador->apellido2}"));
        }

        // Todas las filas en sesionMateria + las que cuentan en sesiones_dadas (mismo criterio que el badge X/Y).
        $sesiones = SesionMateria::where('idHorarioMateria', $idHorarioMateria)
            ->whereNotNull('fechaSesion')
            ->orderBy('fechaSesion', 'asc')
            ->get();

        $fechasProgramadas = [];
        if ($horario && !empty($horario->fechaInicial)) {
            $fechasProgramadas = $this->obtenerFechasSesionesProgramadas(
                (string) $horario->fechaInicial,
                $horario->fechaFinal !== null ? (string) $horario->fechaFinal : null,
                (int) $horario->idDia
            );
        }

        $sesionesContadas = $sesiones->filter(function ($s) use ($fechasProgramadas, $meta) {
            if ($fechasProgramadas === []) {
                return true;
            }
            $ymd = Carbon::parse((string) $s->fechaSesion)->format('Y-m-d');
            if (!in_array($ymd, $fechasProgramadas, true)) {
                return false;
            }

            return $this->sesionMateriaContadaComoFinalizada(
                (string) $s->fechaSesion,
                (string) $meta->horaInicial,
                (string) $meta->horaFinal,
                $meta->jornada_nombre ?? null
            );
        });

        $sesiones = $sesiones->merge($sesionesContadas)->unique('id')->sortBy('fechaSesion')->values();

        return $sesiones->map(function ($sesion) use ($evaluadorNombre) {
            $fecha = Carbon::parse($sesion->fechaSesion);

            return [
                'id' => $sesion->id,
                'numeroSesion' => $sesion->numeroSesion,
                'fechaSesion' => $fecha->format('Y-m-d'),
                'fechaFormateada' => $fecha->locale('es')->isoFormat('dddd, D [de] MMMM [de] YYYY'),
                'fechaCorta' => $fecha->format('d/m/Y'),
                'estado' => $sesion->estado,
                'observacion' => $sesion->observacion,
                'evaluador_nombre' => $evaluadorNombre,
            ];
        })->toArray();
    }

    /**
     * Sesiones completadas agrupadas por idHorarioMateria (calendario con varias franjas el mismo día).
     *
     * @param  array<int>  $idsHorarioMateria
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function obtenerSesionesCompletadasPorHorarios(array $idsHorarioMateria): array
    {
        $idsHorarioMateria = array_values(array_unique(array_filter(array_map('intval', $idsHorarioMateria))));
        if ($idsHorarioMateria === []) {
            return [];
        }

        // Misma regla que `obtenerSesionesCompletadas`: todas las filas en sesionMateria (calendario verde).
        $porHorario = [];
        foreach ($idsHorarioMateria as $idHm) {
            $porHorario[$idHm] = $this->obtenerSesionesCompletadas($idHm);
        }

        return $porHorario;
    }

    /**
     * Obtener todas las clases (materias) de un estudiante
     * Devuelve materias agrupadas con información completa: profesor, aula, horarios, sesiones
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function clasesEstudiante(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();
            if (!$user || !$user->idpersona) {
                return response()->json([
                    'message' => 'Usuario no autenticado o sin persona asociada',
                    'data' => []
                ], 401);
            }

            $idPersona = (int) $user->idpersona;

            // Estados que consideramos “vigentes” para ver horario (case/whitespace-safe).
            // Mantener alineado con otros módulos (GruposFichaController / AsignacionActividadController).
            $estadosVigentes = ['ACTIVO', 'EN CURSO', 'CURSANDO', 'MATRICULADO', 'EN FORMACION'];

            // 1) Fuente primaria: matrícula con ficha directa (algunos entornos llenan m.idFicha).
            $matriculas = DB::table('matricula as m')
                ->where('m.idPersona', $idPersona)
                ->whereIn(DB::raw('UPPER(TRIM(m.estado))'), $estadosVigentes)
                ->whereNotNull('m.idFicha')
                ->select('m.idFicha', 'm.estado', 'm.id')
                ->distinct()
                ->get();

            $idsFichas = $matriculas->pluck('idFicha')->map(fn($v) => (int) $v)->filter()->unique()->values()->toArray();

            // 2) Fallback: si no hay m.idFicha, derivar fichas desde matriculaAcademica.
            // Esto cubre aprendices que sí están matriculados en una ficha, pero su ficha solo está reflejada en el módulo académico.
            if ($idsFichas === []) {
                $idsFichas = DB::table('matriculaAcademica as ma')
                    ->join('matricula as m', 'ma.idMatricula', '=', 'm.id')
                    ->where('m.idPersona', $idPersona)
                    ->whereIn(DB::raw('UPPER(TRIM(m.estado))'), $estadosVigentes)
                    ->whereNotNull('ma.idFicha')
                    ->select('ma.idFicha')
                    ->distinct()
                    ->pluck('ma.idFicha')
                    ->map(fn($v) => (int) $v)
                    ->filter()
                    ->unique()
                    ->values()
                    ->toArray();
            }

            if ($idsFichas === []) {
                return response()->json([
                    'message' => 'El estudiante no tiene matrículas activas con fichas asignadas',
                    'data' => []
                ], 200);
            }

            $clases = DB::table('horarioMateria as hm')
                ->select([
                    'm.nombreMateria as materia_nombre',
                    'hm.id as idHorarioMateria',
                    'hm.idFicha',
                    'hm.fechaInicial',
                    'hm.fechaFinal',
                    'hm.horaInicial',
                    'hm.horaFinal',
                    'hm.idDia',
                    'd.dia as dia_semana',
                    'f.codigo as ficha_codigo',
                    'inf.nombreInfraestructura as aula_nombre',
                    'c.id as contrato_id',
                    DB::raw("TRIM(CONCAT(
                    COALESCE(per.nombre1, ''), ' ',
                    COALESCE(per.nombre2, ''), ' ',
                    COALESCE(per.apellido1, ''), ' ',
                    COALESCE(per.apellido2, '')
                )) as profesor_nombre"),
                    'per.email as profesor_email',
                    'gm.idMateria as idMateria'
                ])
                ->join('ficha as f', 'hm.idFicha', '=', 'f.id')
                ->join('gradoMateria as gm', 'hm.idGradoMateria', '=', 'gm.id')
                ->join('materia as m', 'gm.idMateria', '=', 'm.id')
                ->leftJoin('dia as d', 'hm.idDia', '=', 'd.id')
                ->leftJoin('infraestructura as inf', 'hm.idInfraestructura', '=', 'inf.id')
                ->leftJoin('contrato as c', 'hm.idContrato', '=', 'c.id')
                ->leftJoin('persona as per', 'c.idpersona', '=', 'per.id')
                ->whereIn('f.id', $idsFichas)
                ->whereNotNull('hm.idDia')
                ->whereNotNull('hm.horaInicial')
                ->whereNotNull('hm.horaFinal')
                ->orderBy('m.nombreMateria')
                ->orderBy('hm.idDia')
                ->orderBy('hm.horaInicial')
                ->get();

            if ($clases->isEmpty()) {
                return response()->json([
                    'message' => 'El estudiante tiene ficha asignada, pero no hay horarios registrados para esta ficha',
                    'data' => []
                ], 200);
            }

            // — Castear IDs a entero en cada clase antes de procesar —
            $clases = $clases->map(function ($clase) {
                $clase->idHorarioMateria = (int) $clase->idHorarioMateria;
                $clase->idMateria = (int) $clase->idMateria;
                $clase->idDia = (int) $clase->idDia;
                $clase->idFicha = (int) $clase->idFicha;
                $clase->contrato_id = $clase->contrato_id !== null ? (int) $clase->contrato_id : null;
                return $clase;
            });

            $clasesAgrupadas = $clases->groupBy(function ($clase) {
                if ($clase->contrato_id) {
                    return $clase->idMateria . '_contrato_' . $clase->contrato_id;
                }
                $profesorKey = trim($clase->profesor_nombre ?? 'Sin asignar');
                return $clase->idMateria . '_profesor_' . md5($profesorKey);
            });

            $materias = [];

            foreach ($clasesAgrupadas as $claveGrupo => $horariosMateria) {
                $primerHorario = $horariosMateria->first();
                $horariosUnicos = $horariosMateria->unique('idHorarioMateria');

                // — Validar fechas del primer horario —
                $fechaInicialValida = !empty($primerHorario->fechaInicial) && strtotime($primerHorario->fechaInicial) !== false;
                $fechaFinalValida = !empty($primerHorario->fechaFinal) && strtotime($primerHorario->fechaFinal) !== false;

                // — Construir diasHorarios —
                $diasHorarios = [];
                foreach ($horariosUnicos as $horario) {
                    $diaNombre = $horario->dia_semana ?? 'Sin día';
                    $horaIni = $horario->horaInicial ? Carbon::parse($horario->horaInicial)->format('H:i') : '';
                    $horaFin = $horario->horaFinal ? Carbon::parse($horario->horaFinal)->format('H:i') : '';
                    $diasHorarios[] = [
                        'dia' => $diaNombre,
                        'horaInicial' => $horaIni,
                        'horaFinal' => $horaFin,
                        'idHorarioMateria' => (int) $horario->idHorarioMateria,
                    ];
                }

                usort($diasHorarios, function ($a, $b) use ($horariosUnicos) {
                    $horarioA = $horariosUnicos->firstWhere('idHorarioMateria', $a['idHorarioMateria']);
                    $horarioB = $horariosUnicos->firstWhere('idHorarioMateria', $b['idHorarioMateria']);
                    return ((int) ($horarioA->idDia ?? 999)) <=> ((int) ($horarioB->idDia ?? 999));
                });

                // — Texto de horario para display —
                $horarioDisplay = [];
                foreach ($diasHorarios as $dh) {
                    $horarioDisplay[] = $dh['dia'] . ' ' . $dh['horaInicial'] . ' - ' . $dh['horaFinal'];
                }
                $horarioTexto = implode(', ', $horarioDisplay);

                // — Calcular sesiones y totales —
                $totalSesiones = 0;
                $sesionesCompletadas = 0;
                $todasLasSesiones = [];

                Log::info('clasesEstudiante: inicio grupo materia-profesor', [
                    'clave_grupo' => $claveGrupo,
                    'idMateria' => $primerHorario->idMateria ?? null,
                    'materia' => $primerHorario->materia_nombre ?? null,
                    'horarios_unicos' => $horariosUnicos->pluck('idHorarioMateria')->toArray(),
                ]);

                foreach ($horariosUnicos as $horario) {
                    // Validar fechas del horario actual
                    $hFechaIniValida = !empty($horario->fechaInicial) && strtotime($horario->fechaInicial) !== false;
                    $hFechaFinValida = !empty($horario->fechaFinal) && strtotime($horario->fechaFinal) !== false;

                    if (!$hFechaIniValida || !$hFechaFinValida) {
                        continue;
                    }

                    $this->sincronizarSesionesCompletadas(
                        (int) $horario->idHorarioMateria,
                        $horario->fechaInicial,
                        $horario->fechaFinal,
                        $horario->horaInicial,
                        $horario->horaFinal,
                        (int) $horario->idDia
                    );

                    $totalSesionesHorario = $this->calcularTotalSesiones(
                        $horario->fechaInicial,
                        $horario->fechaFinal,
                        (int) $horario->idDia
                    );

                    $sesionesDadasHorario = $this->calcularSesionesDadas(
                        (int) $horario->idHorarioMateria,
                        $horario->fechaInicial,
                        $horario->fechaFinal,
                        $horario->horaFinal,
                        (int) $horario->idDia
                    );

                    $sesionesCompletadasHorario = $this->obtenerSesionesCompletadas((int) $horario->idHorarioMateria);

                    $totalSesiones += (int) $totalSesionesHorario;
                    $sesionesCompletadas += (int) $sesionesDadasHorario;

                    foreach ($sesionesCompletadasHorario as $sesion) {
                        $todasLasSesiones[] = [
                            'id' => (int) $sesion['id'],
                            'numeroSesion' => (int) $sesion['numeroSesion'],
                            'fechaSesion' => $sesion['fechaSesion'],
                            'idHorarioMateria' => (int) $horario->idHorarioMateria,
                            'horaInicial' => $horario->horaInicial,
                            'horaFinal' => $horario->horaFinal,
                            'idDia' => (int) $horario->idDia,
                        ];
                    }
                }

                // — Calcular TODAS las fechas de sesiones con estado —
                $ahora = Carbon::now();
                $todasLasFechasSesiones = [];

                foreach ($horariosUnicos as $horario) {
                    $hFechaIniValida = !empty($horario->fechaInicial) && strtotime($horario->fechaInicial) !== false;
                    $hFechaFinValida = !empty($horario->fechaFinal) && strtotime($horario->fechaFinal) !== false;

                    if (!$hFechaIniValida || !$hFechaFinValida) {
                        continue;
                    }

                    $fechaInicio = Carbon::parse($horario->fechaInicial);
                    $fechaFin = Carbon::parse($horario->fechaFinal);
                    $idDia = (int) $horario->idDia;
                    $carbonDayOfWeek = $idDia === 7 ? 0 : $idDia;

                    $fechaTemporal = $fechaInicio->copy();

                    if ($fechaTemporal->dayOfWeek !== $carbonDayOfWeek) {
                        $diasHastaProximoDia = ($carbonDayOfWeek - $fechaTemporal->dayOfWeek + 7) % 7;
                        if ($diasHastaProximoDia === 0) {
                            $diasHastaProximoDia = 7;
                        }
                        $fechaTemporal->addDays($diasHastaProximoDia);
                    }

                    if ($fechaTemporal->lte($fechaFin)) {
                        while ($fechaTemporal->lte($fechaFin)) {
                            if ($fechaTemporal->dayOfWeek === $carbonDayOfWeek) {
                                $horaIni = $this->parseHora($horario->horaInicial);
                                $horaFin = $this->parseHora($horario->horaFinal);

                                if ($horaIni && $horaFin) {
                                    $fechaHoraInicio = $fechaTemporal->copy()->setTime($horaIni->hour, $horaIni->minute, $horaIni->second);
                                    $fechaHoraFin = $fechaTemporal->copy()->setTime($horaFin->hour, $horaFin->minute, $horaFin->second);

                                    $todasLasFechasSesiones[] = [
                                        'fecha' => $fechaTemporal->format('Y-m-d'),
                                        'fechaHoraInicioCarbon' => $fechaHoraInicio,
                                        'fechaHoraFinCarbon' => $fechaHoraFin,
                                        'horaInicial' => $horario->horaInicial,
                                        'horaFinal' => $horario->horaFinal,
                                        'idDia' => $idDia,
                                        'idHorarioMateria' => (int) $horario->idHorarioMateria,
                                    ];
                                }
                            }
                            $fechaTemporal->addWeek();
                        }
                    }
                }

                // — Ordenar por fecha y hora —
                usort($todasLasFechasSesiones, function ($a, $b) {
                    $fechaA = Carbon::parse($a['fecha'] . ' ' . $a['horaInicial']);
                    $fechaB = Carbon::parse($b['fecha'] . ' ' . $b['horaInicial']);
                    return $fechaA->getTimestamp() <=> $fechaB->getTimestamp();
                });

                // — FALLBACK: Si no se generaron sesiones pero hay horarios válidos, crear sesiones básicas —
                if (empty($todasLasFechasSesiones) && $horariosUnicos->isNotEmpty()) {
                    $sesionNum = 1;
                    foreach ($horariosUnicos as $horario) {
                        $hFechaIniValida = !empty($horario->fechaInicial) && strtotime($horario->fechaInicial) !== false;
                        $hFechaFinValida = !empty($horario->fechaFinal) && strtotime($horario->fechaFinal) !== false;

                        if (!$hFechaIniValida || !$hFechaFinValida) {
                            continue;
                        }

                        $fechaInicio = Carbon::parse($horario->fechaInicial);
                        $fechaFin = Carbon::parse($horario->fechaFinal);
                        $fechaTemp = $fechaInicio->copy();

                        while ($fechaTemp->lte($fechaFin)) {
                            $fechaHoraInicio = $fechaTemp->copy();
                            $fechaHoraFin = $fechaTemp->copy();

                            if ($horario->horaInicial) {
                                $hi = Carbon::parse($horario->horaInicial);
                                $fechaHoraInicio->setTime($hi->hour, $hi->minute, 0);
                            }
                            if ($horario->horaFinal) {
                                $hf = Carbon::parse($horario->horaFinal);
                                $fechaHoraFin->setTime($hf->hour, $hf->minute, 0);
                            }

                            $estado = 'PENDIENTE';
                            if ($ahora->gte($fechaHoraFin)) {
                                $estado = 'COMPLETADA';
                            } elseif ($ahora->gte($fechaHoraInicio) && $ahora->lt($fechaHoraFin)) {
                                $estado = 'EN_CURSO';
                            }

                            $todasLasFechasSesiones[] = [
                                'fecha' => $fechaTemp->format('Y-m-d'),
                                'horaInicial' => $horario->horaInicial ?? '',
                                'horaFinal' => $horario->horaFinal ?? '',
                                'idHorarioMateria' => (int) $horario->idHorarioMateria,
                                'idDia' => (int) ($horario->idDia ?? 0),
                                'estado' => $estado,
                                'numeroSesion' => $sesionNum++,
                            ];

                            $fechaTemp->addWeek();
                        }
                    }
                }

                // — Asignar número de sesión y estado final —
                $proximaSesionEncontrada = false;
                foreach ($todasLasFechasSesiones as $index => &$sesion) {
                    $sesion['numeroSesion'] = $index + 1;

                    // Solo calcular estado si no viene ya del fallback
                    if (!isset($sesion['fechaHoraInicioCarbon'])) {
                        // Sesión generada por fallback; estado ya asignado, saltar
                        continue;
                    }

                    $fechaHoraInicio = $sesion['fechaHoraInicioCarbon'];
                    $fechaHoraFin = $sesion['fechaHoraFinCarbon'];

                    $estadoSesion = 'PENDIENTE';
                    if ($ahora->gte($fechaHoraFin)) {
                        $estadoSesion = 'COMPLETADA';
                    } elseif ($ahora->gte($fechaHoraInicio) && $ahora->lt($fechaHoraFin)) {
                        $estadoSesion = 'EN_CURSO';
                    } elseif (!$proximaSesionEncontrada && $ahora->lt($fechaHoraInicio)) {
                        $estadoSesion = 'PROXIMO';
                        $proximaSesionEncontrada = true;
                    }

                    $sesion['estado'] = $estadoSesion;
                    unset($sesion['fechaHoraInicioCarbon'], $sesion['fechaHoraFinCarbon']);
                }
                unset($sesion);

                // — Garantizar campos mínimos en cada sesión —
                foreach ($todasLasFechasSesiones as &$sesion) {
                    $sesion['fecha'] = $sesion['fecha'] ?? '';
                    $sesion['horaInicial'] = $sesion['horaInicial'] ?? '';
                    $sesion['horaFinal'] = $sesion['horaFinal'] ?? '';
                    $sesion['idHorarioMateria'] = (int) ($sesion['idHorarioMateria'] ?? 0);
                    $sesion['estado'] = $sesion['estado'] ?? 'PENDIENTE';
                    $sesion['numeroSesion'] = (int) ($sesion['numeroSesion'] ?? 0);

                    // Buscar si existe el id de sesionMateria real en base de datos para esta sesión
                    $idSesionReal = null;
                    foreach ($todasLasSesiones as $ts) {
                        if ($ts['idHorarioMateria'] === $sesion['idHorarioMateria'] && $ts['fechaSesion'] === $sesion['fecha']) {
                            $idSesionReal = (int) $ts['id'];
                            break;
                        }
                    }
                    $sesion['idSesionMateria'] = $idSesionReal;

                    // Si ya existe la sesión real, verificar si el estudiante actual ya la calificó
                    $yaCalificada = false;
                    $calificacionInfo = null;
                    if ($idSesionReal) {
                        $matriculaAcademica = \App\Models\MatriculaAcademica::whereHas('matricula', function($q) use ($idPersona) {
                            $q->where('idPersona', $idPersona);
                        })
                        ->where('idFicha', $sesion['idHorarioMateria'] ? \DB::table('horarioMateria')->where('id', $sesion['idHorarioMateria'])->value('idFicha') : null)
                        ->where('idMateria', (int) $primerHorario->idMateria)
                        ->first();

                        if ($matriculaAcademica) {
                            $califDb = \DB::table('calificacionSesiones')
                                ->where('idSesionMateria', $idSesionReal)
                                ->where('idMatriculaAcademica', $matriculaAcademica->id)
                                ->first();
                            
                            if ($califDb) {
                                $yaCalificada = true;
                                $calificacionInfo = [
                                    'id' => $califDb->id,
                                    'estrellas' => $califDb->estrellas,
                                    'comentarios' => $califDb->comentarios,
                                ];
                            }
                        }
                    }
                    $sesion['yaCalificada'] = $yaCalificada;
                    $sesion['calificacionInfo'] = $calificacionInfo;
                }
                unset($sesion);

                // — Calcular porcentaje —
                $porcentajeCompletado = $totalSesiones > 0
                    ? (float) round(($sesionesCompletadas / $totalSesiones) * 100, 0)
                    : 0.0;

                // — Aulas únicas —
                $aulasUnicas = $horariosUnicos->pluck('aula_nombre')->filter()->unique()->values();
                $aulaNombre = $aulasUnicas->count() > 1
                    ? $aulasUnicas->implode(', ')
                    : ($primerHorario->aula_nombre ?? 'Sin aula asignada');

                // — IDs de horarios —
                $idHorariosMateria = $horariosUnicos
                    ->pluck('idHorarioMateria')
                    ->map(fn($v) => (int) $v)
                    ->values()
                    ->toArray();

                // — Respuesta final con tipos forzados —
                $materias[] = [
                    'idMateria' => (int) $primerHorario->idMateria,
                    'materia_nombre' => (string) ($primerHorario->materia_nombre ?? ''),
                    'ficha_codigo' => (string) ($primerHorario->ficha_codigo ?? ''),
                    'profesor_nombre' => (string) trim($primerHorario->profesor_nombre ?? 'Sin asignar'),
                    'profesor_email' => (string) ($primerHorario->profesor_email ?? ''),
                    'aula_nombre' => (string) $aulaNombre,
                    'horario_texto' => (string) $horarioTexto,
                    'horarios' => $diasHorarios,
                    'total_sesiones' => (int) $totalSesiones,
                    'sesiones_completadas' => (int) $sesionesCompletadas,
                    'sesiones_restantes' => (int) max(0, $totalSesiones - $sesionesCompletadas),
                    'porcentaje_completado' => (float) $porcentajeCompletado,
                    'sesiones' => array_values($todasLasFechasSesiones),
                    'idHorariosMateria' => $idHorariosMateria,
                ];
            }

            return response()->json([
                'message' => 'Clases del estudiante obtenidas correctamente',
                'data' => $materias,
                'total' => (int) count($materias)
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener las clases del estudiante',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    private function parseHora(string $hora): ?Carbon
    {
        if (empty($hora)) {
            return null;
        }

        // Intentar diferentes formatos
        $formatos = ['H:i:s', 'H:i'];

        foreach ($formatos as $formato) {
            try {
                $parsed = Carbon::createFromFormat($formato, $hora);
                if ($parsed) {
                    return $parsed;
                }
            } catch (\Exception $e) {
                continue;
            }
        }

        // Si no funciona, intentar parse genérico
        try {
            return Carbon::parse($hora);
        } catch (\Exception $e) {
            return null;
        }
    }

    // ──────────────────────────────────────────────────────────────
    // Modalidad RAP: compartido / reemplazo  (solo Mis formaciones + detalle)
    // ──────────────────────────────────────────────────────────────

    private function idsHorarioMateriaMismoSlot(int $idHorarioMateria): array
    {
        $hm = DB::table('horarioMateria')->where('id', $idHorarioMateria)->first();
        if (!$hm)
            return [$idHorarioMateria];

        return DB::table('horarioMateria')
            ->where('idFicha', $hm->idFicha)
            ->where('idGradoMateria', $hm->idGradoMateria)
            ->where('idDia', $hm->idDia)
            ->where('horaInicial', $hm->horaInicial)
            ->where('horaFinal', $hm->horaFinal)
            ->pluck('id')
            ->map(fn($id) => (int) $id)
            ->unique()->values()->all();
    }

    private function reemplazosVigentesEnSlot(array $slotIds, ?Carbon $ref = null): \Illuminate\Support\Collection
    {
        if (!Schema::hasTable('asignacionSesion') || empty($slotIds)) {
            return collect();
        }

        $ref = $ref ?? Carbon::today();

        return DB::table('asignacionSesion')
            ->whereIn('idHorarioMateria', $slotIds)
            ->where('tipoAsignacion', 'REEMPLAZO')
            ->whereDate('fechaInicio', '<=', $ref)
            ->whereDate('fechaFin', '>=', $ref)
            ->get();
    }

    private function horariosCompartidosVigentesEnSlot(array $slotIds, ?Carbon $ref = null): \Illuminate\Support\Collection
    {
        if (!Schema::hasTable('asignacionSesion') || empty($slotIds)) {
            return collect();
        }

        $ref = $ref ?? Carbon::today();

        return DB::table('asignacionSesion')
            ->whereIn('idHorarioMateria', $slotIds)
            ->where('tipoAsignacion', 'HORARIO COMPARTIDO')
            ->whereDate('fechaInicio', '<=', $ref)
            ->whereDate('fechaFin', '>=', $ref)
            ->get();
    }

    /** Clave lógica de sesión (misma franja ficha+día+horas): evita duplicar titular/clon compartido. */
    private function claveLogicaSesionHistorial(
        int $fichaId,
        int $idDia,
        string $horaInicial,
        string $horaFinal,
        string $fechaYmd,
        int $numeroSesion
    ): string {
        $hi = substr((string) $horaInicial, 0, 5);
        $hf = substr((string) $horaFinal, 0, 5);

        return implode('|', [$fichaId, $idDia, $hi, $hf, $fechaYmd, $numeroSesion]);
    }

    private function contratosActivosEnSlot(array $slotIds): array
    {
        if (empty($slotIds))
            return [];
        return DB::table('horarioMateria')
            ->whereIn('id', $slotIds)
            ->whereNotNull('idContrato')
            ->pluck('idContrato')
            ->map(fn($id) => (int) $id)
            ->unique()->values()->all();
    }

    private function instructoresRapPayload(array $contratoIdsConRol): array
    {
        $ids = array_values(array_unique(array_filter(array_map(
            fn($item) => (int) ($item['id'] ?? 0),
            $contratoIdsConRol
        ))));
        if (empty($ids))
            return [];

        $rows = DB::table('contrato as c')
            ->join('persona as per', 'c.idpersona', '=', 'per.id')
            ->whereIn('c.id', $ids)
            ->select([
                'c.id as idContrato',
                DB::raw("TRIM(CONCAT(COALESCE(per.nombre1,''), ' ', COALESCE(per.nombre2,''), ' ', COALESCE(per.apellido1,''), ' ', COALESCE(per.apellido2,''))) as nombre"),
                'per.rutaFoto as rutaFotoUrl',
            ])
            ->get()->keyBy('idContrato');

        $out = [];
        foreach ($contratoIdsConRol as $item) {
            $id = (int) ($item['id'] ?? 0);
            if ($id <= 0 || !isset($rows[$id]))
                continue;
            $r = $rows[$id];
            $out[] = [
                'idContrato' => $id,
                'nombre' => trim((string) ($r->nombre ?? '')) ?: 'Instructor',
                'rutaFotoUrl' => $r->rutaFotoUrl ?? null,
                'rol' => (string) ($item['rol'] ?? 'titular'),
            ];
        }
        return $out;
    }

    private function resolverModalidadRap(int $idHorarioMateria, ?int $idContratoVista, ?string $fechaReferenciaYmd = null): array
    {
        $default = [
            'tipo_asignacion' => null,
            'modalidad_rap' => 'TITULAR',
            'asignacion_vigente' => false,
            'asignacion_fecha_inicio' => null,
            'asignacion_fecha_fin' => null,
            'reemplazo_vigente_por_otro' => false,
            'es_reemplazante' => false,
            'instructores_rap' => [],
        ];

        $hm = DB::table('horarioMateria')->where('id', $idHorarioMateria)->first();
        if (!$hm)
            return $default;

        $ref = $fechaReferenciaYmd
            ? Carbon::parse($fechaReferenciaYmd)->startOfDay()
            : Carbon::today();

        $horarioContratoId = $hm->idContrato ? (int) $hm->idContrato : null;
        $slotIds = $this->idsHorarioMateriaMismoSlot($idHorarioMateria);
        $reemplazosVigentes = $this->reemplazosVigentesEnSlot($slotIds, $ref);
        $compartidosVigentes = $this->horariosCompartidosVigentesEnSlot($slotIds, $ref);
        $contratosSlot = $this->contratosActivosEnSlot($slotIds);

        $titularId = $horarioContratoId ?: (
            DB::table('horarioMateria')->whereIn('id', $slotIds)
                ->whereNotNull('idContrato')->orderBy('id')->value('idContrato')
            ? (int) DB::table('horarioMateria')->whereIn('id', $slotIds)
                ->whereNotNull('idContrato')->orderBy('id')->value('idContrato')
            : null
        );

        $reemplazo = $reemplazosVigentes->first();
        if ($reemplazo && $reemplazo->idContrato) {
            $idReemplazante = (int) $reemplazo->idContrato;
            return [
                'tipo_asignacion' => 'REEMPLAZO',
                'modalidad_rap' => ($idContratoVista !== null && $idContratoVista === $idReemplazante) ? 'REEMPLAZO' : 'TITULAR',
                'asignacion_vigente' => true,
                'asignacion_fecha_inicio' => $reemplazo->fechaInicio ?? null,
                'asignacion_fecha_fin' => $reemplazo->fechaFin ?? null,
                'reemplazo_vigente_por_otro' => $titularId && $idContratoVista !== null && $idContratoVista === $titularId && $idReemplazante !== $titularId,
                'es_reemplazante' => $idContratoVista !== null && $idContratoVista === $idReemplazante,
                'instructores_rap' => $this->instructoresRapPayload(array_filter([
                    ['id' => $idReemplazante, 'rol' => 'reemplazante'],
                    $titularId && $titularId !== $idReemplazante ? ['id' => $titularId, 'rol' => 'titular'] : null,
                ])),
            ];
        }

        if ($compartidosVigentes->isNotEmpty() || count($contratosSlot) > 1) {
            $roles = [];
            foreach ($contratosSlot as $cid) {
                $roles[] = ['id' => $cid, 'rol' => 'compartido'];
            }
            foreach ($compartidosVigentes as $compartido) {
                $cid = (int) ($compartido->idContrato ?? 0);
                if ($cid > 0 && !in_array($cid, $contratosSlot, true)) {
                    $roles[] = ['id' => $cid, 'rol' => 'compartido'];
                }
            }

            $contratosUnicos = [];
            foreach ($roles as $rol) {
                $id = (int) ($rol['id'] ?? 0);
                if ($id > 0) {
                    $contratosUnicos[$id] = true;
                }
            }

            // Compartido solo si hay al menos dos instructores distintos en el cupo.
            if (count($contratosUnicos) >= 2) {
                $first = $compartidosVigentes->first();

                return [
                    'tipo_asignacion' => $compartidosVigentes->isNotEmpty() ? 'HORARIO COMPARTIDO' : null,
                    'modalidad_rap' => 'COMPARTIDO',
                    'asignacion_vigente' => true,
                    'asignacion_fecha_inicio' => $first?->fechaInicio ?? null,
                    'asignacion_fecha_fin' => $first?->fechaFin ?? null,
                    'reemplazo_vigente_por_otro' => false,
                    'es_reemplazante' => false,
                    'instructores_rap' => $this->instructoresRapPayload($roles),
                ];
            }

            // Hay fila de horario compartido pero aún sin co-instructor → no mostrar badge Compartido.
            if ($compartidosVigentes->isNotEmpty()) {
                $first = $compartidosVigentes->first();
                $cidTitular = $titularId ?: $horarioContratoId ?: $idContratoVista;

                return array_merge($default, [
                    'tipo_asignacion' => 'HORARIO COMPARTIDO',
                    'asignacion_vigente' => true,
                    'asignacion_fecha_inicio' => $first?->fechaInicio ?? null,
                    'asignacion_fecha_fin' => $first?->fechaFin ?? null,
                    'instructores_rap' => $cidTitular
                        ? $this->instructoresRapPayload([['id' => (int) $cidTitular, 'rol' => 'titular']])
                        : [],
                ]);
            }
        }

        $cid = $horarioContratoId ?: $idContratoVista;
        return array_merge($default, [
            'instructores_rap' => $cid ? $this->instructoresRapPayload([['id' => (int) $cid, 'rol' => 'titular']]) : [],
        ]);
    }
    public function asignarProyectoFormativo(Request $request, $idFicha)
    {
        $request->validate([
            'idProyectoFormativo' => 'required|integer'
        ]);

        $ficha = Ficha::findOrFail($idFicha);

        $ficha->idProyectoFormativo = $request->idProyectoFormativo;
        $ficha->save();

        return response()->json([
            'message' => 'Proyecto formativo asignado correctamente.',
            'data' => $ficha
        ]);
    }
    public function getProyectoByFicha(int $fichaId): JsonResponse
    {
        $ficha = Ficha::with([
            'proyectoFormativo.fases.actividades.faseProyectoRaps.materia',
            'proyectoFormativo.fases.actividades.faseProyectoRaps.faseProyectoMaterias.materia',
            'proyectoFormativo.fases.faseProyectoRaps.materia',
            'proyectoFormativo.fases.faseProyectoRaps.faseProyectoMaterias.materia',
            'aperturarPrograma'
        ])->findOrFail($fichaId);

        if (!$ficha->idProyectoFormativo) {
            return response()->json([
                'message' => 'La ficha no tiene un proyecto formativo asignado.',
            ], 404);
        }

        $proyecto = $ficha->proyectoFormativo;
        $idPrograma = $ficha->aperturarPrograma->idPrograma ?? null;
        $porcentajeEjecucion = $ficha->porcentajeEjecucion ?? 100;

        // Matriculas para estados
        $matriculasFicha = \App\Models\MatriculaAcademica::where('idFicha', $fichaId)
            ->select('idMateria', 'estado')
            ->get()
            ->groupBy('idMateria');

        // Horarios para sesiones, instructores, fechas, etc.
        $horarios = HorarioMateria::where('idFicha', $fichaId)
            ->with([
                'gradoMateria.materia',
                'gradoMateria.gradoPrograma.grado',
                'contrato.persona:id,nombre1,nombre2,apellido1,apellido2,rutaFoto,email',
            ])
            ->withCount([
                'sesionMaterias as sesiones_realizadas_count' => function ($q) {
                    $q->whereNotNull('fechaSesion');
                }
            ])
            ->get();

        // Para las horasPrograma
        $horasPorMateria = [];
        if ($idPrograma) {
            $horasPorMateria = \Illuminate\Support\Facades\DB::table('agregarMateriaPrograma')
                ->where('idPrograma', $idPrograma)
                ->pluck('horas', 'idMateria')
                ->toArray();
        }

        // IDs de materias permitidas en este programa
        $materiasDelPrograma = $idPrograma
            ? \Illuminate\Support\Facades\DB::table('agregarMateriaPrograma')
                ->where('idPrograma', $idPrograma)
                ->pluck('idMateria')
                ->toArray()
            : [];

        // Mapa idMateria => datos agregados
        $datosPorMateria = $horarios
            ->groupBy(fn($h) => $h->gradoMateria->idMateria ?? 0)
            ->map(function ($grupo) {
                $instructores = $grupo
                    ->pluck('contrato.persona')
                    ->filter()
                    ->unique('id')
                    ->values()
                    ->map(function ($persona) {
                        return [
                            'id' => $persona->id,
                            'nombre' => trim("{$persona->nombre1} {$persona->nombre2} {$persona->apellido1} {$persona->apellido2}"),
                            'email' => $persona->email,
                            'rutaFoto' => $persona->rutaFoto,
                        ];
                    });

                $trimestres = $grupo
                    ->pluck('gradoMateria.gradoPrograma.grado.numeroGrado')
                    ->filter()
                    ->unique()
                    ->values()
                    ->implode(', ');

                $fechaInicio = $grupo->pluck('fechaInicial')->filter()->min();
                $fechaFin = $grupo->pluck('fechaFinal')->filter()->max();
                $numeroSesiones = $grupo->sum('sesiones_realizadas_count');

                $horasActuales = 0;
                foreach ($grupo as $horario) {
                    if ($horario->horaInicial && $horario->horaFinal) {
                        $hI = \Carbon\Carbon::parse($horario->horaInicial);
                        $hF = \Carbon\Carbon::parse($horario->horaFinal);
                        $duracionSesion = $hF->diffInMinutes($hI, true) / 60;
                        $sesionesDadas = $horario->sesiones_realizadas_count ?? 0;
                        $horasActuales += $sesionesDadas * $duracionSesion;
                    }
                }

                return [
                    'instructores' => $instructores,
                    'trimestre' => $trimestres,
                    'fechaInicio' => $fechaInicio ? \Carbon\Carbon::parse($fechaInicio)->format('Y-m-d') : null,
                    'fechaFin' => $fechaFin ? \Carbon\Carbon::parse($fechaFin)->format('Y-m-d') : null,
                    'numeroSesiones' => $numeroSesiones,
                    'horasActuales' => round($horasActuales, 2),
                ];
            });

        $formatearRap = function ($rap) use ($datosPorMateria, $matriculasFicha, $horasPorMateria, $materiasDelPrograma) {
            $materia = $rap->materia;

            // Obtener datos de la materia actual
            $datos = $datosPorMateria->get($materia->id, [
                'instructores' => collect(),
                'trimestre' => '',
                'fechaInicio' => null,
                'fechaFin' => null,
                'numeroSesiones' => 0,
                'horasActuales' => 0
            ]);

            $horasPrograma = $horasPorMateria[$materia->id] ?? ($materia->horas ?? 0);

            // Obtener las hijas de este FaseProyectoRap
            $hijasDelPrograma = $rap->faseProyectoMaterias
                ->map(fn($fpm) => $fpm->materia)
                ->filter(fn($hija) => in_array($hija->id, $materiasDelPrograma));

            // Calcular estado de la materia actual
            $aprobadoCount = $matriculasFicha->get($materia->id, collect())
                ->filter(fn($m) => strtoupper(trim($m->estado ?? '')) === 'APROBADO')
                ->count();

            $estaFinalizado = $aprobadoCount >= 5;

            // ... (dentro de $formatearRap en tu controlador PHP)
            if ($hijasDelPrograma->isNotEmpty()) {
                $materiaData['hijas'] = $hijasDelPrograma
                    ->map(function ($hija) use ($datosPorMateria, $matriculasFicha, $horasPorMateria, $datos) { // <-- Pasamos $datos del padre
                        $datosHija = $datosPorMateria->get($hija->id, [
                            'instructores' => collect(),
                            'trimestre' => '',
                            'fechaInicio' => null,
                            'fechaFin' => null,
                            'numeroSesiones' => 0,
                            'horasActuales' => 0
                        ]);

                        // SI LA HIJA NO TIENE INSTRUCTORES, HEREDA LOS DEL PADRE
                        $instructoresHija = $datosHija['instructores']->isNotEmpty()
                            ? $datosHija['instructores']
                            : $datos['instructores'];

                        $horasProgramaHija = $horasPorMateria[$hija->id] ?? ($hija->horas ?? 0);
                        $aprobadoCountHija = $matriculasFicha->get($hija->id, collect())
                            ->filter(fn($m) => strtoupper(trim($m->estado ?? '')) === 'APROBADO')
                            ->count();

                        $estaFinalizadoHija = $aprobadoCountHija >= 5;

                        return [
                            'id' => $hija->id,
                            'nombre' => $hija->nombreMateria ?? $hija->descripcion ?? null,
                            'instructores' => is_array($instructoresHija) ? $instructoresHija : $instructoresHija->toArray(),
                            'trimestre' => $datosHija['trimestre'] ?: $datos['trimestre'],
                            'fechaInicio' => $datosHija['fechaInicio'],
                            'fechaFin' => $datosHija['fechaFin'],
                            'numeroSesiones' => $datosHija['numeroSesiones'],
                            'horasActuales' => $datosHija['horasActuales'],
                            'horas' => $horasProgramaHija,
                            'estado' => $estaFinalizadoHija ? 'APROBADO' : 'POR EVALUAR',
                        ];
                    })
                    ->values()
                    ->toArray();
            }

            // Formatear datos de la materia actual
            $materiaData = [
                'id' => $materia->id,
                'nombre' => $materia->nombreMateria ?? $materia->descripcion ?? null,
                'instructores' => is_array($datos['instructores']) ? $datos['instructores'] : $datos['instructores']->toArray(),
                'trimestre' => $datos['trimestre'],
                'fechaInicio' => $datos['fechaInicio'],
                'fechaFin' => $datos['fechaFin'],
                'numeroSesiones' => $datos['numeroSesiones'],
                'horasActuales' => $datos['horasActuales'],
                'horas' => $horasPrograma,
                'estado' => $estaFinalizado ? 'APROBADO' : 'POR EVALUAR',
            ];

            // Formatear hijas (sin recursión)
            if ($hijasDelPrograma->isNotEmpty()) {
                $materiaData['hijas'] = $hijasDelPrograma
                    ->map(function ($hija) use ($datosPorMateria, $matriculasFicha, $horasPorMateria, $materiasDelPrograma) {
                        $datosHija = $datosPorMateria->get($hija->id, [
                            'instructores' => collect(),
                            'trimestre' => '',
                            'fechaInicio' => null,
                            'fechaFin' => null,
                            'numeroSesiones' => 0,
                            'horasActuales' => 0
                        ]);

                        $horasProgramaHija = $horasPorMateria[$hija->id] ?? ($hija->horas ?? 0);

                        $aprobadoCountHija = $matriculasFicha->get($hija->id, collect())
                            ->filter(fn($m) => strtoupper(trim($m->estado ?? '')) === 'APROBADO')
                            ->count();

                        $estaFinalizadoHija = $aprobadoCountHija >= 5;

                        return [
                            'id' => $hija->id,
                            'nombre' => $hija->nombreMateria ?? $hija->descripcion ?? null,
                            'instructores' => is_array($datosHija['instructores']) ? $datosHija['instructores'] : $datosHija['instructores']->toArray(),
                            'trimestre' => $datosHija['trimestre'],
                            'fechaInicio' => $datosHija['fechaInicio'],
                            'fechaFin' => $datosHija['fechaFin'],
                            'numeroSesiones' => $datosHija['numeroSesiones'],
                            'horasActuales' => $datosHija['horasActuales'],
                            'horas' => $horasProgramaHija,
                            'estado' => $estaFinalizadoHija ? 'APROBADO' : 'POR EVALUAR',
                        ];
                    })
                    ->values()
                    ->toArray();
            }

            return [
                'id' => $rap->id,
                'materia' => $materiaData,
                'idMateriaPadre' => $materia->idMateriaPadre,
                'instructores' => $materiaData['instructores'],
                'trimestre' => $materiaData['trimestre'],
                'fechaInicio' => $materiaData['fechaInicio'],
                'fechaFin' => $materiaData['fechaFin'],
                'numeroSesiones' => $materiaData['numeroSesiones'],
                'horasActuales' => $materiaData['horasActuales'],
                'horas' => $materiaData['horas'],
                'estado' => $materiaData['estado'],
            ];
        };

        $fasesFormateadas = $proyecto->fases->map(function ($fase) use ($formatearRap) {
            $actividadesFormateadas = $fase->actividades->map(function ($actividad) use ($formatearRap) {
                return [
                    'id' => $actividad->id,
                    'descripcionActividad' => $actividad->descripcionActividad,
                    'faseProyectoRap' => $actividad->faseProyectoRaps->map($formatearRap),
                ];
            });

            $rapsSinActividad = $fase->faseProyectoRaps
                ->whereNull('idActividadProyecto')
                ->map($formatearRap)
                ->values();

            return [
                'id' => $fase->id,
                'descripcionFase' => $fase->descripcionFase,
                'actividades' => $actividadesFormateadas,
                'rapsGenerales' => $rapsSinActividad,
            ];
        });

        return response()->json([
            'proyectoFormativo' => [
                'id' => $proyecto->id,
                'nombre' => $proyecto->nombre ?? $proyecto->descripcion ?? null,
            ],
            'fasesProyecto' => $fasesFormateadas,
        ]);
    }
}
