<?php

namespace App\Http\Controllers;

use App\Mail\MailService;
use App\Models\ActivationCompanyUser;
use App\Models\ActividadContrato;
use App\Models\ActividadInstructor;
use App\Models\ComisionInstructor;
use App\Models\Contract;
use App\Models\DetalleRmi;
use App\Models\NotificacionSistema;
use App\Models\Rmi;
use App\Models\SesionMateria;
use App\Util\KeyUtil;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\JsonResponse;

class InstructoresController extends Controller
{
    public function getInstructors(Request $request)
    {
        $validated = $request->validate([
            'idCentroFormacion' => 'required|integer|exists:centroFormacion,id'
        ]);

        // Periodo calculado automáticamente: mes actual
        $inicio     = \Carbon\Carbon::now()->startOfMonth();
        $fin        = \Carbon\Carbon::now()->endOfMonth();
        $periodoReq = \Carbon\Carbon::now()->format('Y-m');

        // Cargar el RMI del periodo actual una sola vez
        $rmi = Rmi::where('periodo', $periodoReq)->first();

        $instructors = ActivationCompanyUser::with([
            'user.persona.contracts' => function ($q) use ($inicio, $fin) {
                $q->latest()->with([
                    'horarioMateria' => function ($h) use ($inicio, $fin) {
                        $h->select(
                            'id',
                            'idContrato',
                            'horaInicial',
                            'horaFinal',
                            'estado',
                            'idDia',
                            'fechaInicial',
                            'fechaFinal'
                        )->where('estado', 'ASIGNADO')
                            ->where(function ($q) use ($inicio, $fin) {
                                $q->whereBetween('fechaInicial', [$inicio, $fin])
                                    ->orWhereBetween('fechaFinal', [$inicio, $fin])
                                    ->orWhere(function ($q2) use ($inicio, $fin) {
                                        $q2->where('fechaInicial', '<=', $inicio)
                                            ->where('fechaFinal', '>=', $fin);
                                    });
                            });
                    }
                ]);
            }
        ])
            ->active()
            ->role('INSTRUCTOR SENA')
            ->whereHas('user', function ($q) use ($validated) {
                $q->where('idCentroFormacion', $validated['idCentroFormacion']);
            })
            ->whereHas('user.persona.contracts.horarioMateria', function ($h) use ($inicio, $fin) {
                $h->where('estado', 'ASIGNADO')
                    ->where(function ($q) use ($inicio, $fin) {
                        $q->whereBetween('fechaInicial', [$inicio, $fin])
                            ->orWhereBetween('fechaFinal', [$inicio, $fin])
                            ->orWhere(function ($q2) use ($inicio, $fin) {
                                $q2->where('fechaInicial', '<=', $inicio)
                                    ->where('fechaFinal', '>=', $fin);
                            });
                    });
            })
            ->get()
            ->flatMap(function ($acu) use ($inicio, $fin, $rmi) {

                $user     = $acu->user;
                $persona  = $user->persona;
                $contrato = $persona->contracts->first();

                if (!$contrato) return [];

                // Obtener detallesRmi PENDIENTE/RECHAZADO del periodo actual para este contrato
                $detallesRmi = $rmi
                    ? DetalleRmi::where('idRmi', $rmi->id)
                    ->whereIn('estado', ['PENDIENTE', 'RECHAZADO'])
                    ->whereHas('horarioMateria', function ($q) use ($contrato) {
                        $q->where('idContrato', $contrato->id);
                    })
                    ->get()
                    : collect();

                // Si no tiene detallesRmi PENDIENTE/RECHAZADO en el periodo, excluir
                if ($detallesRmi->isEmpty()) return [];

                // IDs de horarios con detalleRmi PENDIENTE o RECHAZADO
                $idsConDetalle = $detallesRmi->pluck('idHorarioMateria')->toArray();

                // Mapear horarios del mes que tienen detalleRmi relevante
                $horariosBase = $contrato->horarioMateria
                    ->filter(fn($h) => in_array($h->id, $idsConDetalle))
                    ->map(function ($h) use ($inicio, $fin) {
                        $duracionSesion   = round((strtotime($h->horaFinal) - strtotime($h->horaInicial)) / 3600, 2);
                        $desde            = \Carbon\Carbon::parse($h->fechaInicial)->max($inicio);
                        $hasta            = \Carbon\Carbon::parse($h->fechaFinal)->min($fin);
                        $diaSemanaCarbon  = $h->idDia === 7 ? 0 : $h->idDia;
                        $cantidadSesiones = 0;
                        $cursor           = $desde->copy();
                        while ($cursor->lte($hasta)) {
                            if ($cursor->dayOfWeek === $diaSemanaCarbon) $cantidadSesiones++;
                            $cursor->addDay();
                        }
                        return [
                            'id'               => $h->id,
                            'idContrato'       => $h->idContrato,
                            'horaInicial'      => $h->horaInicial,
                            'horaFinal'        => $h->horaFinal,
                            'estado'           => $h->estado,
                            'idDia'            => $h->idDia,
                            'fechaInicial'     => $h->fechaInicial,
                            'fechaFinal'       => $h->fechaFinal,
                            'duracionSesion'   => $duracionSesion,
                            'cantidadSesiones' => $cantidadSesiones,
                            'duracionHoras'    => round($duracionSesion * $cantidadSesiones, 2),
                        ];
                    })->keyBy('id');

                // Horas ejecutadas en el mes actual (sesiones registradas)
                $horasEjecutadas = round(
                    SesionMateria::where('sesionMateria.idContrato', $contrato->id)
                        ->whereBetween('fechaSesion', [$inicio, $fin])
                        ->join('horarioMateria', 'sesionMateria.idHorarioMateria', '=', 'horarioMateria.id')
                        ->selectRaw('SUM((TIME_TO_SEC(horarioMateria.horaFinal) - TIME_TO_SEC(horarioMateria.horaInicial)) / 3600) as totalHoras')
                        ->value('totalHoras') ?? 0
                );

                $personaData = [
                    'identificacion' => $persona->identificacion,
                    'nombre1'        => $persona->nombre1,
                    'nombre2'        => $persona->nombre2,
                    'apellido1'      => $persona->apellido1,
                    'apellido2'      => $persona->apellido2,
                    'fechaNac'       => $persona->fechaNac,
                    'direccion'      => $persona->direccion,
                    'email'          => $persona->email,
                    'celular'        => $persona->celular,
                    'telefonoFijo'   => $persona->telefonoFijo,
                    'perfil'         => $persona->perfil,
                    'sexo'           => $persona->sexo,
                    'rh'             => $persona->rh,
                    'rutaFoto'       => $persona->rutaFotoUrl
                ];

                // Agrupar por estado → una entrada por cada estado distinto
                return $detallesRmi
                    ->groupBy('estado')
                    ->map(function ($grupo, $estado) use ($acu, $user, $contrato, $personaData, $horariosBase, $horasEjecutadas, $rmi) {
                        $idsGrupo = $grupo->pluck('idHorarioMateria')->toArray();

                        $motivoRechazo = $estado === 'RECHAZADO'
                            ? $grupo->first(fn($d) => !empty($d->observacion))?->observacion
                            : null;

                        return [
                            'idActivation'      => $acu->id,
                            'emailUsuario'      => $user->email,
                            'idContrato'        => $contrato->id,
                            'idRmi'             => $rmi?->id,
                            'roles'             => $acu->getRoleNames(),
                            'estado'            => $estado,
                            'totalHoras'        => $contrato->horasmes ?? 0,
                            'totalHorasFormato' => $horasEjecutadas,
                            'motivoRechazo'     => $motivoRechazo,
                            'horarios'          => $horariosBase->only($idsGrupo)->values(),
                            'persona'           => $personaData,
                        ];
                    })
                    ->values()
                    ->toArray();
            });

        return response()->json($instructors->values());
    }
    public function getInstructorsHistorial(Request $request)
    {
        $validated = $request->validate([
            'idCentroFormacion' => 'required|integer|exists:centroFormacion,id',
            'periodo'           => 'nullable|date_format:Y-m',
            'estado'            => 'nullable|string|in:PENDIENTE,ACEPTADO,RECHAZADO',
        ]);

        // Determinar periodo
        $periodoReq = $validated['periodo'] ?? \Carbon\Carbon::now()->format('Y-m');
        $inicio = \Carbon\Carbon::createFromFormat('Y-m', $periodoReq)->startOfMonth();
        $fin    = \Carbon\Carbon::createFromFormat('Y-m', $periodoReq)->endOfMonth();

        $instructors = ActivationCompanyUser::with([
            'user.persona.contracts' => function ($q) use ($inicio, $fin) {
                $q->latest()->with([
                    'horarioMateria' => function ($h) use ($inicio, $fin) {
                        $h->select(
                            'id',
                            'idContrato',
                            'horaInicial',
                            'horaFinal',
                            'estado',
                            'idDia',
                            'fechaInicial',
                            'fechaFinal'
                        )->where('estado', 'ASIGNADO');

                        $h->where(function ($q) use ($inicio, $fin) {
                            $q->whereBetween('fechaInicial', [$inicio, $fin])
                                ->orWhereBetween('fechaFinal', [$inicio, $fin])
                                ->orWhere(function ($q2) use ($inicio, $fin) {
                                    $q2->where('fechaInicial', '<=', $inicio)
                                        ->where('fechaFinal', '>=', $fin);
                                });
                        });
                    }
                ]);
            }
        ])
            ->active()
            ->role('INSTRUCTOR SENA')
            ->whereHas('user', function ($q) use ($validated) {
                $q->where('idCentroFormacion', $validated['idCentroFormacion']);
            })
            // Solo instructores con horarios en el periodo
            ->whereHas('user.persona.contracts.horarioMateria', function ($h) use ($inicio, $fin) {
                $h->where('estado', 'ASIGNADO')
                    ->where(function ($q) use ($inicio, $fin) {
                        $q->whereBetween('fechaInicial', [$inicio, $fin])
                            ->orWhereBetween('fechaFinal', [$inicio, $fin])
                            ->orWhere(function ($q2) use ($inicio, $fin) {
                                $q2->where('fechaInicial', '<=', $inicio)
                                    ->where('fechaFinal', '>=', $fin);
                            });
                    });
            })
            ->get()
            ->map(function ($acu) use ($periodoReq, $inicio, $fin) {

                $user     = $acu->user;
                $persona  = $user->persona;
                $contrato = $persona->contracts->first();

                $horarios = $contrato?->horarioMateria->map(function ($h) use ($inicio, $fin) {
                    $duracionSesion = round((strtotime($h->horaFinal) - strtotime($h->horaInicial)) / 3600, 2);

                    $desde = \Carbon\Carbon::parse($h->fechaInicial)->max($inicio);
                    $hasta = \Carbon\Carbon::parse($h->fechaFinal)->min($fin);

                    $diaSemanaCarbon  = $h->idDia === 7 ? 0 : $h->idDia;
                    $cantidadSesiones = 0;
                    $cursor           = $desde->copy();

                    while ($cursor->lte($hasta)) {
                        if ($cursor->dayOfWeek === $diaSemanaCarbon) {
                            $cantidadSesiones++;
                        }
                        $cursor->addDay();
                    }

                    return [
                        'id'               => $h->id,
                        'idContrato'       => $h->idContrato,
                        'horaInicial'      => $h->horaInicial,
                        'horaFinal'        => $h->horaFinal,
                        'estado'           => $h->estado,
                        'idDia'            => $h->idDia,
                        'fechaInicial'     => $h->fechaInicial,
                        'fechaFinal'       => $h->fechaFinal,
                        'duracionSesion'   => $duracionSesion,
                        'cantidadSesiones' => $cantidadSesiones,
                        'duracionHoras'    => round($duracionSesion * $cantidadSesiones, 2),
                    ];
                });

                // Obtener estado del RMI para el periodo
                $estadoRmi = 'PENDIENTE';
                $motivoRechazo = null;
                $idRmi = null;

                if ($contrato) {
                    $rmi = Rmi::where('periodo', $periodoReq)->first();
                    $idRmi = $rmi?->id;
                    if ($rmi) {
                        $detallesRmi = DetalleRmi::where('idRmi', $rmi->id)
                            ->whereHas('horarioMateria', function ($q) use ($contrato) {
                                $q->where('idContrato', $contrato->id);
                            })
                            ->get();

                        if ($detallesRmi->isNotEmpty()) {
                            if ($detallesRmi->contains('estado', 'RECHAZADO')) {
                                $estadoRmi = 'RECHAZADO';
                                $detalleRechazado = $detallesRmi->firstWhere('estado', 'RECHAZADO');
                                $motivoRechazo = $detalleRechazado?->observacion;
                            } elseif ($detallesRmi->every(function ($d) {
                                return $d->estado === 'ACEPTADO';
                            })) {
                                $estadoRmi = 'ACEPTADO';
                            } else {
                                $estadoRmi = 'PENDIENTE';
                            }
                        }
                    }
                }

                return [
                    'idActivation' => $acu->id,
                    'emailUsuario' => $user->email,
                    'idContrato'   => $contrato?->id,
                    'idRmi'         => $idRmi,
                    'roles'        => $acu->getRoleNames(),
                    'horarios'     => $horarios,
                    'estado'       => $estadoRmi,
                    'motivoRechazo' => $motivoRechazo,
                    'persona'      => [
                        'identificacion' => $persona->identificacion,
                        'nombre1'        => $persona->nombre1,
                        'nombre2'        => $persona->nombre2,
                        'apellido1'      => $persona->apellido1,
                        'apellido2'      => $persona->apellido2,
                        'fechaNac'       => $persona->fechaNac,
                        'direccion'      => $persona->direccion,
                        'email'          => $persona->email,
                        'celular'        => $persona->celular,
                        'telefonoFijo'   => $persona->telefonoFijo,
                        'perfil'         => $persona->perfil,
                        'sexo'           => $persona->sexo,
                        'rh'             => $persona->rh,
                        'rutaFoto'       => $persona->rutaFotoUrl,
                    ],
                ];
            });

        // Filtrar por estado si se proporciona
        if (!empty($validated['estado'])) {
            $instructors = $instructors->where('estado', $validated['estado'])->values();
        }

        return response()->json($instructors);
    }
    public function getFichasByContrato(Request $request)
    {
        $validated = $request->validate([
            'idContrato' => 'required|integer|exists:contrato,id',
            'periodo'    => 'nullable|date_format:Y-m',
        ]);

        $query = \App\Models\HorarioMateria::with([
            'ficha.asignacion.programa',
            'gradoMateria.materia.padre',
            'detallesRmi'
        ])
            ->where('idContrato', $validated['idContrato'])
            ->where('estado', 'ASIGNADO');

        if (!empty($validated['periodo'])) {
            $inicio = \Carbon\Carbon::createFromFormat('Y-m', $validated['periodo'])->startOfMonth();
            $fin    = \Carbon\Carbon::createFromFormat('Y-m', $validated['periodo'])->endOfMonth();
        } else {
            $inicio = \Carbon\Carbon::now()->startOfMonth();
            $fin    = \Carbon\Carbon::now()->endOfMonth();
        }

        $query->where(function ($q) use ($inicio, $fin) {
            $q->whereBetween('fechaInicial', [$inicio, $fin])
                ->orWhereBetween('fechaFinal', [$inicio, $fin])
                ->orWhere(function ($q2) use ($inicio, $fin) {
                    $q2->where('fechaInicial', '<=', $inicio)
                        ->where('fechaFinal', '>=', $fin);
                });
        });

        $horarios = $query->get();

        $fichas = $horarios->groupBy('idFicha')->map(function ($horariosGrupo) use ($validated) {
            $ficha    = $horariosGrupo->first()->ficha;
            $programa = $ficha?->asignacion?->programa;

            // Paso 1: mapear cada horario con todos sus datos y su idGradoMateria
            $horariosConDatos = $horariosGrupo->map(function ($h) use ($validated) {
                $rap            = $h->gradoMateria?->materia;
                $competencia    = $rap?->padre;
                $duracionSesion = round((strtotime($h->horaFinal) - strtotime($h->horaInicial)) / 3600, 2);

                // Determinar el rango a calcular
                if (!empty($validated['periodo'])) {
                    $rangoInicio = \Carbon\Carbon::createFromFormat('Y-m', $validated['periodo'])->startOfMonth();
                    $rangoFin    = \Carbon\Carbon::createFromFormat('Y-m', $validated['periodo'])->endOfMonth();
                    $desde       = \Carbon\Carbon::parse($h->fechaInicial)->max($rangoInicio);
                    $hasta       = \Carbon\Carbon::parse($h->fechaFinal)->min($rangoFin);
                } else {
                    $desde = \Carbon\Carbon::parse($h->fechaInicial)->max(\Carbon\Carbon::now()->startOfMonth());
                    $hasta = \Carbon\Carbon::parse($h->fechaFinal)->min(\Carbon\Carbon::now()->endOfMonth());
                }

                // idDia: 1=Lunes...6=Sabado, 7=Domingo → Carbon: 0=Domingo, 1=Lunes...6=Sabado
                $diaSemanaCarbon  = $h->idDia === 7 ? 0 : $h->idDia;
                $cantidadSesiones = 0;
                $cursor           = $desde->copy();

                while ($cursor->lte($hasta)) {
                    if ($cursor->dayOfWeek === $diaSemanaCarbon) {
                        $cantidadSesiones++;
                    }
                    $cursor->addDay();
                }

                return [
                    'idGradoMateria'       => $h->idGradoMateria,
                    'competencia'          => $competencia?->nombreMateria,
                    'resultadoAprendizaje' => $rap?->nombreMateria,
                    'idHorario'            => $h->id,
                    'horaInicial'          => $h->horaInicial,
                    'horaFinal'            => $h->horaFinal,
                    'fechaInicial'         => $h->fechaInicial,
                    'fechaFinal'           => $h->fechaFinal,
                    'duracionSesion'       => $duracionSesion,
                    'cantidadSesiones'     => $cantidadSesiones,
                    'duracionHoras'        => round($duracionSesion * $cantidadSesiones, 2),
                    'idDia'                => $h->idDia
                ];
            });

            // Paso 2: agrupar por idGradoMateria
            $resultados = $horariosConDatos
                ->groupBy('idGradoMateria')
                ->map(function ($horariosGM) {
                    $primero = $horariosGM->first();
                    $detallesRmi = DetalleRmi::whereHas('horarioMateria', function ($query) use ($primero) {
                        $query->where('idGradoMateria', $primero['idGradoMateria']);
                    })->get();
                    return [
                        'idGradoMateria'       => $primero['idGradoMateria'],
                        'competencia'          => $primero['competencia'],
                        'resultadoAprendizaje' => $primero['resultadoAprendizaje'],
                        'estadoAsociacion'     => $detallesRmi->first()?->estadoAsociacion,
                        'horarios'             => $horariosGM->map(function ($item) use ($detallesRmi) {
                            return [
                                'idHorario'        => $item['idHorario'],
                                'horaInicial'      => $item['horaInicial'],
                                'horaFinal'        => $item['horaFinal'],
                                'fechaInicial'     => $item['fechaInicial'],
                                'fechaFinal'       => $item['fechaFinal'],
                                'duracionSesion'   => $item['duracionSesion'],
                                'cantidadSesiones' => $item['cantidadSesiones'],
                                'duracionHoras'    => $item['duracionHoras'],
                                'idDia'            => $item['idDia']
                            ];
                        })->values(),
                    ];
                })->values();

            return [
                'idFicha'           => $ficha?->id,
                'codigoFicha'       => $ficha?->codigo,
                'programaFormacion' => $programa?->nombrePrograma,
                'codigoPrograma'    => $programa?->codigoPrograma,
                'resultados'        => $resultados,
            ];
        })->values();

        return response()->json($fichas);
    }

    public function aceptarRmi($idActivation, Request $request)
    {
        DB::beginTransaction();
        try {
            $validated = $request->validate([
                'periodo' => 'nullable|date_format:Y-m',
                'email' => 'required|email'
            ]);

            $activation = ActivationCompanyUser::with('user.persona.contracts')->findOrFail($idActivation);
            $contrato = $activation->user->persona->contracts->first();

            if (!$contrato) {
                DB::rollBack();
                return response()->json(['message' => 'El instructor no tiene un contrato asociado'], 404);
            }

            $periodo = $validated['periodo'] ?? \Carbon\Carbon::now()->format('Y-m');
            $inicio = \Carbon\Carbon::createFromFormat('Y-m', $periodo)->startOfMonth();
            $fin = \Carbon\Carbon::createFromFormat('Y-m', $periodo)->endOfMonth();

            // Obtener horarios del contrato en el periodo
            $horarios = \App\Models\HorarioMateria::where('idContrato', $contrato->id)
                ->where('estado', 'ASIGNADO')
                ->where(function ($q) use ($inicio, $fin) {
                    $q->whereBetween('fechaInicial', [$inicio, $fin])
                        ->orWhereBetween('fechaFinal', [$inicio, $fin])
                        ->orWhere(function ($q2) use ($inicio, $fin) {
                            $q2->where('fechaInicial', '<=', $inicio)
                                ->where('fechaFinal', '>=', $fin);
                        });
                })
                ->pluck('id');

            if ($horarios->isEmpty()) {
                DB::rollBack();
                return response()->json(['message' => 'No se encontraron horarios para el periodo especificado'], 404);
            }

            // Asegurar que existan los DetalleRmi y RMI para el periodo
            $rmi = Rmi::firstOrCreate(
                ['periodo' => $periodo],
                ['estado' => 'PENDIENTE', 'observacion' => null]
            );

            // Crear o actualizar DetalleRmi relacionados
            $detallesActualizados = 0;
            foreach ($horarios as $idHorario) {
                $detalle = DetalleRmi::firstOrCreate(
                    [
                        'idRmi' => $rmi->id,
                        'idHorarioMateria' => $idHorario,
                    ],
                    [
                        'estado' => 'PENDIENTE',
                        'observacion' => null,
                    ]
                );

                $detalle->update([
                    'estado' => 'ACEPTADO',
                    'observacion' => null
                ]);
                $detallesActualizados++;
            }

            // Actualizar estado del RMI si todos los detalles están aceptados
            $todosAceptados = DetalleRmi::where('idRmi', $rmi->id)
                ->where('estado', '!=', 'ACEPTADO')
                ->doesntExist();

            if ($todosAceptados) {
                $rmi->update(['estado' => 'ACEPTADO']);
            }
            $user = KeyUtil::user(); //Con esto atrapo el id del usuario que rechaza el rmi:

            $this->enviarNotificacionRmi($user->id, $activation->user->id, $periodo, null, 'ACEPTADO');

            DB::commit();

            $email = $validated['email'];
            $nombre = $activation->user->persona->nombre1;

            $asunto  = 'Aprobación de RMI - Periodo ' . $periodo;
            $mensaje = "Estimado(a) $nombre,\n\n"
                . "Nos complace informarle que su RMI correspondiente al periodo $periodo ha sido APROBADO.\n\n"
                . "No se requieren acciones adicionales por su parte.\n\n"
                . "Si tiene alguna inquietud, puede comunicarse con el equipo administrativo.\n\n"
                . "Atentamente,\n"
                . "Equipo Administrativo\n"
                . "Sistema de Gestión Académica";

            \App\Jobs\SendBasicEmail::dispatch($email, $asunto, $mensaje);

            return response()->json([
                'message' => 'RMI aceptado con éxito',
                'estado' => 'ACEPTADO',
                'detalles_actualizados' => $detallesActualizados
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error de validación', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error al aceptar RMI: ' . $e->getMessage(), [
                'idActivation' => $idActivation,
                'periodo' => $request->input('periodo'),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['message' => 'Error al aceptar el RMI', 'error' => $e->getMessage()], 500);
        }
    }

    public function rechazarRmi($idActivation, Request $request)
    {
        DB::beginTransaction();
        try {
            $validated = $request->validate([
                'periodo' => 'nullable|date_format:Y-m',
                'motivo' => 'required|string|max:500',
                'email' => 'required|email'
            ]);

            $activation = ActivationCompanyUser::with('user.persona.contracts')->findOrFail($idActivation);
            $contrato = $activation->user->persona->contracts->first();

            if (!$contrato) {
                DB::rollBack();
                return response()->json(['message' => 'El instructor no tiene un contrato asociado'], 404);
            }

            $periodo = $validated['periodo'] ?? \Carbon\Carbon::now()->format('Y-m');
            $inicio = \Carbon\Carbon::createFromFormat('Y-m', $periodo)->startOfMonth();
            $fin = \Carbon\Carbon::createFromFormat('Y-m', $periodo)->endOfMonth();

            // Obtener horarios del contrato en el periodo
            $horarios = \App\Models\HorarioMateria::where('idContrato', $contrato->id)
                ->where('estado', 'ASIGNADO')
                ->where(function ($q) use ($inicio, $fin) {
                    $q->whereBetween('fechaInicial', [$inicio, $fin])
                        ->orWhereBetween('fechaFinal', [$inicio, $fin])
                        ->orWhere(function ($q2) use ($inicio, $fin) {
                            $q2->where('fechaInicial', '<=', $inicio)
                                ->where('fechaFinal', '>=', $fin);
                        });
                })
                ->pluck('id');

            if ($horarios->isEmpty()) {
                DB::rollBack();
                return response()->json(['message' => 'No se encontraron horarios para el periodo especificado'], 404);
            }

            // Asegurar que existan los DetalleRmi y RMI para el periodo
            $rmi = Rmi::firstOrCreate(
                ['periodo' => $periodo],
                ['estado' => 'PENDIENTE', 'observacion' => null]
            );

            // Crear o actualizar DetalleRmi relacionados
            $detallesActualizados = 0;
            foreach ($horarios as $idHorario) {
                $detalle = DetalleRmi::firstOrCreate(
                    [
                        'idRmi' => $rmi->id,
                        'idHorarioMateria' => $idHorario,
                    ],
                    [
                        'estado' => 'PENDIENTE',
                        'observacion' => null,
                    ]
                );

                $detalle->update([
                    'estado' => 'RECHAZADO',
                    'observacion' => $validated['motivo']
                ]);
                $detallesActualizados++;
            }
            // Aqui empieza la prueba para el envio de la notificación:

            $user = KeyUtil::user(); //Con esto atrapo el id del usuario que rechaza el rmi:

            $this->enviarNotificacionRmi($user->id, $activation->user->id, $periodo, $validated['motivo'], 'RECHAZADO');

            DB::commit();

            $email = $validated['email'];

            $nombre = $activation->user->persona->nombre1;

            $nombre   = $activation->user->persona->nombre1;
            $asunto   = 'Rechazo de RMI - Periodo ' . $periodo;
            $mensaje  = "Estimado(a) $nombre,\n\n"
                . "Le informamos que su RMI correspondiente al periodo $periodo ha sido RECHAZADO.\n\n"
                . "Motivo del rechazo:\n"
                . $validated['motivo'] . "\n\n"
                . "Por favor revise las observaciones y realice las correcciones necesarias.\n\n"
                . "Atentamente,\n"
                . "Equipo administrativo";

            \App\Jobs\SendBasicEmail::dispatch($email, $asunto, $mensaje);

            return response()->json([
                'message' => 'RMI rechazado con éxito',
                'estado' => 'RECHAZADO',

            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error de validación', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error al rechazar RMI: ' . $e->getMessage(), [
                'idActivation' => $idActivation,
                'periodo' => $request->input('periodo'),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['message' => 'Error al rechazar el RMI', 'error' => $e->getMessage()], 500);
        }
    }
    protected function enviarNotificacionRmi($remitenteId, $receptorId, $periodo, $motivo = null, $tipo = 'ACEPTADO')
    {
        $asunto = $tipo === 'ACEPTADO' ? 'Estado del RMI' : 'Estado del RMI';
        $mensaje = $tipo === 'ACEPTADO'
            ? "Su RMI del periodo {$periodo} ha sido aprobado."
            : "Su RMI del periodo {$periodo} ha sido rechazado. Motivo: {$motivo}";

        return NotificacionSistema::create([
            'fecha' => now()->toDateString(),
            'hora' => now()->toTimeString(),
            'asunto' => $asunto,
            'mensaje' => $mensaje,
            'estado_id' => 1,
            'idUsuarioReceptor' => $receptorId,
            'idUsuarioRemitente' => $remitenteId,
            'idTipoNotificacion' => 1,
            'idEmpresa' => KeyUtil::idCompany(),
            'route' => '/rmi'
        ]);
    }
    public function revertirRmi($idActivation, Request $request)
    {
        DB::beginTransaction();
        try {
            $validated = $request->validate([
                'periodo' => 'nullable|date_format:Y-m',
            ]);

            $activation = ActivationCompanyUser::with('user.persona.contracts')->findOrFail($idActivation);
            $contrato = $activation->user->persona->contracts->first();

            if (!$contrato) {
                DB::rollBack();
                return response()->json(['message' => 'El instructor no tiene un contrato asociado'], 404);
            }

            $periodo = $validated['periodo'] ?? \Carbon\Carbon::now()->format('Y-m');
            $inicio  = \Carbon\Carbon::createFromFormat('Y-m', $periodo)->startOfMonth();
            $fin     = \Carbon\Carbon::createFromFormat('Y-m', $periodo)->endOfMonth();

            // Obtener horarios del contrato en el periodo
            $horarios = \App\Models\HorarioMateria::where('idContrato', $contrato->id)
                ->where('estado', 'ASIGNADO')
                ->where(function ($q) use ($inicio, $fin) {
                    $q->whereBetween('fechaInicial', [$inicio, $fin])
                        ->orWhereBetween('fechaFinal', [$inicio, $fin])
                        ->orWhere(function ($q2) use ($inicio, $fin) {
                            $q2->where('fechaInicial', '<=', $inicio)
                                ->where('fechaFinal', '>=', $fin);
                        });
                })
                ->pluck('id');

            if ($horarios->isEmpty()) {
                DB::rollBack();
                return response()->json(['message' => 'No se encontraron horarios para el periodo especificado'], 404);
            }

            $rmi = Rmi::where('periodo', $periodo)->first();

            if (!$rmi) {
                DB::rollBack();
                return response()->json(['message' => 'No existe un RMI para el periodo especificado'], 404);
            }

            // Revertir todos los DetalleRmi a PENDIENTE
            $detallesActualizados = DetalleRmi::where('idRmi', $rmi->id)
                ->whereIn('idHorarioMateria', $horarios)
                ->update([
                    'estado'      => 'PENDIENTE',
                    'observacion' => null,
                ]);

            // Revertir estado del RMI a PENDIENTE
            $rmi->update(['estado' => 'PENDIENTE']);

            $user = KeyUtil::user();
            $this->enviarNotificacionRmi($user->id, $activation->user->id, $periodo, null, 'PENDIENTE');

            DB::commit();

            return response()->json([
                'message'              => 'RMI revertido a pendiente con éxito',
                'estado'               => 'PENDIENTE',
                'detalles_actualizados' => $detallesActualizados,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error de validación', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error al revertir RMI: ' . $e->getMessage(), [
                'idActivation' => $idActivation,
                'periodo'      => $request->input('periodo'),
                'trace'        => $e->getTraceAsString(),
            ]);
            return response()->json(['message' => 'Error al revertir el RMI', 'error' => $e->getMessage()], 500);
        }
    }


    // cambiar el estado de asociacion de un detalleRmi
    public function setEstadoAsociacion($idGradoMateria, Request $request)
    {
        try {
            $estadoAsociacion = $request->input('estado');
            if ($idGradoMateria == null) {
                return response()->json(['message' => 'Parametros no proporcionados'], 400);
            }

            $detallesRmi = DetalleRmi::whereHas('horarioMateria', function ($query) use ($idGradoMateria) {
                $query->where('idGradoMateria', $idGradoMateria);
            })->get();

            foreach ($detallesRmi as $detalle) {
                $detalle->estadoAsociacion = $estadoAsociacion;
                $detalle->save();
            }

            return response()->json([
                'message' => 'Estado de la asociación actualizado correctamente',
                'detalles' => $detallesRmi
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['message' => 'Error de validación', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al actualizar el estado de la asociación', 'error' => $e->getMessage()], 500);
        }
    }
    /**
     * Dashboard del instructor autenticado.
     * No requiere parámetros: usa auth()->user() para obtener el contrato activo.
     * Devuelve fichas con RAPs (resultados planos), sesiones y horas del mes actual.
     */
    public function getDashboardInstructor(Request $request)
    {
        try {
            $user    = auth()->user();
            $persona = $user?->persona;

            if (!$persona) {
                return response()->json(['fichas' => [], 'message' => 'Sin persona asociada'], 200);
            }

            // Contrato activo del instructor
            $contrato = $persona->contracts()->latest()->first();

            if (!$contrato) {
                return response()->json(['fichas' => [], 'message' => 'Sin contrato activo'], 200);
            }

            $inicio = \Carbon\Carbon::now()->startOfMonth();
            $fin    = \Carbon\Carbon::now()->endOfMonth();

            $horarios = \App\Models\HorarioMateria::with([
                'ficha.asignacion.programa',
                'gradoMateria.materia.padre',
            ])
                ->where('idContrato', $contrato->id)
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

            $fichas = $horarios->groupBy('idFicha')->map(function ($grupo) use ($inicio, $fin) {
                $ficha    = $grupo->first()->ficha;
                $programa = $ficha?->asignacion?->programa;

                // Resultados planos (un registro por horario)
                $resultados = $grupo->map(function ($h) use ($inicio, $fin) {
                    $rap         = $h->gradoMateria?->materia;
                    $competencia = $rap?->padre;
                    $durSesion   = round((strtotime($h->horaFinal) - strtotime($h->horaInicial)) / 3600, 2);

                    $desde = \Carbon\Carbon::parse($h->fechaInicial)->max($inicio);
                    $hasta = \Carbon\Carbon::parse($h->fechaFinal)->min($fin);

                    $diaSemanaCarbon = $h->idDia === 7 ? 0 : $h->idDia;
                    $cantSesiones    = 0;
                    $cursor          = $desde->copy();
                    while ($cursor->lte($hasta)) {
                        if ($cursor->dayOfWeek === $diaSemanaCarbon) $cantSesiones++;
                        $cursor->addDay();
                    }

                    return [
                        'idHorario'            => $h->id,
                        'competencia'          => $competencia?->nombreMateria,
                        'resultadoAprendizaje' => $rap?->nombreMateria,
                        'horaInicial'          => $h->horaInicial,
                        'horaFinal'            => $h->horaFinal,
                        'fechaInicial'         => $h->fechaInicial,
                        'fechaFinal'           => $h->fechaFinal,
                        'idDia'                => $h->idDia,
                        'duracionSesion'       => $durSesion,
                        'cantidadSesiones'     => $cantSesiones,
                        'duracionHoras'        => round($durSesion * $cantSesiones, 2),
                    ];
                })->values();

                return [
                    'idFicha'           => $ficha?->id,
                    'codigoFicha'       => $ficha?->codigo,
                    'programaFormacion' => $programa?->nombrePrograma,
                    'codigoPrograma'    => $programa?->codigoPrograma,
                    'resultados'        => $resultados,
                ];
            })->values();

            return response()->json(['fichas' => $fichas]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    //Dejo preparado para agregarlos endpoints del contrato para el instructor...
    public function getContratoByInstructor(Request $request)
    {
        $user    = auth()->user();
        $persona = $user?->persona;

        $contrato = Contract::where('idpersona', $persona->id)->where('idEstado', 1)->with('centroFormacion', 'persona.ciudadExpedicionRel.departamento')->get();

        return response()->json(['contrato' => $contrato]);
    }
    public function updateSupervisor(Request $request, int $id)
    {
        $request->validate([
            'supervisorContrato'  => 'nullable|string|max:255',
            'cargoSupervisor'     => 'nullable|string|max:255',
            'objetoContrato'      => 'nullable|string|max:1000',
            'formaDePago'         => 'nullable|string|in:COMISIONES,SALARIO INTEGRAL,NORMAL',
            'ciudadExpedicionId'  => 'nullable|integer|exists:ciudad,id',
            'siif' => 'nullable|numeric',
            'descripcionFormaPago' => 'nullable|string|max:1000',
        ]);

        DB::transaction(function () use ($request, $id) {
            $contrato = Contract::with('persona')->findOrFail($id);

            $contrato->update($request->only([
                'supervisorContrato',
                'cargoSupervisor',
                'objetoContrato',
                'formaDePago',
                'siif',
                'descripcionFormaPago'
            ]));

            if ($request->filled('ciudadExpedicionId')) {
                $contrato->persona->update([
                    'ciudadExpedicion' => $request->ciudadExpedicionId,
                ]);
            }
        });

        return response()->json(['message' => 'Contrato actualizado correctamente.']);
    }
    public function getYearsContractPerson(Request $request): JsonResponse
    {
        $idPerson = $request->idPerson ?? KeyUtil::user()->idPersona;

        $contracts = Contract::where('idpersona', $idPerson)->get();

        $years = [];

        foreach ($contracts as $contract) {
            $fechaInicio = Carbon::parse($contract->fechaContratacion);

            // Si no tiene fecha fin, usar el año actual como límite
            $fechaFin = $contract->fechaFinalContrato
                ? Carbon::parse($contract->fechaFinalContrato)
                : Carbon::now();

            for ($year = $fechaInicio->year; $year <= $fechaFin->year; $year++) {
                $years[] = $year;
            }
        }

        $yearsUnicos = array_unique($years);
        sort($yearsUnicos);

        return response()->json(array_values($yearsUnicos));
    }
    public function getDataRmiConfiguracionByYear(Request $request)
    {
        $year     = $request->year;
        $idPerson = $request->idPerson ?? KeyUtil::user()->idPersona;

        if (!$year) {
            return response()->json(['message' => 'El año es requerido.'], 400);
        }

        $contracts = Contract::where('idEstado', 1)
            ->where('idpersona', $idPerson)
            ->where(function ($query) use ($year) {
                $query->whereYear('fechaContratacion', '<=', $year)
                    ->whereYear('fechaFinalContrato', '>=', $year);
            })
            ->with(['horarioMateria.detallesRmi.rmi'])
            ->get();

        if ($contracts->isEmpty()) {
            return response()->json(['message' => 'No hay contratos válidos para el año especificado.'], 404);
        }

        $data = $contracts->map(function ($contract) use ($year) {
            $periodos = [];

            foreach ($contract->horarioMateria as $horario) {
                foreach ($horario->detallesRmi as $detalle) {
                    $rmi    = $detalle->rmi;
                    $period = $rmi->periodo;

                    if (!str_starts_with($period, $year)) continue;

                    if (!isset($periodos[$period])) {
                        // Calcular horas asignadas en ese periodo (mismo cálculo que getFichasByContrato)
                        $inicio = Carbon::createFromFormat('Y-m', $period)->startOfMonth();
                        $fin    = Carbon::createFromFormat('Y-m', $period)->endOfMonth();

                        $horasAsignadas = 0;

                        foreach ($contract->horarioMateria as $h) {
                            $desde = Carbon::parse($h->fechaInicial)->max($inicio);
                            $hasta = Carbon::parse($h->fechaFinal ?? Carbon::now())->min($fin);

                            if ($desde->gt($hasta)) continue;

                            $duracionSesion  = (strtotime($h->horaFinal) - strtotime($h->horaInicial)) / 3600;
                            $diaSemanaCarbon = $h->idDia === 7 ? 0 : $h->idDia;
                            $cantSesiones    = 0;
                            $cursor          = $desde->copy();

                            while ($cursor->lte($hasta)) {
                                if ($cursor->dayOfWeek === $diaSemanaCarbon) $cantSesiones++;
                                $cursor->addDay();
                            }

                            $horasAsignadas += round($duracionSesion * $cantSesiones, 2);
                        }

                        $detallesDelPeriodo = $contract->horarioMateria->flatMap(function ($h) use ($rmi) {
                            return $h->detallesRmi->where('idRmi', $rmi->id);
                        });

                        $estadoConsolidado = 'PENDIENTE';
                        if ($detallesDelPeriodo->isNotEmpty()) {
                            if ($detallesDelPeriodo->contains('estado', 'RECHAZADO')) {
                                $estadoConsolidado = 'RECHAZADO';
                            } elseif ($detallesDelPeriodo->every(fn($d) => $d->estado === 'ACEPTADO')) {
                                $estadoConsolidado = 'ACEPTADO';
                            }
                        }

                        $periodos[$period] = [
                            'periodo'        => $period,
                            'idRmi'          => $rmi->id,
                            'estadoRmi'      => $estadoConsolidado,
                            'observacion'    => $rmi->observacion,
                            'horasAsignadas' => round($horasAsignadas, 2),
                            'detalles'       => [],
                        ];
                    }

                    $periodos[$period]['detalles'][] = [
                        'idDetalleRmi'     => $detalle->id,
                        'estadoDetalle'    => $detalle->estado,
                        'observacion'      => $detalle->observacion,
                        'idHorarioMateria' => $horario->id,
                        'horaInicial'      => $horario->horaInicial,
                        'horaFinal'        => $horario->horaFinal,
                        'fechaInicial'     => $horario->fechaInicial,
                        'fechaFinal'       => $horario->fechaFinal,
                        'estadoHorario'    => $horario->estado,
                        'archivoPago'      => $detalle->archivoPago,
                        'archivoPagoUrl'   => $detalle->archivoPagoUrl,
                    ];
                }
            }

            ksort($periodos);

            return [
                'idContrato'        => $contract->id,
                'siif'             => $contract->siif,
                'identificacion'   => $contract->persona->identificacion,
                'fechaContratacion' => $contract->fechaContratacion,
                'fechaFinal'        => $contract->fechaFinalContrato,
                'periodos'          => array_values($periodos),
            ];
        });

        return response()->json($data);
    }
    // Ruta: POST detalle_rmi/archivo_pago_periodo
    public function uploadArchivoPagoPeriodo(Request $request)
    {
        $request->validate([
            'archivoPago' => 'required|file|mimes:pdf,jpg,jpeg,png',
            'idRmi' => 'required|integer',
        ]);

        $ruta = '/storage/' . $request->file('archivoPago')->store('pagos', 'public');

        // Actualizar todos los detalleRmi del periodo (mismo idRmi)
        DetalleRmi::where('idRmi', $request->idRmi)
            ->update(['archivoPago' => $ruta]);

        return response()->json(['archivoPago' => $ruta]);
    }
    // Ruta: POST merge_pdfs
    public function mergePdfs(Request $request)
    {
        $request->validate([
            'pdfs' => 'required|array',
            'pdfs.*' => 'required|file|mimes:pdf',
        ]);

        $pdf = new \setasign\Fpdi\Fpdi();

        try {

            foreach ($request->file('pdfs') as $file) {
                $path = $file->getPathname();

                $pageCount = $pdf->setSourceFile($path);

                for ($i = 1; $i <= $pageCount; $i++) {
                    $tpl = $pdf->importPage($i);
                    $size = $pdf->getTemplateSize($tpl);

                    $pdf->AddPage(
                        $size['orientation'],
                        [$size['width'], $size['height']]
                    );

                    $pdf->useTemplate($tpl);
                }
            }
        } catch (\setasign\Fpdi\PdfParser\CrossReference\CrossReferenceException $e) {

            return response()->json([
                'message' => 'Uno de los PDFs está protegido. Debe imprimirlo como PDF nuevamente antes de subirlo.',
                'type' => 'PDF_ENCRYPTED'
            ], 422);
        } catch (\Exception $e) {

            return response()->json([
                'message' => 'Error procesando los PDFs',
            ], 500);
        }

        $output = $pdf->Output('S');

        return response($output, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="documento_unido.pdf"',
        ]);
    }
    public function getInformeByInstructorRmi(Request $request)
    {
        $idContrato = $request->idContrato;
        $idRmi      = $request->idRmi;
        $nPlanilla  = $request->nPlanilla;

        $rmi      = Rmi::findOrFail($idRmi);
        $contrato = Contract::with([
            'persona',
            'persona.ciudadExpedicionRel',
            'persona.ciudadExpedicionRel.departamento',
            'centroFormacion'
        ])->findOrFail($idContrato);

        $actividades = ActividadInstructor::where('idRmi', $idRmi)->where('idContrato', $idContrato)->get();
        $comisiones  = ComisionInstructor::where('idRmi', $idRmi)
            ->where('idContrato', $idContrato)
            ->get();


        $actividadesContrato = ActividadContrato::where('idContrato', $idContrato)->orderBy('created_at', 'asc')->get();

        // ── HORARIOS DEL PERIODO ──────────────────────────────────────────────
        $inicio = \Carbon\Carbon::createFromFormat('Y-m', $rmi->periodo)->startOfMonth();
        $fin    = \Carbon\Carbon::createFromFormat('Y-m', $rmi->periodo)->endOfMonth();

        $diasSemana = [1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo'];

        $horariosPorFicha = \App\Models\HorarioMateria::with([
            'ficha.asignacion.programa',
        ])
            ->where('idContrato', $idContrato)
            ->where('estado', 'ASIGNADO')
            ->where(function ($q) use ($inicio, $fin) {
                $q->whereBetween('fechaInicial', [$inicio, $fin])
                    ->orWhereBetween('fechaFinal', [$inicio, $fin])
                    ->orWhere(function ($q2) use ($inicio, $fin) {
                        $q2->where('fechaInicial', '<=', $inicio)
                            ->where('fechaFinal', '>=', $fin);
                    });
            })
            ->get()
            ->groupBy('idFicha')
            ->map(function ($horarios) use ($inicio, $fin, $diasSemana) {
                $ficha    = $horarios->first()->ficha;
                $programa = $ficha?->asignacion?->programa;
                $idFicha  = $horarios->first()->idFicha;

                $filas = $horarios->map(function ($h) use ($inicio, $fin, $diasSemana) {
                    $desde           = \Carbon\Carbon::parse($h->fechaInicial)->max($inicio);
                    $hasta           = \Carbon\Carbon::parse($h->fechaFinal)->min($fin);
                    $diaSemanaCarbon = $h->idDia === 7 ? 0 : $h->idDia;
                    $cantSesiones    = 0;
                    $cursor          = $desde->copy();

                    while ($cursor->lte($hasta)) {
                        if ($cursor->dayOfWeek === $diaSemanaCarbon) $cantSesiones++;
                        $cursor->addDay();
                    }

                    $duracionSesion = round((strtotime($h->horaFinal) - strtotime($h->horaInicial)) / 3600, 2);

                    return [
                        'dia'            => $diasSemana[$h->idDia] ?? $h->idDia,
                        'horaInicial'    => $h->horaInicial,
                        'horaFinal'      => $h->horaFinal,
                        'cantSesiones'   => $cantSesiones,
                        'horasTotales'   => round($duracionSesion * $cantSesiones, 2),
                    ];
                })->values();

                return [
                    'idFicha'           => $idFicha,
                    'codigoFicha'       => $ficha?->codigo,
                    'programaFormacion' => $programa?->nombrePrograma,
                    'filas'             => $filas,
                    'totalHorasFicha'   => $filas->sum('horasTotales'),
                ];
            })->values();
        // ─────────────────────────────────────────────────────────────────────

        // ── MATERIAS / RAPs POR FICHA CON FASE DE PROYECTO ───────────────────────────

        $horariosMaterias = \App\Models\HorarioMateria::with([
            'ficha.asignacion.programa',
            'gradoMateria.materia.padre',
        ])
            ->where('idContrato', $idContrato)
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

        // IDs únicos de los RAPs que se están impartiendo en el periodo
        $idMaterias = $horariosMaterias
            ->pluck('gradoMateria.materia.id')
            ->filter()
            ->unique()
            ->values();

        // Usar la relación belongsToMany de FaseProyecto para traer
        // las fases que tienen esos RAPs, con proyecto y actividades
        $fasesPorMateria = \App\Models\FaseProyectoRap::with([
            'fase.proyectoFormativo',
            'fase.actividades',
        ])
            ->whereIn('idMateria', $idMaterias)
            ->get()
            ->keyBy('idMateria'); // clave: idMateria → fácil lookup

        // Agrupar por ficha
        $fichasConProyecto = $horariosMaterias
            ->groupBy('idFicha')
            ->map(function ($horariosGrupo) use ($fasesPorMateria) {
                $ficha    = $horariosGrupo->first()->ficha;
                $programa = $ficha?->asignacion?->programa;

                $materias = $horariosGrupo
                    ->map(function ($h) use ($fasesPorMateria) {
                        $rap         = $h->gradoMateria?->materia;
                        $competencia = $rap?->padre;
                        $idMateria   = $rap?->id;

                        if (!$idMateria) return null;

                        // Buscar la fase asociada a este RAP
                        $fprRap = $fasesPorMateria->get($idMateria);
                        $fase   = $fprRap?->fase;

                        return [
                            'idMateria'            => $idMateria,
                            'resultadoAprendizaje' => $rap?->nombreMateria,
                            'competencia'          => $competencia?->nombreMateria,
                            'faseProyecto'         => $fase?->descripcionFase,
                            'proyectoFormativo'    => $fase?->proyectoFormativo?->nombreProyecto,
                            'actividades'          => $fase?->actividades
                                ?->map(fn($a) => [
                                    'id'                   => $a->id,
                                    'descripcionActividad' => $a->descripcionActividad,
                                ])->values()->toArray() ?? [],
                        ];
                    })
                    ->filter()
                    ->unique('idMateria')
                    ->values();

                return [
                    'idFicha'           => $ficha?->id,
                    'codigoFicha'       => $ficha?->codigo,
                    'programaFormacion' => $programa?->nombrePrograma,
                    'materias'          => $materias,
                ];
            })->values();

        // ─────────────────────────────────────────────────────────────────────────────

        // ── APRENDICES CON RETIRO VOLUNTARIO O TRASLADADO POR FICHA ──────────────────

        $idFichas = $horariosMaterias->pluck('idFicha')->filter()->unique()->values();

        $aprendicesPorFicha = \App\Models\Matricula::with(['person'])
            ->whereIn('idFicha', $idFichas)
            ->whereIn('estado', ['RETIRO VOLUNTARIO', 'TRASLADADO'])
            ->get()
            ->groupBy('idFicha')
            ->map(function ($matriculas) {
                return $matriculas->map(function ($matricula) {
                    $person = $matricula->person;
                    return [
                        'idMatricula'    => $matricula->id,
                        'identificacion' => $person?->identificacion,
                        'nombreCompleto' => trim(implode(' ', array_filter([
                            $person?->nombre1,
                            $person?->nombre2,
                            $person?->apellido1,
                            $person?->apellido2,
                        ]))),
                        'estado'         => $matricula->estado,
                        'observacion'    => $matricula->observacion,
                    ];
                })->values();
            });

        // ─────────────────────────────────────────────────────────────────────────────

        $pdf = Pdf::loadView('pdf.informeinstructor', compact(
            'rmi',
            'contrato',
            'actividades',
            'comisiones',
            'nPlanilla',
            'horariosPorFicha',
            'actividadesContrato',
            'fichasConProyecto',
            'aprendicesPorFicha'
        ))
            ->setPaper('letter')
            ->setOption('isPhpEnabled', true)
            ->setOption('isHtml5ParserEnabled', true)
            ->setOption('isFontSubsettingEnabled', true);

        return $pdf->stream("RMI_{$idContrato}_{$idRmi}.pdf");
    }
}
