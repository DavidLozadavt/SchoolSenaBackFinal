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

            $ficha = Ficha::create([
                'idJornada' => $validated['idJornada'],
                'idAsignacion' => $apertura->id,
                'codigo' => $validated['codigo'],
                'idSede' => $validated['idSede'],
                'idInfraestructura' => $validated['idInfraestructura'] ?? null,
                'idRegional' => $validated['idRegional'],
                'porcentajeEjecucion' => 100,
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
            'instructorLider.persona:id,nombre1,nombre2,apellido1,apellido2,identificacion',
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
    public function fichasPorPrograma(int $idPrograma): JsonResponse
    {
        $fichas = Ficha::query()
            ->whereHas('asignacion', function ($query) use ($idPrograma) {
                $query->where('idPrograma', $idPrograma);
            })
            ->with([
                'jornada:id,nombreJornada',
                'sede:id,nombre',
                'regional:id,razonSocial',
                'asignacion:id,estado,fechaInicialClases,fechaFinalClases,idPrograma',
                'asignacion.programa:id,nombrePrograma',
                'instructorLider:id,idpersona',
                'instructorLider.persona:id,nombre1,nombre2,apellido1,apellido2,identificacion',
            ])
            ->orderBy('created_at', 'desc')
            ->get();


        return response()->json([
            'idPrograma' => $idPrograma,
            'total' => $fichas->count(),
            'data' => $fichas
        ]);
    }
<<<<<<< HEAD
    public function validarCodigo($codigo)
    {
        $existe = Ficha::where('codigo', $codigo)->exists();

        return response()->json([
            'codigo' => $codigo,
            'existe' => $existe
        ]);
    }
=======

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
                    'persona:id,nombre1,nombre2,apellido1,apellido2,identificacion',
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
                'instructorLider.persona:id,nombre1,nombre2,apellido1,apellido2,identificacion'
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
>>>>>>> 47140c11e232bcfe2fabad69f6adabe971edc8f4
}
