<?php

namespace App\Http\Controllers;

use App\Models\Ficha;
use App\Models\AperturarPrograma;
use App\Models\Contract;
use App\Models\Programa;
use App\Models\Status;
use App\Util\KeyUtil;
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

            $rutaDocumento = null;

            if ($request->hasFile('documento')) {
                $rutaDocumento = '/storage/' . $request->file('documento')
                    ->store('fichas/documentos', 'public');
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
                'sede:id,nombre',
                'regional:id,razonSocial',
                'asignacion:id,estado,fechaInicialClases,fechaFinalClases,idPrograma',
                'asignacion.programa:id,nombrePrograma',
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

            $rutaDocumento = $ficha->documento; // conservar el actual

            if ($request->hasFile('documento')) {
                // 🗑️ eliminar el anterior si existe
                if ($ficha->documento) {
                    $rutaAnterior = str_replace('/storage/', '', $ficha->documento);
                    Storage::disk('public')->delete($rutaAnterior);
                }

                // 📄 guardar el nuevo
                $rutaDocumento = '/storage/' . $request->file('documento')
                    ->store('fichas/documentos', 'public');
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
        if (str_contains($e->getMessage(), 'foreign key constraint') || 
            str_contains($e->getMessage(), 'Cannot delete or update a parent row')) {
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
}
