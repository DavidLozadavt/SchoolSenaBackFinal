<?php

namespace App\Http\Controllers;

use App\Models\Ficha;
use App\Models\AperturarPrograma;
use App\Models\Company;
use App\Models\Contract;
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
            $oldCodigo   = $ficha->codigo;
            $oldSede     = $ficha->idSede;
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
                $sedeNueva     = Sede::findOrFail($validated['idSede']);
                $programaNuevo = Programa::findOrFail($validated['idPrograma']);

                $sedeName     = $sanitize($sedeNueva->nombre);
                $programaName = $sanitize($programaNuevo->nombrePrograma);
                $codigoFicha  = $validated['codigo'];

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
                ->where('c.id', $idInstructor)
                ->whereNotNull('hm.idDia')
                ->whereNotNull('hm.horaInicial')
                ->whereNotNull('hm.horaFinal')
                ->groupBy([
                    'f.id',
                    'f.codigo',
                    'p.nombrePrograma',
                    'm.nombreMateria',
                    'j.nombreJornada',
                    'd.dia',
                    'hm.horaInicial',
                    'hm.horaFinal',
                    'hm.fechaInicial',
                    'hm.fechaFinal',
                    'hm.idDia',
                    'c.id',
                    'per.nombre1',
                    'per.apellido1',
                    'gp.id',
                    'g.nombreGrado',
                    'hm.id',
                    'hm.idGradoMateria',
                    'gm.idMateria'
                ])
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
                
                // Sincronizar sesiones completadas automáticamente (crear registros en BD si pasaron)
                $this->sincronizarSesionesCompletadas(
                    $clase->idHorarioMateria,
                    $clase->fechaInicial,
                    $clase->fechaFinal,
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
                
                return $clase;
            });

            // Logs temporales para depuración
            Log::info('=== CLASES ASIGNADAS DEL INSTRUCTOR ===', [
                'idInstructor' => $idInstructor,
                'total_clases' => $clases->count(),
                'clases' => $clases->map(function ($clase) {
                    return [
                        'idHorarioMateria' => $clase->idHorarioMateria,
                        'materia_nombre' => $clase->materia_nombre,
                        'fechaInicial' => $clase->fechaInicial,
                        'horaInicial' => $clase->horaInicial,
                        'horaFinal' => $clase->horaFinal,
                        'jornada_tipo' => $clase->jornada_tipo,
                        'ficha_id' => $clase->ficha_id,
                        'ficha_codigo' => $clase->ficha_codigo,
                        'contrato_id' => $clase->contrato_id,
                        'estado' => $clase->estado,
                        'dia_semana' => $clase->dia_semana ?? null
                    ];
                })->toArray()
            ]);

            // Log adicional: verificar clases con la misma fecha pero diferentes horarios
            $clasesPorFecha = $clases->groupBy('fechaInicial');
            foreach ($clasesPorFecha as $fecha => $clasesFecha) {
                if ($clasesFecha->count() > 1) {
                    Log::info("=== MÚLTIPLES CLASES EN FECHA: {$fecha} ===", [
                        'total' => $clasesFecha->count(),
                        'clases' => $clasesFecha->map(function ($clase) {
                            return [
                                'idHorarioMateria' => $clase->idHorarioMateria,
                                'materia_nombre' => $clase->materia_nombre,
                                'horaInicial' => $clase->horaInicial,
                                'horaFinal' => $clase->horaFinal,
                                'jornada_tipo' => $clase->jornada_tipo,
                                'ficha_id' => $clase->ficha_id
                            ];
                        })->toArray()
                    ]);
                }
            }

            // Log adicional: verificar clases que NO tienen el instructor asignado
            $clasesSinInstructor = DB::table('horarioMateria as hm')
                ->select([
                    'hm.id as idHorarioMateria',
                    'hm.fechaInicial',
                    'hm.horaInicial',
                    'hm.idContrato',
                    'hm.idFicha'
                ])
                ->leftJoin('contrato as c', 'hm.idContrato', '=', 'c.id')
                ->whereNotNull('hm.idDia')
                ->whereNotNull('hm.horaInicial')
                ->whereNotNull('hm.horaFinal')
                ->where(function ($query) use ($idInstructor) {
                    $query->whereNull('hm.idContrato')
                        ->orWhere('hm.idContrato', '!=', $idInstructor);
                })
                ->whereIn('hm.fechaInicial', $clases->pluck('fechaInicial')->unique())
                ->get();

            if ($clasesSinInstructor->count() > 0) {
                Log::info('=== CLASES CON LA MISMA FECHA PERO SIN ESTE INSTRUCTOR ===', [
                    'total' => $clasesSinInstructor->count(),
                    'clases' => $clasesSinInstructor->map(function ($clase) {
                        return [
                            'idHorarioMateria' => $clase->idHorarioMateria,
                            'fechaInicial' => $clase->fechaInicial,
                            'horaInicial' => $clase->horaInicial,
                            'idContrato' => $clase->idContrato,
                            'idFicha' => $clase->idFicha
                        ];
                    })->toArray()
                ]);
            }

            // Log adicional: verificar si hay clases con la misma fecha pero diferentes horarios
            $clasesPorFecha = $clases->groupBy('fechaInicial');
            foreach ($clasesPorFecha as $fecha => $clasesFecha) {
                if ($clasesFecha->count() > 1) {
                    Log::info("=== MÚLTIPLES CLASES EN FECHA: {$fecha} ===", [
                        'total' => $clasesFecha->count(),
                        'clases' => $clasesFecha->map(function ($clase) {
                            return [
                                'idHorarioMateria' => $clase->idHorarioMateria,
                                'materia_nombre' => $clase->materia_nombre,
                                'horaInicial' => $clase->horaInicial,
                                'horaFinal' => $clase->horaFinal,
                                'jornada_tipo' => $clase->jornada_tipo,
                                'ficha_id' => $clase->ficha_id
                            ];
                        })->toArray()
                    ]);
                }
            }

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
                ->groupBy([
                    'f.id',
                    'f.codigo',
                    'p.nombrePrograma',
                    'm.nombreMateria',
                    'j.nombreJornada',
                    'd.dia',
                    'hm.horaInicial',
                    'hm.horaFinal',
                    'hm.fechaInicial',
                    'hm.fechaFinal',
                    'hm.idDia',
                    'c.id',
                    'per.nombre1',
                    'per.apellido1',
                    'gp.id',
                    'g.nombreGrado',
                    'hm.id',
                    'hm.idGradoMateria',
                    'gm.idMateria'
                ])
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
                
                // Sincronizar sesiones completadas automáticamente (crear registros en BD si pasaron)
                $this->sincronizarSesionesCompletadas(
                    $clase->idHorarioMateria,
                    $clase->fechaInicial,
                    $clase->fechaFinal,
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
            $claseData = DB::table('horarioMateria as hm')
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
                    'g.id as idGrado',
                    'hm.id as idHorarioMateria',
                    'gm.id as idGradoMateria',
                    'ap.fechaInicialClases',
                    'ap.fechaFinalClases',
                    'ap.id as idAsignacion'
                ])
                ->join('ficha as f', 'hm.idFicha', '=', 'f.id')
                ->join('jornadas as j', 'f.idJornada', '=', 'j.id')
                ->join('aperturarprograma as ap', 'f.idAsignacion', '=', 'ap.id')
                ->join('programa as p', 'ap.idPrograma', '=', 'p.id')
                ->join('gradoMateria as gm', 'hm.idGradoMateria', '=', 'gm.id')
                ->join('materia as m', 'gm.idMateria', '=', 'm.id')
                ->leftJoin('gradoPrograma as gp', 'gm.idGradoPrograma', '=', 'gp.id')
                ->leftJoin('grado as g', 'gp.idGrado', '=', 'g.id')
                ->leftJoin('dia as d', 'hm.idDia', '=', 'd.id')
                ->leftJoin('contrato as c', 'hm.idContrato', '=', 'c.id')
                ->leftJoin('persona as per', 'c.idpersona', '=', 'per.id')
                ->where('hm.id', $idHorarioMateria)
                ->groupBy([
                    'f.id',
                    'f.codigo',
                    'p.nombrePrograma',
                    'm.nombreMateria',
                    'j.nombreJornada',
                    'j.horaInicial',
                    'd.dia',
                    'hm.horaInicial',
                    'hm.horaFinal',
                    'hm.fechaInicial',
                    'hm.fechaFinal',
                    'hm.idDia',
                    'c.id',
                    'per.nombre1',
                    'per.apellido1',
                    'gp.id',
                    'g.nombreGrado',
                    'g.id',
                    'hm.id',
                    'gm.id',
                    'ap.fechaInicialClases',
                    'ap.fechaFinalClases',
                    'ap.id'
                ])
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
            
            // Sincronizar sesiones completadas automáticamente (crear registros en BD si pasaron)
            $this->sincronizarSesionesCompletadas(
                $idHorarioMateria,
                $claseData->fechaInicial,
                $claseData->fechaFinal,
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

            if (!$claseData) {
                return response()->json([
                    'message' => 'Clase no encontrada',
                    'error' => 'No existe una clase con el ID proporcionado'
                ], 404);
            }

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

            // Obtener TODAS las fechas de clase del INSTRUCTOR para el calendario
            // Esto permite que el instructor vea todas sus clases en el calendario, no solo la clase actual
            $todasLasFechasClase = DB::table('horarioMateria as hm')
                ->select([
                    'hm.id as idHorarioMateria',
                    'hm.fechaInicial',
                    'hm.fechaFinal',
                    'd.dia as dia_semana',
                    'hm.idDia'
                ])
                ->leftJoin('dia as d', 'hm.idDia', '=', 'd.id')
                ->where('hm.idContrato', $claseData->contrato_id) // Filtrar por instructor, no por horario específico
                ->whereNotNull('hm.fechaInicial')
                ->whereNotNull('d.dia')
                ->orderBy('hm.fechaInicial', 'asc')
                ->get();

            $apertura = AperturarPrograma::find($ficha->idAsignacion);

            // Obtener sesiones completadas con sus fechas específicas
            $sesionesCompletadas = $this->obtenerSesionesCompletadas($idHorarioMateria);

            return response()->json([
                'message' => 'Clase encontrada',
                'data' => [
                    'clase' => $claseDataArray,
                    'ficha' => $ficha,
                    'apertura' => $apertura,
                    'todasLasFechasClase' => $todasLasFechasClase,
                    'sesionesCompletadas' => $sesionesCompletadas
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
        if (!$fechaFinal) {
            return 0;
        }

        $inicio = Carbon::parse($fechaInicial);
        $fin = Carbon::parse($fechaFinal);
        
        // Convertir idDia a formato Carbon (0=Domingo, 1=Lunes, ..., 6=Sábado)
        // idDia BD: 1=Lunes, 2=Martes, 3=Miércoles, 4=Jueves, 5=Viernes, 6=Sábado, 7=Domingo
        $carbonDayOfWeek = $idDia === 7 ? 0 : $idDia;
        
        $totalSesiones = 0;
        $fechaActual = $inicio->copy();
        
        while ($fechaActual->lte($fin)) {
            if ($fechaActual->dayOfWeek === $carbonDayOfWeek) {
                $totalSesiones++;
            }
            $fechaActual->addDay();
        }
        
        return $totalSesiones;
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
        // Contar sesiones registradas en la base de datos con fechaSesion no nula
        $sesionesRegistradas = SesionMateria::where('idHorarioMateria', $idHorarioMateria)
            ->whereNotNull('fechaSesion')
            ->count();
        
        return $sesionesRegistradas;
    }

    /**
     * Sincroniza automáticamente las sesiones completadas en la base de datos
     * Crea registros en sesionMateria para las sesiones que ya pasaron su hora final
     * pero que aún no tienen registro en la BD
     * 
     * @param int $idHorarioMateria ID del horario de materia
     * @param string $fechaInicial Fecha inicial en formato Y-m-d
     * @param string|null $fechaFinal Fecha final en formato Y-m-d (puede ser null)
     * @param string $horaFinal Hora final (formato H:i:s)
     * @param int $idDia ID del día de la semana (1=Lunes, 2=Martes, ..., 7=Domingo)
     * @return void
     */
    private function sincronizarSesionesCompletadas(
        int $idHorarioMateria,
        string $fechaInicial,
        ?string $fechaFinal,
        string $horaFinal,
        int $idDia
    ): void {
        if (!$fechaFinal) {
            return;
        }

        try {
            $inicio = Carbon::parse($fechaInicial);
            $fin = Carbon::parse($fechaFinal);
            $ahora = Carbon::now();
            
            // Parsear hora final
            $horaFin = $this->parseHora($horaFinal);
            if (!$horaFin) {
                return;
            }
            
            // Convertir idDia a formato Carbon (0=Domingo, 1=Lunes, ..., 6=Sábado)
            $carbonDayOfWeek = $idDia === 7 ? 0 : $idDia;
            
            // Obtener todas las sesiones ya registradas para este horario
            $sesionesExistentes = SesionMateria::where('idHorarioMateria', $idHorarioMateria)
                ->whereNotNull('fechaSesion')
                ->pluck('fechaSesion')
                ->map(function ($fecha) {
                    return Carbon::parse($fecha)->format('Y-m-d');
                })
                ->toArray();
            
            // Obtener el último número de sesión registrado
            $ultimoNumeroSesion = SesionMateria::where('idHorarioMateria', $idHorarioMateria)
                ->max('numeroSesion') ?? 0;
            
            $fechaActual = $inicio->copy();
            $nuevasSesiones = [];
            
            // Iterar por todas las fechas posibles de clase
            while ($fechaActual->lte($fin)) {
                if ($fechaActual->dayOfWeek === $carbonDayOfWeek) {
                    $fechaSesionStr = $fechaActual->format('Y-m-d');
                    
                    // Crear fecha y hora completa de fin de esta sesión
                    $fechaHoraFinSesion = $fechaActual->copy()
                        ->setTime($horaFin->hour, $horaFin->minute, $horaFin->second);
                    
                    // Si la hora actual es mayor o igual a la hora final de esta sesión
                    // Y no existe un registro para esta fecha, crear uno
                    if ($ahora->gte($fechaHoraFinSesion) && !in_array($fechaSesionStr, $sesionesExistentes)) {
                        $ultimoNumeroSesion++;
                        $nuevasSesiones[] = [
                            'numeroSesion' => $ultimoNumeroSesion,
                            'idHorarioMateria' => $idHorarioMateria,
                            'fechaSesion' => $fechaSesionStr,
                            'estado' => EstadoSesionMateria::APLICA,
                            'created_at' => Carbon::now(),
                            'updated_at' => Carbon::now(),
                        ];
                    }
                }
                $fechaActual->addDay();
            }
            
            // Insertar todas las nuevas sesiones en una sola operación
            if (!empty($nuevasSesiones)) {
                SesionMateria::insert($nuevasSesiones);
                
                Log::info('Sesiones sincronizadas automáticamente', [
                    'idHorarioMateria' => $idHorarioMateria,
                    'sesiones_creadas' => count($nuevasSesiones),
                    'fechas' => array_column($nuevasSesiones, 'fechaSesion'),
                ]);
            }
        } catch (\Exception $e) {
            // Log del error pero no interrumpir el flujo principal
            Log::error('Error al sincronizar sesiones completadas', [
                'idHorarioMateria' => $idHorarioMateria,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
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
     * 
     * @param int $idHorarioMateria ID del horario de materia
     * @return array Array de sesiones completadas con fecha formateada
     */
    private function obtenerSesionesCompletadas(int $idHorarioMateria): array
    {
        $sesiones = SesionMateria::where('idHorarioMateria', $idHorarioMateria)
            ->whereNotNull('fechaSesion')
            ->orderBy('fechaSesion', 'asc')
            ->get();

        return $sesiones->map(function ($sesion) {
            $fecha = Carbon::parse($sesion->fechaSesion);
            return [
                'id' => $sesion->id,
                'numeroSesion' => $sesion->numeroSesion,
                'fechaSesion' => $sesion->fechaSesion,
                'fechaFormateada' => $fecha->locale('es')->isoFormat('dddd, D [de] MMMM [de] YYYY'),
                'fechaCorta' => $fecha->format('d/m/Y'),
                'estado' => $sesion->estado,
                'observacion' => $sesion->observacion,
            ];
        })->toArray();
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
}
