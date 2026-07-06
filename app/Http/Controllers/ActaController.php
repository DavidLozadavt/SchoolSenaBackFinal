<?php

namespace App\Http\Controllers;

use App\Models\Acta;
use App\Models\Ficha;
use App\Models\Person;
use App\Models\Contract;
use App\Models\NotificacionSistema;
use App\Models\User;
use App\Util\KeyUtil;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;


class ActaController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        try {
            $actas = Acta::with(['ciudad', 'ficha', 'contrato.persona', 'agenda', 'objetivos', 'asistencias.contrato.persona', 'asistencias.contrato.empresa', 'conclusiones', 'compromisos', 'anexos'])->get();
            return response()->json($actas);
        } catch (\Exception $e) {
            Log::error('Error al listar actas: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error al obtener las actas',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'fecha' => 'required|date',
            'horaInicio' => 'required',
            'horaFin' => 'required',
            'tipoActa' => 'required|string|max:255',
            'observacion' => 'nullable|string',
            'lugar' => 'nullable|string|max:255',
            'direccion' => 'nullable|string',
            'idCiudad' => 'required|exists:ciudad,id',
            'idFicha' => 'required|exists:ficha,id',
            'idContrato' => 'required|exists:contrato,id',
            'documento' => 'nullable|string',
            'fechaInicialFormacion' => 'sometimes|required|date',
            'fechaFinalFormacion' => 'sometimes|required|date',
            // Relaciones
            'agenda' => 'nullable|array',
            'agenda.*.punto' => 'required|string',
            'objetivos' => 'nullable|array',
            'objetivos.*.objetivo' => 'required|string',
            'asistencias' => 'nullable|array',
            'asistencias.*.idContrato' => 'required|exists:contrato,id',
            'asistencias.*.dependencia' => 'required|string',
            'asistencias.*.aprueba' => 'required|in:SI,NO',
            'asistencias.*.observacion' => 'nullable|string',
            'conclusiones' => 'nullable|array',
            'conclusiones.*.conclusion' => 'required|string',
            'compromisos' => 'nullable|array',
            'compromisos.*.actividad' => 'required|string',
            'compromisos.*.fecha' => 'required|date',
            'compromisos.*.responsable' => 'required|string',
            'anexos' => 'nullable|array',
            'anexos.*.nombre' => 'required|string',
            'anexos.*.archivo' => 'required|string',
            'anexos.*.descripcion' => 'nullable|string',
        ]);

        DB::beginTransaction();
        $asistenciasParaNotificar = [];
        try {
            $acta = Acta::create($validated);

            if (!empty($validated['agenda'])) {
                foreach ($validated['agenda'] as $item) {
                    $acta->agenda()->create($item);
                }
            }

            if (!empty($validated['objetivos'])) {
                foreach ($validated['objetivos'] as $item) {
                    $acta->objetivos()->create($item);
                }
            }

            if (!empty($validated['asistencias'])) {
                foreach ($validated['asistencias'] as $item) {
                    $acta->asistencias()->create($item);
                }
                // Guardar para notificar DESPUÉS del commit
                $asistenciasParaNotificar = $validated['asistencias'];
            }

            if (!empty($validated['conclusiones'])) {
                foreach ($validated['conclusiones'] as $item) {
                    $acta->conclusiones()->create($item);
                }
            }

            if (!empty($validated['compromisos'])) {
                foreach ($validated['compromisos'] as $item) {
                    $acta->compromisos()->create($item);
                }
            }

            if (!empty($validated['anexos'])) {
                foreach ($validated['anexos'] as $item) {
                    $acta->anexos()->create($item);
                }
            }

            DB::commit();

            // Enviar notificaciones DESPUÉS del commit, con su propio try-catch
            // para que un fallo en notificaciones no afecte la creación del acta
            if (!empty($asistenciasParaNotificar)) {
                try {
                    $this->notificarAsistentes($acta, $asistenciasParaNotificar);
                } catch (\Exception $notifEx) {
                    Log::error('Error al notificar asistentes del acta #' . $acta->id . ': ' . $notifEx->getMessage());
                }
            }

            return response()->json([
                'message' => 'Acta creada correctamente con todos sus detalles',
                'data' => $acta->load(['agenda', 'objetivos', 'asistencias', 'conclusiones', 'compromisos', 'anexos'])
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al crear acta con detalles: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error al crear el acta y sus detalles',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function show($id): JsonResponse
    {
        try {
            $acta = Acta::with(['ciudad', 'ficha', 'contrato.persona', 'agenda', 'objetivos', 'asistencias.contrato.persona', 'asistencias.contrato.empresa', 'conclusiones', 'compromisos', 'anexos'])->findOrFail($id);
            return response()->json($acta);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Acta no encontrada'
            ], 404);
        } catch (\Exception $e) {
            Log::error('Error al mostrar acta: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error al obtener el acta',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function update(Request $request, $id): JsonResponse
    {
        $validated = $request->validate([
            'nombre' => 'sometimes|required|string|max:255',
            'fecha' => 'sometimes|required|date',
            'horaInicio' => 'sometimes|required',
            'horaFin' => 'sometimes|required',
            'tipoActa' => 'sometimes|required|string|max:255',
            'observacion' => 'nullable|string',
            'lugar' => 'nullable|string|max:255',
            'direccion' => 'nullable|string',
            'idCiudad' => 'sometimes|required|exists:ciudad,id',
            'idFicha' => 'sometimes|required|exists:ficha,id',
            'idContrato' => 'sometimes|required|exists:contrato,id',
            'documento' => 'nullable|string',
            'fechaInicialFormacion' => 'sometimes|required|date',
            'fechaFinalFormacion' => 'sometimes|required|date',
            // Relaciones
            'agenda' => 'nullable|array',
            'agenda.*.punto' => 'required|string',
            'objetivos' => 'nullable|array',
            'objetivos.*.objetivo' => 'required|string',
            'asistencias' => 'nullable|array',
            'asistencias.*.idContrato' => 'required|exists:contrato,id',
            'asistencias.*.dependencia' => 'required|string',
            'asistencias.*.aprueba' => 'required|in:SI,NO',
            'asistencias.*.observacion' => 'nullable|string',
            'conclusiones' => 'nullable|array',
            'conclusiones.*.conclusion' => 'required|string',
            'compromisos' => 'nullable|array',
            'compromisos.*.actividad' => 'required|string',
            'compromisos.*.fecha' => 'required|date',
            'compromisos.*.responsable' => 'required|string',
            'anexos' => 'nullable|array',
            'anexos.*.nombre' => 'required|string',
            'anexos.*.archivo' => 'required|string',
            'anexos.*.descripcion' => 'nullable|string',
        ]);

        DB::beginTransaction();
        try {
            $acta = Acta::with('asistencias')->findOrFail($id);

            // Verificar si el acta ya está finalizada (todos los asistentes aprobaron)
            $isLocked = $acta->asistencias->count() > 0 && $acta->asistencias->every(function ($asistencia) {
                return $asistencia->aprueba === 'SI';
            });

            if ($isLocked) {
                return response()->json([
                    'message' => 'No se puede editar el acta porque ya ha sido aceptada por todos los asistentes.'
                ], 403);
            }

            $acta->update($validated);

            // Sincronizar Agenda
            if (isset($validated['agenda'])) {
                $acta->agenda()->delete();
                foreach ($validated['agenda'] as $item) {
                    $acta->agenda()->create($item);
                }
            }

            // Sincronizar Objetivos
            if (isset($validated['objetivos'])) {
                $acta->objetivos()->delete();
                foreach ($validated['objetivos'] as $item) {
                    $acta->objetivos()->create($item);
                }
            }

            // Sincronizar Asistencias
            if (isset($validated['asistencias'])) {
                $existingIds = $acta->asistencias->pluck('idContrato')->toArray();
                $newAsistencias = [];

                foreach ($validated['asistencias'] as $item) {
                    if (!in_array($item['idContrato'], $existingIds)) {
                        $newAsistencias[] = $item;
                    }
                }

                $acta->asistencias()->delete();
                foreach ($validated['asistencias'] as $item) {
                    $acta->asistencias()->create($item);
                }

                if (!empty($newAsistencias)) {
                    $this->notificarAsistentes($acta, $newAsistencias);
                }
            }

            // Sincronizar Conclusiones
            if (isset($validated['conclusiones'])) {
                $acta->conclusiones()->delete();
                foreach ($validated['conclusiones'] as $item) {
                    $acta->conclusiones()->create($item);
                }
            }

            // Sincronizar Compromisos
            if (isset($validated['compromisos'])) {
                $acta->compromisos()->delete();
                foreach ($validated['compromisos'] as $item) {
                    $acta->compromisos()->create($item);
                }
            }

            // Sincronizar Anexos
            if (isset($validated['anexos'])) {
                $acta->anexos()->delete();
                foreach ($validated['anexos'] as $item) {
                    $acta->anexos()->create($item);
                }
            }

            DB::commit();

            return response()->json([
                'message' => 'Acta actualizada correctamente',
                'data' => $acta->load(['agenda', 'objetivos', 'asistencias', 'conclusiones', 'compromisos', 'anexos'])
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Acta no encontrada'
            ], 404);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al actualizar el acta',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function destroy($id): JsonResponse
    {
        try {
            $acta = Acta::findOrFail($id);
            $acta->delete();
            return response()->json([
                'message' => 'Acta eliminada correctamente'
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Acta no encontrada'
            ], 404);
        } catch (\Exception $e) {
            Log::error('Error al eliminar acta: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error al eliminar el acta',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get actas by idContrato.
     *
     * @param int $idContrato
     * @return JsonResponse
     */
    public function getByContrato($idContrato): JsonResponse
    {
        try {
            $actas = Acta::where('idContrato', $idContrato)
                ->with(['ciudad', 'ficha', 'contrato.persona', 'agenda', 'objetivos', 'asistencias.contrato.persona', 'asistencias.contrato.empresa', 'conclusiones', 'compromisos', 'anexos'])
                ->get();

            return response()->json($actas);
        } catch (\Exception $e) {
            Log::error('Error al filtrar actas por contrato: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error al filtrar actas por contrato',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get ficha, persona, and contrato data based on dates.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getFichaData(Request $request): JsonResponse
    {
        $request->validate([
            'idFicha' => 'required|integer',
            'periodo' => 'nullable|date_format:Y-m',
        ]);

        try {
            $idFicha = $request->input('idFicha');
            $query = \App\Models\HorarioMateria::with(['ficha', 'contrato.persona'])
                ->where('idFicha', $idFicha)
                ->whereNotNull('idContrato');

            // Si se proporciona el período, filtrar por fechas
            if ($request->filled('periodo')) {
                $fechaInicial = \Carbon\Carbon::createFromFormat('Y-m', $request->input('periodo'))
                    ->startOfMonth()
                    ->toDateString();

                $fechaFinal = \Carbon\Carbon::createFromFormat('Y-m', $request->input('periodo'))
                    ->endOfMonth()
                    ->toDateString();

                $query->where('fechaInicial', '<=', $fechaFinal)
                    ->where('fechaFinal', '>=', $fechaInicial);
            }

            // Obtenemos los horarios con sus relaciones
            $horarios = $query->get();

            // Mapeamos los resultados
            $data = $horarios->map(function ($hm) {
                $ficha = $hm->ficha;
                $contrato = $hm->contrato;
                $persona = $contrato ? $contrato->persona : null;

                $item = [
                    'idFicha' => $ficha ? $ficha->id : null,
                    'codigoFicha' => $ficha ? $ficha->codigo : null,
                    'idContrato' => $contrato ? $contrato->id : null,
                    'numeroContrato' => $contrato ? $contrato->numeroContrato : null,
                    'fechaContratacion' => $contrato ? $contrato->fechaContratacion : null,
                    'fechaFinalContrato' => $contrato ? $contrato->fechaFinalContrato : null,
                    'fechaInicialHorario' => $hm->fechaInicial,
                    'fechaFinalHorario' => $hm->fechaFinal,
                ];

                // Añadimos todos los campos de la persona
                if ($persona) {
                    $item = array_merge($persona->toArray(), $item);
                }

                return $item;
            })->unique(function ($item) {
                return $item['idContrato'];
            })->values();

            return response()->json($data);
        } catch (\Exception $e) {
            Log::error('Error al ejecutar la consulta de ficha: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error al obtener los datos de la ficha',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get actas where a specific contrato is an attendee.
     *
     * @param int $idContrato
     * @return JsonResponse
     */
    public function getActasByAsistente($idContrato): JsonResponse
    {
        try {
            $actas = Acta::whereHas('asistencias', function ($query) use ($idContrato) {
                $query->where('idContrato', $idContrato);
            })
                ->with(['ciudad', 'ficha', 'contrato.persona', 'agenda', 'objetivos', 'asistencias.contrato.persona', 'asistencias.contrato.empresa', 'conclusiones', 'compromisos', 'anexos'])
                ->get();

            return response()->json($actas);
        } catch (\Exception $e) {
            Log::error('Error al filtrar actas por asistente: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error al filtrar actas por asistente',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the attendance status (approval) for an acta attendee.
     *
     * @param Request $request
     * @param int $idActa
     * @param int $idAsistencia
     * @return JsonResponse
     */
    public function updateAsistenciaStatus(Request $request, $idActa, $idAsistencia): JsonResponse
    {
        $validated = $request->validate([
            'aprueba' => 'required|in:SI,NO',
            'observacion' => 'nullable|string',
        ]);

        try {
            $acta = Acta::with(['asistencias', 'contrato.persona'])->findOrFail($idActa);

            // ✅ Verificar si ya está cerrada ANTES de cualquier acción
            $isLocked = $acta->asistencias->count() > 0
                && $acta->asistencias->every(fn($a) => $a->aprueba === 'SI');

            if ($isLocked) {
                return response()->json([
                    'message' => 'No se puede modificar el estado porque el acta ya fue finalizada por todos los asistentes.'
                ], 403);
            }

            //logica para enviar la notificacion del aplicativo
            $userRemitente = KeyUtil::user();
            $infoRemitente = User::where('idpersona', $userRemitente->idpersona)->with('persona')->firstOrFail();
            $userReceptor = User::where('idpersona', $acta->contrato->persona->id)->firstOrFail();

            DB::transaction(function () use ($acta, $validated, $userRemitente, $infoRemitente, $userReceptor, $idAsistencia) {
                $asistencia = $acta->asistencias()->where('id', $idAsistencia)->firstOrFail();

                $asistencia->update([
                    'aprueba' => $validated['aprueba'],
                    'observacion' => $validated['observacion'] ?? $asistencia->observacion,
                ]);

                $this->enviarNotificacionActa($userRemitente, $userReceptor, $infoRemitente, $acta, $validated['aprueba']);
            });



            $this->enviarCorreoEstado($acta, $validated['aprueba'], $validated['observacion']);

            return response()->json([
                'message' => 'Asistencia actualizada correctamente'
            ]);
        } catch (\Exception $e) {
            Log::error('Error al actualizar asistencia: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error al actualizar la asistencia',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    //Función para encontrat los horarios de los instructores que tienen para esa getFichaData
    //Con las materias
    public function getInstructoresByFicha(Request $request)
    {
        $validated = $request->validate([
            'idFicha' => 'required|integer|exists:ficha,id',
            'periodo' => 'nullable|date_format:Y-m',
        ]);

        if (!empty($validated['periodo'])) {
            $inicio = \Carbon\Carbon::createFromFormat('Y-m', $validated['periodo'])->startOfMonth();
            $fin = \Carbon\Carbon::createFromFormat('Y-m', $validated['periodo'])->endOfMonth();
        } else {
            $inicio = \Carbon\Carbon::now()->startOfMonth();
            $fin = \Carbon\Carbon::now()->endOfMonth();
        }

        $horarios = \App\Models\HorarioMateria::with([
            'contrato.persona',
            'gradoMateria.materia.padre',
        ])
            ->where('idFicha', $validated['idFicha'])
            ->where('estado', 'ASIGNADO')
            ->where(function ($q) use ($inicio, $fin) {
                $q->whereBetween('fechaInicial', [$inicio, $fin])
                    ->orWhereBetween('fechaFinal', [$inicio, $fin])
                    ->orWhere(function ($q2) use ($inicio, $fin) {
                        $q2->where('fechaInicial', '<=', $inicio)
                            ->where('fechaFinal', '>=', $fin);
                    });
            })
            ->get();

        $instructores = $horarios
            ->groupBy('idContrato')
            ->map(function ($horariosGrupo) use ($inicio, $fin) {
                $persona = $horariosGrupo->first()->contrato?->persona;

                $materias = $horariosGrupo
                    ->groupBy('idGradoMateria')
                    ->map(function ($horariosGM) use ($inicio, $fin) {
                        $primero = $horariosGM->first();
                        $rap = $primero->gradoMateria?->materia;
                        $competencia = $rap?->padre;

                        $totalHoras = $horariosGM->sum(function ($h) use ($inicio, $fin) {
                            $duracionSesion = round(
                                (strtotime($h->horaFinal) - strtotime($h->horaInicial)) / 3600,
                                2
                            );

                            $desde = \Carbon\Carbon::parse($h->fechaInicial)->max($inicio);
                            $hasta = \Carbon\Carbon::parse($h->fechaFinal)->min($fin);

                            $idDiaInt = (int) $h->idDia;
                            $diaSemanaCarbon = $idDiaInt === 7 ? 0 : $idDiaInt;
                            $cantidadSesiones = 0;
                            $cursor = $desde->copy();

                            while ($cursor->lte($hasta)) {
                                if ($cursor->dayOfWeek === $diaSemanaCarbon) {
                                    $cantidadSesiones++;
                                }
                                $cursor->addDay();
                            }

                            return round($duracionSesion * $cantidadSesiones, 2);
                        });

                        return [
                            'idGradoMateria' => $primero->idGradoMateria,
                            'competencia' => $competencia?->nombreMateria,
                            'resultadoAprendizaje' => $rap?->nombreMateria,
                            'totalHoras' => $totalHoras,
                        ];
                    })
                    ->values();

                return [
                    'idContrato' => $horariosGrupo->first()->idContrato,
                    'nombre' => $persona?->nombre1,
                    'apellido' => $persona?->apellido1,
                    'totalHoras' => $materias->sum('totalHoras'),
                    'materias' => $materias,
                ];
            })
            ->values();

        return response()->json($instructores);
    }


    private function enviarCorreoEstado(Acta $acta, string $aprueba, ?string $observacion): void
    {
        $userConectado = KeyUtil::user();
        $userRemitente = Person::find($userConectado->idpersona);
        $userReceptor = optional($acta->contrato->persona);

        $asunto = "Acta {$acta->nombre}";

        if ($aprueba === 'SI') {
            $detalle = "ha sido APROBADA.\n\nNo se requiere ninguna acción adicional de su parte.";
        } else {
            $motivoTexto = $observacion
                ? "Motivo:\n\n* {$observacion}"
                : "No se proporcionó un motivo específico.";

            $detalle = "ha sido RECHAZADA.\n\nPor favor revise las observaciones y realice las correcciones necesarias.\n\n{$motivoTexto}";
        }

        $mensaje = "Estimado(a) {$userReceptor->nombre1} {$userReceptor->apellido1},\n\n"
            . "Le informamos que el acta {$acta->nombre} {$detalle}\n\n"
            . "Atentamente,\n"
            . "{$userRemitente->nombre1} {$userRemitente->apellido1}\n";

        \App\Jobs\SendBasicEmail::dispatch($userReceptor->email, $asunto, $mensaje);
    }

    private function notificarAsistentes(Acta $acta, array $asistenciasData): void
    {
        $userConectado = KeyUtil::user();
        $userRemitente = User::where('idpersona', $userConectado->idpersona)->first();
        $personaRemitente = Person::find($userConectado->idpersona);

        Log::info("Iniciando notificarAsistentes para acta #{$acta->id} con " . count($asistenciasData) . " asistentes.");

        foreach ($asistenciasData as $asistencia) {
            $idContrato = $asistencia['idContrato'];
            $contrato = Contract::with('persona')->find($idContrato);

            if ($contrato && $contrato->persona) {
                $userReceptorPersona = $contrato->persona;

                // Correo electrónico
                if ($userReceptorPersona->email) {
                    $asunto = "Asignación a Acta: {$acta->nombre}";
                    $mensaje = "Estimado(a) {$userReceptorPersona->nombre1} {$userReceptorPersona->apellido1},\n\n"
                        . "Le informamos que ha sido registrado como asistente en el acta: \"{$acta->nombre}\".\n\n"
                        . "Detalles del Acta:\n"
                        . "- Fecha: {$acta->fecha}\n"
                        . "- Lugar: " . ($acta->lugar ?? 'No especificado') . "\n"
                        . "- Hora Inicio: {$acta->horaInicio}\n\n"
                        . "Por favor, ingrese al sistema para revisar el contenido del acta y confirmar su aprobación.\n\n"
                        . "Atentamente,\n"
                        . "{$personaRemitente->nombre1} {$personaRemitente->apellido1}\n";

                    \App\Jobs\SendBasicEmail::dispatchSync($userReceptorPersona->email, $asunto, $mensaje);
                    Log::info("Correo de asignación enviado a {$userReceptorPersona->email} para acta #{$acta->id}");
                } else {
                    Log::warning("Asistente con idContrato={$idContrato} no tiene email registrado. No se envió correo.");
                }

                // Notificación en el aplicativo
                $userReceptor = User::where('idpersona', $userReceptorPersona->id)->first();
                if ($userReceptor && $userRemitente) {
                    NotificacionSistema::create([
                        'fecha' => now()->toDateString(),
                        'hora' => now()->toTimeString(),
                        'asunto' => "Nueva asignación de acta",
                        'mensaje' => "Ha sido asignado como asistente al acta: {$acta->nombre}",
                        'estado_id' => 1,
                        'idUsuarioReceptor' => $userReceptor->id,
                        'idUsuarioRemitente' => $userRemitente->id,
                        'idTipoNotificacion' => 1,
                        'idEmpresa' => KeyUtil::idCompany(),
                        'route' => '/actas'
                    ]);
                    Log::info("Notificación de sistema creada para usuario #{$userReceptor->id} - acta #{$acta->id}");
                } else {
                    Log::warning("No se pudo crear notificación para idContrato={$idContrato}. userReceptor=" . ($userReceptor ? $userReceptor->id : 'null') . " userRemitente=" . ($userRemitente ? $userRemitente->id : 'null'));
                }
            } else {
                Log::warning("No se encontró contrato o persona para idContrato={$idContrato}. No se enviaron notificaciones.");
            }
        }
    }
    protected function enviarNotificacionActa(
        $remitente,
        $receptor,
        $infoRemitente,
        $acta,
        $aprueba = 'SI'
    ) {
        $textos = [
            'SI' => ['asunto' => 'Acta aprobada', 'accion' => 'aprobado'],
            'NO' => ['asunto' => 'Acta rechazada', 'accion' => 'rechazada'],
        ];

        $nombre = "{$infoRemitente->persona->nombre1} {$infoRemitente->persona->apellido1}";
        $asunto = $textos[$aprueba]['asunto'];
        $mensaje = "El acta {$acta->nombre} ha sido {$textos[$aprueba]['accion']} por {$nombre}.";

        return NotificacionSistema::create([
            'fecha' => now()->toDateString(),
            'hora' => now()->toTimeString(),
            'asunto' => $asunto,
            'mensaje' => $mensaje,
            'estado_id' => 1,
            'idUsuarioReceptor' => $receptor->id,
            'idUsuarioRemitente' => $remitente->id,
            'idTipoNotificacion' => 1,
            'idEmpresa' => KeyUtil::idCompany(),
            'route' => '/actas'
        ]);
    }

    public function getActaInstructor($id)
    {
        try {
            $acta = Acta::with(['ciudad', 'ficha', 'contrato.persona', 'agenda', 'objetivos', 'asistencias.contrato.persona', 'asistencias.contrato.centroFormacion', 'conclusiones', 'compromisos', 'anexos'])->findOrFail($id);

            $instructores = collect();
            $horarios = collect();

            if ($acta->idFicha) {
                $inicio = $acta->fechaInicialFormacion ? \Carbon\Carbon::parse($acta->fechaInicialFormacion)->startOfDay() : \Carbon\Carbon::parse($acta->fecha)->startOfMonth();
                $fin = $acta->fechaFinalFormacion ? \Carbon\Carbon::parse($acta->fechaFinalFormacion)->endOfDay() : \Carbon\Carbon::parse($acta->fecha)->endOfMonth();

                $horarios = \App\Models\HorarioMateria::with([
                    'contrato.persona',
                    'gradoMateria.materia.padre',
                ])
                    ->where('idFicha', $acta->idFicha)
                    ->where('estado', '!=', 'PENDIENTE')
                    ->where(function ($q) use ($inicio, $fin) {
                        $q->whereBetween('fechaInicial', [$inicio, $fin])
                            ->orWhereBetween('fechaFinal', [$inicio, $fin])
                            ->orWhere(function ($q2) use ($inicio, $fin) {
                                $q2->where('fechaInicial', '<=', $inicio)
                                    ->where('fechaFinal', '>=', $fin);
                            });
                    })
                    ->get();

                $instructores = $horarios
                    ->groupBy('idContrato')
                    ->map(function ($horariosGrupo) use ($inicio, $fin) {
                        $persona = $horariosGrupo->first()->contrato?->persona;

                        $materias = $horariosGrupo
                            ->groupBy('idGradoMateria')
                            ->map(function ($horariosGM) use ($inicio, $fin) {
                                $primero = $horariosGM->first();
                                $rap = $primero->gradoMateria?->materia;
                                $competencia = $rap?->padre;

                                $totalHoras = $horariosGM->sum(function ($h) use ($inicio, $fin) {
                                    $duracionSesion = round(
                                        (strtotime($h->horaFinal) - strtotime($h->horaInicial)) / 3600,
                                        2
                                    );

                                    $desde = \Carbon\Carbon::parse($h->fechaInicial)->max($inicio);
                                    $hasta = \Carbon\Carbon::parse($h->fechaFinal)->min($fin);

                                    $idDiaInt = (int) $h->idDia;
                                    $diaSemanaCarbon = $idDiaInt === 7 ? 0 : $idDiaInt;
                                    $cantidadSesiones = 0;
                                    $cursor = $desde->copy();

                                    while ($cursor->lte($hasta)) {
                                        if ($cursor->dayOfWeek === $diaSemanaCarbon) {
                                            $cantidadSesiones++;
                                        }
                                        $cursor->addDay();
                                    }

                                    return round($duracionSesion * $cantidadSesiones, 2);
                                });

                                return [
                                    'idGradoMateria' => $primero->idGradoMateria,
                                    'competencia' => $competencia?->nombreMateria,
                                    'resultadoAprendizaje' => $rap?->nombreMateria,
                                    'totalHoras' => $totalHoras,
                                ];
                            })
                            ->values();

                        return [
                            'idContrato' => $horariosGrupo->first()->idContrato,
                            'nombre' => $persona?->nombre1,
                            'apellido' => $persona?->apellido1,
                            'totalHoras' => $materias->sum('totalHoras'),
                            'materias' => $materias,
                        ];
                    })
                    ->values();
            }

            $colores = [
                '#FFD700',
                '#FFA500',
                '#4CAF50',
                '#2196F3',
                '#E91E63',
                '#9C27B0',
                '#00BCD4',
                '#FF5722',
                '#795548',
                '#607D8B',
            ];

            $instructoresConColor = $instructores->values()->map(function ($instructor, $index) use ($colores) {
                $instructor['color'] = $colores[$index % count($colores)];
                return $instructor;
            });

            $inicio = $acta->fechaInicialFormacion ? \Carbon\Carbon::parse($acta->fechaInicialFormacion)->startOfDay() : \Carbon\Carbon::parse($acta->fecha)->startOfMonth();
            $fin = $acta->fechaFinalFormacion ? \Carbon\Carbon::parse($acta->fechaFinalFormacion)->endOfDay() : \Carbon\Carbon::parse($acta->fecha)->endOfMonth();
            $diasDelMes = $inicio->copy()->toPeriod($fin->copy());

            $calendario = [];
            foreach ($diasDelMes as $dia) {
                $mesKey = $dia->format('Y-m');
                if (!isset($calendario[$mesKey])) {
                    $calendario[$mesKey] = [];
                }
                $calendario[$mesKey][$dia->day] = [
                    'colores' => [],
                    'esFinde' => $dia->isWeekend(),
                    'diaSemana' => $dia->dayOfWeek,
                ];
            }

            foreach ($horarios->groupBy('idContrato') as $idContrato => $horariosInstructor) {
                $instructorConColor = $instructoresConColor->firstWhere('idContrato', $idContrato);
                if (!$instructorConColor)
                    continue;
                $color = $instructorConColor['color'];

                foreach ($horariosInstructor as $h) {
                    $desde = \Carbon\Carbon::parse($h->fechaInicial)->max($inicio);
                    $hasta = \Carbon\Carbon::parse($h->fechaFinal)->min($fin);

                    $idDiaInt = (int) $h->idDia;
                    $diaSemanaCarbon = $idDiaInt === 7 ? 0 : $idDiaInt;

                    $cursor = $desde->copy();
                    while ($cursor->lte($hasta)) {
                        if ($cursor->dayOfWeek === $diaSemanaCarbon) {
                            $mesKey = $cursor->format('Y-m');
                            $day = $cursor->day;
                            if (isset($calendario[$mesKey][$day]) && !in_array($color, $calendario[$mesKey][$day]['colores'])) {
                                $calendario[$mesKey][$day]['colores'][] = $color;
                            }
                        }
                        $cursor->addDay();
                    }
                }
            }

            $novedades = collect();
            if ($acta->idFicha) {
                $novedades = \App\Models\MatriculaAcademica::with(['matricula.person'])
                    ->where('idFicha', $acta->idFicha)
                    ->whereNull('idGradoMateria')
                    ->get()
                    ->map(function ($ma) {
                        $persona = $ma->matricula?->person;
                        $nombreCompleto = $persona
                            ? trim("{$persona->nombre1} {$persona->nombre2} {$persona->apellido1} {$persona->apellido2}")
                            : 'Estudiante no encontrado';

                        return [
                            'nombre' => $nombreCompleto,
                            'estado' => $ma->matricula?->estado ?? 'SIN ESTADO',
                            'identificacion' => $persona?->identificacion ?? '—',
                            'enFormacion' => $ma->matricula->estado === 'EN FORMACION',
                        ];
                    })
                    ->sortBy('enFormacion')
                    ->unique('identificacion')
                    ->sortBy('nombre')
                    ->values();
            }

            $enFormacion = $novedades->where('enFormacion', true)->values();
            $conNovedad = $novedades->where('enFormacion', false)->values();

            // ─── DEBUG ───────────────────────────────────────────────
            if (request()->boolean('debug')) {
                return response()->json([
                    'acta' => [
                        'id' => $acta->id,
                        'fecha' => $acta->fecha,
                        'idFicha' => $acta->idFicha,
                        'ciudad' => $acta->ciudad,
                        'ficha' => $acta->ficha,
                        'contrato' => $acta->contrato,
                        'agenda' => $acta->agenda,
                        'objetivos' => $acta->objetivos,
                        'asistencias' => $acta->asistencias,
                        'conclusiones' => $acta->conclusiones,
                        'compromisos' => $acta->compromisos,
                        'anexos' => $acta->anexos,
                    ],
                    'instructoresConColor' => $instructoresConColor,
                    'calendario' => $calendario,
                    'enFormacion' => $enFormacion,
                    'conNovedad' => $conNovedad,
                    'meta' => [
                        'totalInstructores' => $instructoresConColor->count(),
                        'totalEnFormacion' => $enFormacion->count(),
                        'totalConNovedad' => $conNovedad->count(),
                        'diasEnCalendario' => count($calendario),
                    ],
                    'horarios_raw' => $horarios->map(function ($h) {
                        return [
                            'idContrato' => $h->idContrato,
                            'idGradoMateria' => $h->idGradoMateria,
                            'idDia' => $h->idDia,
                            'fechaInicial' => $h->fechaInicial,
                            'fechaFinal' => $h->fechaFinal,
                            'horaInicial' => $h->horaInicial,
                            'horaFinal' => $h->horaFinal,
                            'estado' => $h->estado,
                            'instructor' => $h->contrato?->persona?->nombre1 . ' ' . $h->contrato?->persona?->apellido1,
                        ];
                    }),
                ]);
            }
            // ─────────────────────────────────────────────────────────
// DESPUÉS — normaliza claves antes de pasar a la vista
            $calendarioNormalizado = [];
            foreach ($calendario as $mesKey => $dias) {
                $calendarioNormalizado[$mesKey] = [];
                foreach ($dias as $k => $v) {
                    $calendarioNormalizado[$mesKey][(string) (int) $k] = $v;
                }
            }

            $pdf = Pdf::loadView('pdf.actasInstructores', [
                'acta' => $acta,
                'instructoresConColor' => $instructoresConColor,
                'instructores' => $instructores,
                'calendario' => $calendarioNormalizado,
                'enFormacion' => $enFormacion,
                'conNovedad' => $conNovedad,
            ])->setPaper('letter')
                ->setOption('isPhpEnabled', true)
                ->setOption('isHtml5ParserEnabled', true)
                ->setOption('isFontSubsettingEnabled', true);

            return $pdf->stream('actasInstructor.pdf');

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['message' => 'Acta no encontrada'], 404);
        } catch (\Exception $e) {
            Log::error('Error al generar PDF de acta: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error al generar el PDF',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    public function uploadDocumento(Request $request, $id): JsonResponse
    {
        $request->validate([
            'documento' => 'required|file|mimes:pdf|max:10240',
        ]);

        try {
            $acta = Acta::findOrFail($id);

            if ($request->hasFile('documento')) {
                // Eliminar archivo anterior si existe
                if ($acta->documento) {
                    Storage::delete($acta->documento); // ya tiene prefijo "public/"
                }

                $file = $request->file('documento');
                $nombreArchivo = uniqid('acta_doc_') . '_' . $file->getClientOriginalName();

                // Guardar igual que AnexoActa: disco local con prefijo public/
                $path = $file->storeAs('public/actas/documentos', $nombreArchivo);

                $acta->update([
                    'documento' => $path
                ]);

                return response()->json([
                    'message' => 'Documento subido correctamente',
                    'documento' => $path
                ]);
            }

            return response()->json([
                'message' => 'No se proporcionó ningún archivo'
            ], 400);
        } catch (\Exception $e) {
            Log::error('Error al subir documento de acta: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error al subir el documento',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
