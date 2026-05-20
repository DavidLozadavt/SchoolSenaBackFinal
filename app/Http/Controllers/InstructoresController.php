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
use App\Models\Person;
use App\Models\Rmi;
use App\Models\GC;
use App\Models\AsignacionSesion;
use App\Models\SesionMateria;
use App\Models\User;
use App\Util\KeyUtil;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\JsonResponse;

class InstructoresController extends Controller
{
    public function getInstructors(Request $request)
    {
        $validated = $request->validate([
            'idCentroFormacion' => 'required|integer|exists:centroFormacion,id',
            'periodo' => 'nullable|date_format:Y-m',
        ]);

        // Determinar periodo
        $periodoReq = $validated['periodo'] ?? \Carbon\Carbon::now()->format('Y-m');
        $inicio = \Carbon\Carbon::createFromFormat('Y-m', $periodoReq)->startOfMonth();
        $fin = \Carbon\Carbon::createFromFormat('Y-m', $periodoReq)->endOfMonth();

        // Cargar el RMI del periodo actual una sola vez
        $rmi = Rmi::where('periodo', $periodoReq)->first();

        $instructors = ActivationCompanyUser::with([
            'user.persona.contracts' => function ($q) use ($inicio, $fin) {
                $q->with([
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
                        )->where('estado', '!=', 'PENDIENTE')
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
            ->where(function ($query) use ($inicio, $fin) {
                $query->whereHas('user.persona.contracts.horarioMateria', function ($h) use ($inicio, $fin) {
                    $h->where('estado', '!=', 'PENDIENTE')
                        ->where(function ($q) use ($inicio, $fin) {
                            $q->whereBetween('fechaInicial', [$inicio, $fin])
                                ->orWhereBetween('fechaFinal', [$inicio, $fin])
                                ->orWhere(function ($q2) use ($inicio, $fin) {
                                    $q2->where('fechaInicial', '<=', $inicio)
                                        ->where('fechaFinal', '>=', $fin);
                                });
                        });
                })
                ->orWhereHas('user.persona.contracts.asignacionSesion', function ($a) use ($inicio, $fin) {
                    $a->where('tipoAsignacion', 'REEMPLAZO')
                        ->where(function ($q) use ($inicio, $fin) {
                            $q->whereBetween('fechaInicio', [$inicio, $fin])
                                ->orWhereBetween('fechaFin', [$inicio, $fin])
                                ->orWhere(function ($q2) use ($inicio, $fin) {
                                    $q2->where('fechaInicio', '<=', $inicio)
                                        ->where('fechaFin', '>=', $fin);
                                });
                        });
                });
            })
            ->get()
            ->flatMap(function ($acu) use ($inicio, $fin, $rmi) {

                $user = $acu->user;
                $persona = $user->persona;
                // Buscar el contrato que tiene horarios o reemplazos en este periodo
                $contrato = $persona->contracts->first(fn($c) => 
                    $c->horarioMateria->isNotEmpty() || $c->asignacionSesion->isNotEmpty()
                ) ?? $persona->contracts->first();

                if (!$contrato)
                    return [];

                // Reemplazos que este instructor está realizando
                $reemplazosHechos = AsignacionSesion::with('horario')
                    ->where('idContrato', $contrato->id)
                    ->where('tipoAsignacion', 'REEMPLAZO')
                    ->where(function ($q) use ($inicio, $fin) {
                        $q->whereBetween('fechaInicio', [$inicio, $fin])
                          ->orWhereBetween('fechaFin', [$inicio, $fin]);
                    })->get();

                // Obtener detallesRmi PENDIENTE/RECHAZADO del periodo actual para este contrato
                $detallesRmi = $rmi
                    ? DetalleRmi::where('idRmi', $rmi->id)
                    ->whereIn('estado', ['PENDIENTE', 'RECHAZADO'])
                    ->whereHas('horarioMateria', function ($q) use ($contrato) {
                        $q->where('idContrato', $contrato->id);
                    })
                    ->get()
                    : collect();

                // Si no tiene detallesRmi PENDIENTE/RECHAZADO Y no tiene reemplazos hechos, excluir
                if ($detallesRmi->isEmpty() && $reemplazosHechos->isEmpty())
                    return [];

                // IDs de horarios con detalleRmi PENDIENTE o RECHAZADO
                $idsConDetalle = $detallesRmi->pluck('idHorarioMateria')->toArray();

                $horariosFilas = collect();

                // Mapear horarios del mes que tienen detalleRmi relevante
                foreach ($contrato->horarioMateria->filter(fn($h) => in_array($h->id, $idsConDetalle)) as $h) {
                    $duracionSesion = round((strtotime($h->horaFinal) - strtotime($h->horaInicial)) / 3600, 2);
                    $desde = \Carbon\Carbon::parse($h->fechaInicial)->max($inicio);
                    $hasta = \Carbon\Carbon::parse($h->fechaFinal)->min($fin);
                    $idDiaInt = (int) $h->idDia;
                    $diaSemanaCarbon = $idDiaInt === 7 ? 0 : $idDiaInt;
                    $cantidadSesiones = 0;
                    $cursor = $desde->copy()->startOfDay();
                    $finalC = $hasta->copy()->startOfDay();

                    // Reemplazos que le hicieron a este horario
                    $reemplazosQueLeHicieron = AsignacionSesion::where('idHorarioMateria', $h->id)
                        ->where('tipoAsignacion', 'REEMPLAZO')
                        ->get();

                    while ($cursor->lte($finalC)) {
                        if ($cursor->dayOfWeek === $diaSemanaCarbon) {
                            $esReemplazo = $reemplazosQueLeHicieron->contains(function ($r) use ($cursor) {
                                $inicioR = \Carbon\Carbon::parse($r->fechaInicio)->startOfDay();
                                $finR = \Carbon\Carbon::parse($r->fechaFin)->startOfDay();
                                return $cursor->between($inicioR, $finR);
                            });
                            if (!$esReemplazo) {
                                $cantidadSesiones++;
                            }
                        }
                        $cursor->addDay();
                    }

                    if ($cantidadSesiones > 0) {
                        $horariosFilas->push([
                            'id' => $h->id,
                            'idContrato' => $h->idContrato,
                            'horaInicial' => $h->horaInicial,
                            'horaFinal' => $h->horaFinal,
                            'estado' => $h->estado,
                            'idDia' => $h->idDia,
                            'fechaInicial' => $h->fechaInicial,
                            'fechaFinal' => $h->fechaFinal,
                            'duracionSesion' => (float) $duracionSesion,
                            'cantidadSesiones' => (int) $cantidadSesiones,
                            'duracionHoras' => (float) round($duracionSesion * $cantidadSesiones, 2),
                        ]);
                    }
                }

                // Agregar reemplazos hechos por este instructor
                foreach ($reemplazosHechos as $r) {
                    $h = $r->horario;
                    if (!$h) continue;

                    $duracionSesion = round((strtotime($h->horaFinal) - strtotime($h->horaInicial)) / 3600, 2);
                    $desde = \Carbon\Carbon::parse($r->fechaInicio)->max($inicio);
                    $hasta = \Carbon\Carbon::parse($r->fechaFin)->min($fin);
                    $idDiaInt = (int) $h->idDia;
                    $diaSemanaCarbon = $idDiaInt === 7 ? 0 : $idDiaInt;
                    $cantidadSesiones = 0;
                    $cursor = $desde->copy()->startOfDay();
                    $finalC = $hasta->copy()->startOfDay();

                    while ($cursor->lte($finalC)) {
                        if ($cursor->dayOfWeek === $diaSemanaCarbon)
                            $cantidadSesiones++;
                        $cursor->addDay();
                    }

                    if ($cantidadSesiones > 0) {
                        $horariosFilas->push([
                            'id' => 'r' . $r->id,
                            'idContrato' => $contrato->id,
                            'horaInicial' => $h->horaInicial,
                            'horaFinal' => $h->horaFinal,
                            'estado' => 'REEMPLAZO',
                            'idDia' => $h->idDia,
                            'fechaInicial' => $r->fechaInicio,
                            'fechaFinal' => $r->fechaFin,
                            'duracionSesion' => (float) $duracionSesion,
                            'cantidadSesiones' => (int) $cantidadSesiones,
                            'duracionHoras' => (float) round($duracionSesion * $cantidadSesiones, 2),
                        ]);
                    }
                }

                $horariosBase = $horariosFilas->keyBy('id');

                // Horas ejecutadas en el mes actual (sesiones registradas para este contrato)
                $horasEjecutadas = round(
                    SesionMateria::join('horarioMateria', 'sesionMateria.idHorarioMateria', '=', 'horarioMateria.id')
                        ->whereBetween('sesionMateria.fechaSesion', [$inicio, $fin])
                        ->where(function ($q) use ($contrato) {
                            $q->where(function ($q2) use ($contrato) {
                                // Caso 1: El instructor es el original del horario
                                $q2->where('horarioMateria.idContrato', $contrato->id)
                                    // Y NO hubo un reemplazo en la fecha de la sesión
                                    ->whereNotExists(function ($sub) {
                                        $sub->select(DB::raw(1))
                                            ->from('asignacionSesion')
                                            ->whereColumn('asignacionSesion.idHorarioMateria', 'horarioMateria.id')
                                            ->where('asignacionSesion.tipoAsignacion', 'REEMPLAZO')
                                            ->whereColumn('sesionMateria.fechaSesion', '>=', 'asignacionSesion.fechaInicio')
                                            ->whereColumn('sesionMateria.fechaSesion', '<=', 'asignacionSesion.fechaFin');
                                    });
                            })
                            ->orWhere(function ($q2) use ($contrato) {
                                // Caso 2: El instructor es el reemplazo asignado para esa fecha
                                $q2->whereExists(function ($sub) use ($contrato) {
                                    $sub->select(DB::raw(1))
                                        ->from('asignacionSesion')
                                        ->whereColumn('asignacionSesion.idHorarioMateria', 'horarioMateria.id')
                                        ->where('asignacionSesion.idContrato', $contrato->id)
                                        ->where('asignacionSesion.tipoAsignacion', 'REEMPLAZO')
                                        ->whereColumn('sesionMateria.fechaSesion', '>=', 'asignacionSesion.fechaInicio')
                                        ->whereColumn('sesionMateria.fechaSesion', '<=', 'asignacionSesion.fechaFin');
                                });
                            });
                        })
                        ->selectRaw('SUM((TIME_TO_SEC(horarioMateria.horaFinal) - TIME_TO_SEC(horarioMateria.horaInicial)) / 3600) as totalHoras')
                        ->value('totalHoras') ?? 0
                );

                $personaData = [
                    'identificacion' => $persona->identificacion,
                    'nombre1' => $persona->nombre1,
                    'nombre2' => $persona->nombre2,
                    'apellido1' => $persona->apellido1,
                    'apellido2' => $persona->apellido2,
                    'fechaNac' => $persona->fechaNac,
                    'direccion' => $persona->direccion,
                    'email' => $persona->email,
                    'celular' => $persona->celular,
                    'telefonoFijo' => $persona->telefonoFijo,
                            'perfil' => $persona->perfil,
                    'sexo' => $persona->sexo,
                    'rh' => $persona->rh,
                    'rutaFoto' => $persona->rutaFotoUrl
                ];

                // Agrupar por estado → una entrada por cada estado distinto
                $grupos = $detallesRmi->groupBy('estado');

                // Si hay reemplazos pero no hay detalles, crear un grupo virtual "PENDIENTE" para mostrar los reemplazos
                if ($grupos->isEmpty() && $reemplazosHechos->isNotEmpty()) {
                    $grupos->put('PENDIENTE', collect());
                }

                return $grupos
                    ->map(function ($grupo, $estado) use ($acu, $user, $contrato, $personaData, $horariosBase, $horasEjecutadas, $rmi) {
                        $idsGrupo = $grupo->pluck('idHorarioMateria')->toArray();
                        
                        // Incluir IDs de reemplazos ('rX') en el listado de horarios
                        $idsReemplazos = $horariosBase->keys()->filter(fn($id) => str_starts_with($id, 'r'))->toArray();
                        $todosLosIds = array_merge($idsGrupo, $idsReemplazos);

                        $motivoRechazo = $estado === 'RECHAZADO'
                            ? $grupo->first(fn($d) => !empty($d->observacion))?->observacion
                            : null;

                        return [
                            'idActivation' => $acu->id,
                            'emailUsuario' => $user->email,
                            'idContrato' => $contrato->id,
                            'idRmi' => $rmi?->id,
                            'roles' => $acu->getRoleNames(),
                            'estado' => $estado,
                            'totalHoras' => $contrato->horasmes ?? 0,
                            'totalHorasFormato' => $horasEjecutadas,
                            'motivoRechazo' => $motivoRechazo,
                            'horarios' => $horariosBase->only($todosLosIds)->values(),
                            'persona' => $personaData,
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
            'periodo' => 'nullable|date_format:Y-m',
            'estado' => 'nullable|string|in:PENDIENTE,ACEPTADO,RECHAZADO',
        ]);

        // Determinar periodo
        $periodoReq = $validated['periodo'] ?? \Carbon\Carbon::now()->format('Y-m');
        $inicio = \Carbon\Carbon::createFromFormat('Y-m', $periodoReq)->startOfMonth();
        $fin = \Carbon\Carbon::createFromFormat('Y-m', $periodoReq)->endOfMonth();

        $instructors = ActivationCompanyUser::with([
            'user.persona.contracts' => function ($q) use ($inicio, $fin) {
                $q->with([
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
                        )->where('estado', '!=', 'PENDIENTE');

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
            ->where(function ($query) use ($inicio, $fin) {
                $query->whereHas('user.persona.contracts.horarioMateria', function ($h) use ($inicio, $fin) {
                    $h->where('estado', '!=', 'PENDIENTE')
                        ->where(function ($q) use ($inicio, $fin) {
                            $q->whereBetween('fechaInicial', [$inicio, $fin])
                                ->orWhereBetween('fechaFinal', [$inicio, $fin])
                                ->orWhere(function ($q2) use ($inicio, $fin) {
                                    $q2->where('fechaInicial', '<=', $inicio)
                                        ->where('fechaFinal', '>=', $fin);
                                });
                        });
                })
                ->orWhereHas('user.persona.contracts.asignacionSesion', function ($a) use ($inicio, $fin) {
                    $a->where('tipoAsignacion', 'REEMPLAZO')
                        ->where(function ($q) use ($inicio, $fin) {
                            $q->whereBetween('fechaInicio', [$inicio, $fin])
                                ->orWhereBetween('fechaFin', [$inicio, $fin])
                                ->orWhere(function ($q2) use ($inicio, $fin) {
                                    $q2->where('fechaInicio', '<=', $inicio)
                                        ->where('fechaFin', '>=', $fin);
                                });
                        });
                });
            })
            ->get()
            ->map(function ($acu) use ($periodoReq, $inicio, $fin) {

                $user = $acu->user;
                $persona = $user->persona;
                // Buscar el contrato que tiene horarios o reemplazos en este periodo
                $contrato = $persona->contracts->first(fn($c) => 
                    $c->horarioMateria->isNotEmpty() || $c->asignacionSesion->isNotEmpty()
                ) ?? $persona->contracts->first();

                // Reemplazos que este instructor está realizando
                $reemplazosHechos = collect();
                if ($contrato) {
                    $reemplazosHechos = AsignacionSesion::with('horario')
                        ->where('idContrato', $contrato->id)
                        ->where('tipoAsignacion', 'REEMPLAZO')
                        ->where(function ($q) use ($inicio, $fin) {
                            $q->whereBetween('fechaInicio', [$inicio, $fin])
                              ->orWhereBetween('fechaFin', [$inicio, $fin]);
                        })->get();
                }

                $horariosFilas = collect();

                if ($contrato) {
                    foreach ($contrato->horarioMateria as $h) {
                        $duracionSesion = round((strtotime($h->horaFinal) - strtotime($h->horaInicial)) / 3600, 2);
                        $desde = \Carbon\Carbon::parse($h->fechaInicial)->max($inicio);
                        $hasta = \Carbon\Carbon::parse($h->fechaFinal)->min($fin);
                        $idDiaInt = (int) $h->idDia;
                        $diaSemanaCarbon = $idDiaInt === 7 ? 0 : $idDiaInt;
                        $cantidadSesiones = 0;
                        $cursor = $desde->copy()->startOfDay();
                        $finalC = $hasta->copy()->startOfDay();

                        // Reemplazos que le hicieron a este horario
                        $reemplazosQueLeHicieron = AsignacionSesion::where('idHorarioMateria', $h->id)
                            ->where('tipoAsignacion', 'REEMPLAZO')
                            ->get();

                        while ($cursor->lte($finalC)) {
                            if ($cursor->dayOfWeek === $diaSemanaCarbon) {
                                $esReemplazo = $reemplazosQueLeHicieron->contains(function ($r) use ($cursor) {
                                    $inicioR = \Carbon\Carbon::parse($r->fechaInicio)->startOfDay();
                                    $finR = \Carbon\Carbon::parse($r->fechaFin)->startOfDay();
                                    return $cursor->between($inicioR, $finR);
                                });
                                if (!$esReemplazo) {
                                    $cantidadSesiones++;
                                }
                            }
                            $cursor->addDay();
                        }

                        if ($cantidadSesiones > 0) {
                            $horariosFilas->push([
                                'id' => $h->id,
                                'idContrato' => $h->idContrato,
                                'horaInicial' => $h->horaInicial,
                                'horaFinal' => $h->horaFinal,
                                'estado' => $h->estado,
                                'idDia' => $h->idDia,
                                'fechaInicial' => $h->fechaInicial,
                                'fechaFinal' => $h->fechaFinal,
                                'duracionSesion' => (float) $duracionSesion,
                                'cantidadSesiones' => (int) $cantidadSesiones,
                                'duracionHoras' => (float) round($duracionSesion * $cantidadSesiones, 2),
                            ]);
                        }
                    }
                }

                // Agregar reemplazos hechos por este instructor
                foreach ($reemplazosHechos as $r) {
                    $h = $r->horario;
                    if (!$h) continue;

                    $duracionSesion = round((strtotime($h->horaFinal) - strtotime($h->horaInicial)) / 3600, 2);
                    $desde = \Carbon\Carbon::parse($r->fechaInicio)->max($inicio);
                    $hasta = \Carbon\Carbon::parse($r->fechaFin)->min($fin);
                    $idDiaInt = (int) $h->idDia;
                    $diaSemanaCarbon = $idDiaInt === 7 ? 0 : $idDiaInt;
                    $cantidadSesiones = 0;
                    $cursor = $desde->copy()->startOfDay();
                    $finalC = $hasta->copy()->startOfDay();

                    while ($cursor->lte($finalC)) {
                        if ($cursor->dayOfWeek === $diaSemanaCarbon)
                            $cantidadSesiones++;
                        $cursor->addDay();
                    }

                    if ($cantidadSesiones > 0) {
                        $horariosFilas->push([
                            'id' => 'r' . $r->id,
                            'idContrato' => $contrato->id,
                            'horaInicial' => $h->horaInicial,
                            'horaFinal' => $h->horaFinal,
                            'estado' => 'REEMPLAZO',
                            'idDia' => $h->idDia,
                            'fechaInicial' => $r->fechaInicio,
                            'fechaFinal' => $r->fechaFin,
                            'duracionSesion' => (float) $duracionSesion,
                            'cantidadSesiones' => (int) $cantidadSesiones,
                            'duracionHoras' => (float) round($duracionSesion * $cantidadSesiones, 2),
                        ]);
                    }
                }

                $horarios = $horariosFilas;

                // Horas ejecutadas en el periodo (sesiones registradas para este contrato)
                $horasEjecutadas = 0;
                if ($contrato) {
                    $horasEjecutadas = round(
                        SesionMateria::join('horarioMateria', 'sesionMateria.idHorarioMateria', '=', 'horarioMateria.id')
                            ->where('sesionMateria.idContrato', $contrato->id) // Usar idContrato de sesionMateria
                            ->whereBetween('fechaSesion', [$inicio, $fin])
                            ->selectRaw('SUM((TIME_TO_SEC(horarioMateria.horaFinal) - TIME_TO_SEC(horarioMateria.horaInicial)) / 3600) as totalHoras')
                            ->value('totalHoras') ?? 0
                    );
                }

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
                            } elseif (
                                $detallesRmi->every(function ($d) {
                                    return $d->estado === 'ACEPTADO';
                                })
                            ) {
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
                    'idContrato' => $contrato?->id,
                    'idRmi' => $idRmi,
                    'roles' => $acu->getRoleNames(),
                    'horarios' => $horarios,
                    'estado' => $estadoRmi,
                    'totalHoras' => $contrato?->horasmes ?? 0,
                    'totalHorasFormato' => $horasEjecutadas,
                    'motivoRechazo' => $motivoRechazo,
                    'persona' => [
                        'identificacion' => $persona->identificacion,
                        'nombre1' => $persona->nombre1,
                        'nombre2' => $persona->nombre2,
                        'apellido1' => $persona->apellido1,
                        'apellido2' => $persona->apellido2,
                        'fechaNac' => $persona->fechaNac,
                        'direccion' => $persona->direccion,
                        'email' => $persona->email,
                        'celular' => $persona->celular,
                        'telefonoFijo' => $persona->telefonoFijo,
                        'perfil' => $persona->perfil,
                        'sexo' => $persona->sexo,
                        'rh' => $persona->rh,
                        'rutaFoto' => $persona->rutaFotoUrl,
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
            'periodo' => 'nullable|date_format:Y-m',
        ]);

        $query = \App\Models\HorarioMateria::with([
            'ficha.asignacion.programa',
            'gradoMateria.materia.padre',
            'detallesRmi'
        ])
            ->where('idContrato', $validated['idContrato'])
            ->where('estado', '!=', 'PENDIENTE');

        if (!empty($validated['periodo'])) {
            $inicio = \Carbon\Carbon::createFromFormat('Y-m', $validated['periodo'])->startOfMonth();
            $fin = \Carbon\Carbon::createFromFormat('Y-m', $validated['periodo'])->endOfMonth();
        } else {
            $inicio = \Carbon\Carbon::now()->startOfMonth();
            $fin = \Carbon\Carbon::now()->endOfMonth();
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
            $ficha = $horariosGrupo->first()->ficha;
            $programa = $ficha?->asignacion?->programa;

            // Paso 1: mapear cada horario con todos sus datos y su idGradoMateria
            $horariosConDatos = $horariosGrupo->map(function ($h) use ($validated) {
                $rap = $h->gradoMateria?->materia;
                $competencia = $rap?->padre;
                $duracionSesion = round((strtotime($h->horaFinal) - strtotime($h->horaInicial)) / 3600, 2);

                // Determinar el rango a calcular
                if (!empty($validated['periodo'])) {
                    $rangoInicio = \Carbon\Carbon::createFromFormat('Y-m', $validated['periodo'])->startOfMonth();
                    $rangoFin = \Carbon\Carbon::createFromFormat('Y-m', $validated['periodo'])->endOfMonth();
                    $desde = \Carbon\Carbon::parse($h->fechaInicial)->max($rangoInicio);
                    $hasta = \Carbon\Carbon::parse($h->fechaFinal)->min($rangoFin);
                } else {
                    $desde = \Carbon\Carbon::parse($h->fechaInicial)->max(\Carbon\Carbon::now()->startOfMonth());
                    $hasta = \Carbon\Carbon::parse($h->fechaFinal)->min(\Carbon\Carbon::now()->endOfMonth());
                }

                // idDia: 1=Lunes...6=Sabado, 7=Domingo → Carbon: 0=Domingo, 1=Lunes...6=Sabado
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

                return [
                    'idGradoMateria' => $h->idGradoMateria,
                    'competencia' => $competencia?->nombreMateria,
                    'resultadoAprendizaje' => $rap?->nombreMateria,
                    'idHorario' => $h->id,
                    'horaInicial' => $h->horaInicial,
                    'horaFinal' => $h->horaFinal,
                    'fechaInicial' => $h->fechaInicial,
                    'fechaFinal' => $h->fechaFinal,
                    'duracionSesion' => $duracionSesion,
                    'cantidadSesiones' => $cantidadSesiones,
                    'duracionHoras' => round($duracionSesion * $cantidadSesiones, 2),
                    'idDia' => $h->idDia,
                    'esCompartida' => \App\Models\AsignacionSesion::where('tipoAsignacion', 'HORARIO COMPARTIDO')
                        ->whereHas('horario', function($q) use ($h) {
                            $q->where('idFicha', $h->idFicha)
                              ->where('idGradoMateria', $h->idGradoMateria);
                        })->exists(),
                    'compartidoCon' => \App\Models\HorarioMateria::with('contrato.persona')
                        ->where('idFicha', $h->idFicha)
                        ->where('idGradoMateria', $h->idGradoMateria)
                        ->whereNotNull('idContrato')
                        ->where('idContrato', '!=', $h->idContrato)
                        ->get()
                        ->map(function($otro) {
                            if ($otro->contrato && $otro->contrato->persona) {
                                $p = $otro->contrato->persona;
                                return trim($p->nombre1 . ' ' . $p->apellido1);
                            }
                            return null;
                        })
                        ->filter()
                        ->unique()
                        ->values()
                        ->toArray()
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
                        'idGradoMateria' => $primero['idGradoMateria'],
                        'competencia' => $primero['competencia'],
                        'resultadoAprendizaje' => $primero['resultadoAprendizaje'],
                        'estadoAsociacion' => $detallesRmi->first()?->estadoAsociacion,
                        'esCompartida' => $primero['esCompartida'] ?? false,
                        'compartidoCon' => $primero['compartidoCon'] ?? [],
                        'horarios' => $horariosGM->map(function ($item) use ($detallesRmi) {
                            return [
                                'idHorario' => $item['idHorario'],
                                'horaInicial' => $item['horaInicial'],
                                'horaFinal' => $item['horaFinal'],
                                'fechaInicial' => $item['fechaInicial'],
                                'fechaFinal' => $item['fechaFinal'],
                                'duracionSesion' => $item['duracionSesion'],
                                'cantidadSesiones' => $item['cantidadSesiones'],
                                'duracionHoras' => $item['duracionHoras'],
                                'idDia' => $item['idDia']
                            ];
                        })->values(),
                    ];
                })->values();

            return [
                'idFicha' => $ficha?->id,
                'codigoFicha' => $ficha?->codigo,
                'programaFormacion' => $programa?->nombrePrograma,
                'codigoPrograma' => $programa?->codigoPrograma,
                'resultados' => $resultados,
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
                ->where('estado', '!=', 'PENDIENTE')
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

            $asunto = 'Aprobación de RMI - Periodo ' . $periodo;
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
            Log::error('Error al aceptar RMI: ' . $e->getMessage(), [
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
                ->where('estado', '!=', 'PENDIENTE')
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

            $nombre = $activation->user->persona->nombre1;
            $asunto = 'Rechazo de RMI - Periodo ' . $periodo;
            $mensaje = "Estimado(a) $nombre,\n\n"
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
            Log::error('Error al rechazar RMI: ' . $e->getMessage(), [
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
            $inicio = \Carbon\Carbon::createFromFormat('Y-m', $periodo)->startOfMonth();
            $fin = \Carbon\Carbon::createFromFormat('Y-m', $periodo)->endOfMonth();

            // Obtener horarios del contrato en el periodo
            $horarios = \App\Models\HorarioMateria::where('idContrato', $contrato->id)
                ->where('estado', '!=', 'PENDIENTE')
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
                    'estado' => 'PENDIENTE',
                    'observacion' => null,
                ]);

            // Revertir estado del RMI a PENDIENTE
            $rmi->update(['estado' => 'PENDIENTE']);

            $user = KeyUtil::user();
            $this->enviarNotificacionRmi($user->id, $activation->user->id, $periodo, null, 'PENDIENTE');

            DB::commit();

            return response()->json([
                'message' => 'RMI revertido a pendiente con éxito',
                'estado' => 'PENDIENTE',
                'detalles_actualizados' => $detallesActualizados,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error de validación', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al revertir RMI: ' . $e->getMessage(), [
                'idActivation' => $idActivation,
                'periodo' => $request->input('periodo'),
                'trace' => $e->getTraceAsString(),
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
            $user = auth()->user();
            $persona = $user?->persona;

            if (!$persona) {
                return response()->json([
                    'fichas' => [],
                    'message' => 'Sin persona asociada'
                ], 200);
            }

            $contrato = $persona->contracts()->latest()->first();

            if (!$contrato) {
                return response()->json([
                    'fichas' => [],
                    'message' => 'Sin contrato activo'
                ], 200);
            }

            $inicio = \Carbon\Carbon::now()->startOfMonth();
            $fin = \Carbon\Carbon::now()->endOfMonth();

            $horarios = \App\Models\HorarioMateria::with([
                'ficha.asignacion.programa',
                'gradoMateria.materia.padre',
            ])
                ->where('idContrato', $contrato->id)
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

            $fichas = $horarios->groupBy('idFicha')->map(function ($grupo) use ($inicio, $fin) {
                $ficha = $grupo->first()->ficha;
                $programa = $ficha?->asignacion?->programa;

                $resultados = $grupo->map(function ($h) use ($inicio, $fin) {
                    $rap = $h->gradoMateria?->materia;
                    $competencia = $rap?->padre;

                    $horaInicial = \Carbon\Carbon::parse($h->horaInicial);
                    $horaFinal = \Carbon\Carbon::parse($h->horaFinal);

                    $durSesion = round(
                        max(0, $horaInicial->diffInMinutes($horaFinal)) / 60,
                        2
                    );

                    $fechaInicialHorario = \Carbon\Carbon::parse($h->fechaInicial)->startOfDay();
                    $fechaFinalHorario = \Carbon\Carbon::parse($h->fechaFinal)->endOfDay();

                    $desde = $fechaInicialHorario->greaterThan($inicio)
                        ? $fechaInicialHorario->copy()
                        : $inicio->copy();

                    $hasta = $fechaFinalHorario->lessThan($fin)
                        ? $fechaFinalHorario->copy()
                        : $fin->copy();
                    $idDia = (int) $h->idDia;
                    $diaSemanaCarbon = $idDia === 7 ? 0 : $idDia;
                    $cantSesiones = 0;
                    if ($idDia >= 1 && $idDia <= 7 && $desde->lte($hasta)) {
                        $cursor = $desde->copy()->startOfDay();
                        $limite = $hasta->copy()->endOfDay();

                        while ($cursor->lte($limite)) {
                            if ((int) $cursor->dayOfWeek === $diaSemanaCarbon) {
                                $cantSesiones++;
                            }
                            $cursor->addDay();
                        }
                    }

                    return [
                        'idHorario' => $h->id,
                        'competencia' => $competencia?->nombreMateria,
                        'resultadoAprendizaje' => $rap?->nombreMateria,
                        'horaInicial' => $h->horaInicial,
                        'horaFinal' => $h->horaFinal,
                        'fechaInicial' => $h->fechaInicial,
                        'fechaFinal' => $h->fechaFinal,
                        'idDia' => $idDia,
                        'duracionSesion' => $durSesion,
                        'cantidadSesiones' => $cantSesiones,
                        'duracionHoras' => round($durSesion * $cantSesiones, 2),
                    ];
                })->values();
                return [
                    'idFicha' => $ficha?->id,
                    'codigoFicha' => $ficha?->codigo,
                    'programaFormacion' => $programa?->nombrePrograma,
                    'codigoPrograma' => $programa?->codigoPrograma,
                    'resultados' => $resultados,
                ];
            })->values();
            return response()->json([
                'fichas' => $fichas
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 500);
        }
    }

    //Dejo preparado para agregarlos endpoints del contrato para el instructor...
    public function getContratoByInstructor(Request $request)
    {
        $user = auth()->user();
        $persona = $user?->persona;

        $contrato = Contract::where('idpersona', $persona->id)->where('idEstado', 1)->with('centroFormacion', 'persona.ciudadExpedicionRel.departamento')->get();

        return response()->json(['contrato' => $contrato]);
    }
    public function updateSupervisor(Request $request, int $id)
    {
        $request->validate([
            'supervisorContrato' => 'nullable|string|max:255',
            'cargoSupervisor' => 'nullable|string|max:255',
            'objetoContrato' => 'nullable|string|max:1000',
            'formaDePago' => 'nullable|string|in:COMISIONES,SALARIO INTEGRAL,NORMAL',
            'ciudadExpedicionId' => 'nullable|integer|exists:ciudad,id',
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
        $year = $request->year;
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
            ->with(['horarioMateria.detallesRmi.rmi', 'gcs'])
            ->get();

        if ($contracts->isEmpty()) {
            return response()->json(['message' => 'No hay contratos válidos para el año especificado.'], 404);
        }

        $data = $contracts->map(function ($contract) use ($year) {
            $periodos = [];

            foreach ($contract->horarioMateria as $horario) {
                foreach ($horario->detallesRmi as $detalle) {
                    $rmi = $detalle->rmi;
                    $period = $rmi->periodo;

                    if (!str_starts_with($period, $year))
                        continue;

                    if (!isset($periodos[$period])) {
                        // Calcular horas asignadas en ese periodo (mismo cálculo que getFichasByContrato)
                        $inicio = Carbon::createFromFormat('Y-m', $period)->startOfMonth();
                        $fin = Carbon::createFromFormat('Y-m', $period)->endOfMonth();

                        $horasAsignadas = 0;

                        foreach ($contract->horarioMateria as $h) {
                            $desde = Carbon::parse($h->fechaInicial)->startOfDay()->max($inicio);
                            $hasta = Carbon::parse($h->fechaFinal ?? Carbon::now())->endOfDay()->min($fin);

                            if ($desde->gt($hasta))
                                continue;

                            $duracionSesion = (strtotime($h->horaFinal) - strtotime($h->horaInicial)) / 3600;
                            $idDiaInt = (int) $h->idDia;
                            $diaSemanaCarbon = $idDiaInt === 7 ? 0 : $idDiaInt;
                            $cantSesiones = 0;
                            $cursor = $desde->copy();

                            while ($cursor->lte($hasta)) {
                                if ((int) $cursor->dayOfWeek === $diaSemanaCarbon)
                                    $cantSesiones++;
                                $cursor->addDay();
                            }

                            $horasAsignadas += round($duracionSesion * $cantSesiones, 2);
                        }

                        $detallesDelPeriodo = $contract->horarioMateria->flatMap(function ($h) use ($rmi) {
                            return $h->detallesRmi->where('idRmi', $rmi->id);
                        });

                        $estadoConsolidado = 'PENDIENTE';
                        $estadoInformeConsolidado = 'PENDIENTE';
                        if ($detallesDelPeriodo->isNotEmpty()) {
                            if ($detallesDelPeriodo->contains('estado', 'RECHAZADO')) {
                                $estadoConsolidado = 'RECHAZADO';
                            } elseif ($detallesDelPeriodo->every(fn($d) => $d->estado === 'ACEPTADO')) {
                                $estadoConsolidado = 'ACEPTADO';
                            }

                            if ($detallesDelPeriodo->every(fn($d) => $d->estadoInforme === 'ACEPTADO')) {
                                $estadoInformeConsolidado = 'ACEPTADO';
                            }
                        }

                        $periodos[$period] = [
                            'periodo' => $period,
                            'idRmi' => $rmi->id,
                            'estadoRmi' => $estadoConsolidado,
                            'estadoInforme' => $estadoInformeConsolidado,
                            'observacion' => $rmi->observacion,
                            'horasAsignadas' => round($horasAsignadas, 2),
                            'detalles' => [],
                            'gc' => $contract->gcs->firstWhere('idRmi', $rmi->id),
                        ];
                    }

                    $periodos[$period]['detalles'][] = [
                        'idDetalleRmi' => $detalle->id,
                        'estadoDetalle' => $detalle->estado,
                        'observacion' => $detalle->observacion,
                        'idHorarioMateria' => $horario->id,
                        'horaInicial' => $horario->horaInicial,
                        'horaFinal' => $horario->horaFinal,
                        'fechaInicial' => $horario->fechaInicial,
                        'fechaFinal' => $horario->fechaFinal,
                        'estadoHorario' => $horario->estado,
                        'archivoPago' => $detalle->archivoPago,
                        'archivoPagoUrl' => $detalle->archivoPagoUrl,
                        'urlInforme' => $detalle->urlInforme,
                        'urlInformeUrl' => $detalle->urlInformeUrl,
                        'estadoInforme' => $detalle->estadoInforme,
                        'numeroPlanilla' => $detalle->numeroPlanilla,
                    ];
                }
            }

            ksort($periodos);

            return [
                'idContrato' => $contract->id,
                'siif' => $contract->siif,
                'identificacion' => $contract->persona->identificacion,
                'fechaContratacion' => $contract->fechaContratacion,
                'fechaFinal' => $contract->fechaFinalContrato,
                'periodos' => array_values($periodos),
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
            'idsHorarioMateria' => 'required|array',
        ]);

        $idRmi = $request->idRmi;
        $idsHorario = $request->idsHorarioMateria;

        // 1. Obtener los archivos actuales de estos registros para su posible eliminación
        $viejosArchivos = DetalleRmi::where('idRmi', $idRmi)
            ->whereIn('idHorarioMateria', $idsHorario)
            ->pluck('archivoPago')
            ->filter()
            ->unique();

        // 2. Guardar el nuevo archivo
        $ruta = '/storage/' . $request->file('archivoPago')->store('pagos', 'public');

        // 3. Borrar los archivos antiguos si ya no se usan en otro lugar
        foreach ($viejosArchivos as $viejo) {
            // Verificar si hay algún DETALLERMI que aún use este archivo y NO esté en el grupo que estamos actualizando
            $estaEnUso = DetalleRmi::where('archivoPago', $viejo)
                ->where(function ($query) use ($idRmi, $idsHorario) {
                    $query->where('idRmi', '!=', $idRmi)
                        ->orWhereNotIn('idHorarioMateria', $idsHorario);
                })
                ->exists();

            if (!$estaEnUso) {
                // El prefijo /storage/ debe ser removido para usar Storage::disk('public')
                $pathRelativo = str_replace('/storage/', '', $viejo);
                if (Storage::disk('public')->exists($pathRelativo)) {
                    Storage::disk('public')->delete($pathRelativo);
                }
            }
        }

        // 4. Actualizar los detalleRmi con la nueva ruta
        DetalleRmi::where('idRmi', $idRmi)
            ->whereIn('idHorarioMateria', $idsHorario)
            ->update(['archivoPago' => $ruta]);

        return response()->json(['archivoPago' => $ruta]);
    }

    public function uploadInformeInstructor(Request $request)
    {
        $request->validate([
            'urlInforme' => 'required|file|mimes:pdf,jpg,jpeg,png',
            'idRmi' => 'required|integer',
            'idsHorarioMateria' => 'required|array',
        ]);

        $idRmi = $request->idRmi;
        $idsHorario = $request->idsHorarioMateria;

        // 1. Obtener los informes actuales para su eliminación
        $viejosInformes = DetalleRmi::where('idRmi', $idRmi)
            ->whereIn('idHorarioMateria', $idsHorario)
            ->pluck('urlInforme')
            ->filter()
            ->unique();

        // 2. Guardar el nuevo informe
        $ruta = '/storage/' . $request->file('urlInforme')->store('informes', 'public');

        // 3. Borrar los informes antiguos
        foreach ($viejosInformes as $viejo) {
            $estaEnUso = DetalleRmi::where('urlInforme', $viejo)
                ->where(function ($query) use ($idRmi, $idsHorario) {
                    $query->where('idRmi', '!=', $idRmi)
                        ->orWhereNotIn('idHorarioMateria', $idsHorario);
                })
                ->exists();

            if (!$estaEnUso) {
                $pathRelativo = str_replace('/storage/', '', $viejo);
                if (Storage::disk('public')->exists($pathRelativo)) {
                    Storage::disk('public')->delete($pathRelativo);
                }
            }
        }

        // 4. Actualizar los detalleRmi
        DetalleRmi::where('idRmi', $idRmi)
            ->whereIn('idHorarioMateria', $idsHorario)
            ->update([
                'urlInforme' => $ruta,
            ]);

        return response()->json(['urlInforme' => $ruta]);
    }

    public function updateNumeroPlanilla(Request $request)
    {
        $request->validate([
            'numeroPlanilla' => 'required|string|max:50',
            'idRmi' => 'required|integer',
            'idsHorarioMateria' => 'required|array',
        ]);

        DetalleRmi::where('idRmi', $request->idRmi)
            ->whereIn('idHorarioMateria', $request->idsHorarioMateria)
            ->update(['numeroPlanilla' => $request->numeroPlanilla]);

        return response()->json(['message' => 'Número de planilla actualizado correctamente']);
    }

    public function aceptarInforme(Request $request)
    {
        DB::beginTransaction();
        try {
            $validated = $request->validate([
                'idRmi' => 'required|integer',
                'idsHorarioMateria' => 'required|array',
                'email' => 'required|email'
            ]);

            // Obtener información del RMI
            $rmi = Rmi::findOrFail($validated['idRmi']);

            // Obtener el instructor a través del primer DetalleRmi
            $primerDetalle = DetalleRmi::where('idRmi', $validated['idRmi'])
                ->whereIn('idHorarioMateria', $validated['idsHorarioMateria'])
                ->first();

            if (!$primerDetalle) {
                DB::rollBack();
                return response()->json(['message' => 'No se encontraron detalles del RMI'], 404);
            }

            $horario = $primerDetalle->horarioMateria;
            $contrato = $horario->contrato;
            $instructor = $contrato->persona;

            // Actualizar el estado del informe
            DetalleRmi::where('idRmi', $validated['idRmi'])
                ->whereIn('idHorarioMateria', $validated['idsHorarioMateria'])
                ->update(['estadoInforme' => 'ACEPTADO']);

            // Obtener el usuario actual
            $user = KeyUtil::user();
            //remitente:
            $userRemitente = User::where('idpersona', $contrato->idpersona)->first();

            // Enviar notificación
            $this->enviarNotificacionInforme($user->id, $userRemitente->id, $rmi->periodo, null, 'APROBADO');

            DB::commit();

            // Enviar correo
            $email = $validated['email'];
            $nombre = $instructor->nombre1;
            $asunto = 'Informe Aceptado - Periodo ' . $rmi->periodo;
            $mensaje = "Estimado(a) $nombre,\n\n"
                . "Le informamos que su informe correspondiente al periodo {$rmi->periodo} ha sido ACEPTADO.\n\n"
                . "No se requieren acciones adicionales por su parte.\n\n"
                . "Si tiene alguna inquietud, puede comunicarse con el equipo administrativo.\n\n"
                . "Atentamente,\n"
                . "Equipo Administrativo\n"
                . "Sistema de Gestión Académica";

            \App\Jobs\SendBasicEmail::dispatch($email, $asunto, $mensaje);

            return response()->json(['message' => 'Informe aceptado correctamente']);
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error de validación', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al aceptar informe: ' . $e->getMessage(), [
                'idRmi' => $request->input('idRmi'),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['message' => 'Error al aceptar el informe', 'error' => $e->getMessage()], 500);
        }
    }

    public function revertirInforme(Request $request)
    {
        DB::beginTransaction();
        try {
            $validated = $request->validate([
                'idRmi' => 'required|integer',
                'idsHorarioMateria' => 'required|array',
                'email' => 'required|email'
            ]);

            // Obtener información del RMI
            $rmi = Rmi::findOrFail($validated['idRmi']);

            // Obtener el instructor a través del primer DetalleRmi
            $primerDetalle = DetalleRmi::where('idRmi', $validated['idRmi'])
                ->whereIn('idHorarioMateria', $validated['idsHorarioMateria'])
                ->first();

            if (!$primerDetalle) {
                DB::rollBack();
                return response()->json(['message' => 'No se encontraron detalles del RMI'], 404);
            }

            $horario = $primerDetalle->horarioMateria;
            $contrato = $horario->contrato;
            $instructor = $contrato->persona;

            // Revertir el estado del informe a PENDIENTE
            DetalleRmi::where('idRmi', $validated['idRmi'])
                ->whereIn('idHorarioMateria', $validated['idsHorarioMateria'])
                ->update([
                    'estadoInforme' => 'PENDIENTE'
                ]);

            // Obtener el usuario actual
            $user = KeyUtil::user();
            //remitente:
            $userRemitente = User::where('idpersona', $contrato->idpersona)->first();

            // Enviar notificación
            $this->enviarNotificacionInforme($user->id, $userRemitente->id, $rmi->periodo, null, 'DEVUELTO');

            DB::commit();

            // Enviar correo
            $email = $validated['email'];
            $nombre = $instructor->nombre1;
            $asunto = 'Informe Revertido a Pendiente - Periodo ' . $rmi->periodo;
            $mensaje = "Estimado(a) $nombre,\n\n"
                . "Le informamos que su informe correspondiente al periodo {$rmi->periodo} ha sido REVERTIDO a PENDIENTE.\n\n"
                . "Por favor revise la plataforma para más detalles y proceda con las correcciones o acciones necesarias.\n\n"
                . "Atentamente,\n"
                . "Equipo Administrativo\n"
                . "Sistema de Gestión Académica";

            \App\Jobs\SendBasicEmail::dispatch($email, $asunto, $mensaje);

            return response()->json([
                'message' => 'Informe revertido a pendiente correctamente',
                'estado' => 'PENDIENTE'
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error de validación', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al revertir informe: ' . $e->getMessage(), [
                'idRmi' => $request->input('idRmi'),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['message' => 'Error al revertir el informe', 'error' => $e->getMessage()], 500);
        }
    }

    public function rechazarInforme(Request $request)
    {
        DB::beginTransaction();
        try {
            $validated = $request->validate([
                'idRmi' => 'required|integer',
                'idsHorarioMateria' => 'required|array',
                'email' => 'required|email',
                'motivo' => 'required|string'
            ]);

            // Obtener información del RMI
            $rmi = Rmi::findOrFail($validated['idRmi']);
            $motivo = $validated['motivo'];

            // Obtener el instructor a través del primer DetalleRmi
            $primerDetalle = DetalleRmi::where('idRmi', $validated['idRmi'])
                ->whereIn('idHorarioMateria', $validated['idsHorarioMateria'])
                ->first();

            if (!$primerDetalle) {
                DB::rollBack();
                return response()->json(['message' => 'No se encontraron detalles del RMI'], 404);
            }

            $horario = $primerDetalle->horarioMateria;
            $contrato = $horario->contrato;
            $instructor = $contrato->persona;

            // Actualizar el estado del informe
            DetalleRmi::where('idRmi', $validated['idRmi'])
                ->whereIn('idHorarioMateria', $validated['idsHorarioMateria'])
                ->update([
                    'estadoInforme' => 'PENDIENTE'
                ]);

            // Obtener el usuario actual
            $user = KeyUtil::user();
            //remitente:
            $userRemitente = User::where('idpersona', $contrato->idpersona)->first();

            // Enviar notificación
            $this->enviarNotificacionInforme($user->id, $userRemitente->id, $rmi->periodo, $motivo, 'RECHAZADO');

            DB::commit();

            // Enviar correo
            $email = $validated['email'];
            $nombre = $instructor->nombre1;
            $asunto = 'Informe Rechazado - Periodo ' . $rmi->periodo;
            $mensaje = "Estimado(a) $nombre,\n\n"
                . "Le informamos que su informe correspondiente al periodo {$rmi->periodo} ha sido RECHAZADO.\n\n"
                . "Por favor revise las observaciones y realice las correcciones necesarias.\n\n"
                . "Motivo:\n\n"
                . "* {$motivo}:.\n\n"
                . "Atentamente,\n"
                . "Equipo administrativo";

            \App\Jobs\SendBasicEmail::dispatch($email, $asunto, $mensaje);

            return response()->json(['message' => 'Informe rechazado correctamente']);
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error de validación', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al rechazar informe: ' . $e->getMessage(), [
                'idRmi' => $request->input('idRmi'),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['message' => 'Error al rechazar el informe', 'error' => $e->getMessage()], 500);
        }
    }
    protected function enviarNotificacionInforme(
        $remitenteId,
        $receptorId,
        $periodo,
        $motivo = null,
        $accion = 'APROBADO'
    ) {
        switch ($accion) {
            case 'APROBADO':
                $asunto = 'Informe aprobado';
                $mensaje = "Su informe del periodo {$periodo} ha sido aprobado.";
                break;

            case 'RECHAZADO':
                $asunto = 'Informe rechazado';
                $mensaje = "Su informe del periodo {$periodo} ha sido rechazado."
                    . ($motivo ? " Motivo: {$motivo}" : "");
                break;

            case 'DEVUELTO':
                $asunto = 'Informe devuelto a pendiente';
                $mensaje = "Su informe del periodo {$periodo} ha sido devuelto a estado PENDIENTE."
                    . " Por favor revise la plataforma y realice los ajustes necesarios.";
                break;

            default:
                $asunto = 'Actualización de informe';
                $mensaje = "El estado de su informe del periodo {$periodo} ha cambiado.";
                break;
        }

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

    public function getAllRmiDetailsForAdmin(Request $request)
    {
        try {
            // Obtener todos los RMI con sus detalles y GCs
            $rmiRecords = Rmi::with([
                'detalles.horarioMateria.gradoMateria.materia',
                'detalles.horarioMateria.contrato.persona',
                'gcs'
            ])->orderBy('periodo', 'desc')->get();

            $result = [];

            foreach ($rmiRecords as $rmi) {
                // Agrupar detalles por contrato para separar instructores en el mismo periodo
                $contratosGrouped = [];

                foreach ($rmi->detalles as $detalle) {
                    $horario = $detalle->horarioMateria;
                    if (!$horario)
                        continue;

                    $contrato = $horario->contrato;
                    if (!$contrato)
                        continue;

                    $idContrato = $contrato->id;
                    $persona = $contrato->persona;

                    if (!isset($contratosGrouped[$idContrato])) {
                        $contratosGrouped[$idContrato] = [
                            'idRmi' => $rmi->id,
                            'periodo' => $rmi->periodo,
                            'estadoRmi' => $rmi->estado,
                            'instructorNombre' => $persona ? ($persona->nombre1 . ' ' . $persona->apellido1) : 'Sin nombre',
                            'instructorId' => $persona ? $persona->id : null,
                            'identificacion' => $persona ? $persona->identificacion : 'S/N',
                            'emailInstructor' => $persona ? $persona->email : 'S/E',
                            'idContrato' => $idContrato,
                            'detalles' => [],
                            'gc' => null // Se llenará después
                        ];
                    }

                    $materia = $horario->gradoMateria->materia ?? null;

                    $contratosGrouped[$idContrato]['detalles'][] = [
                        'idDetalleRmi' => $detalle->id,
                        'idRmi' => $rmi->id,
                        'idHorarioMateria' => $horario->id,
                        'estadoInforme' => $detalle->estadoInforme,
                        'urlInforme' => $detalle->urlInformeUrl,
                        'numeroPlanilla' => $detalle->numeroPlanilla,
                        'horaInicial' => $horario->horaInicial,
                        'horaFinal' => $horario->horaFinal,
                        'fechaInicial' => $horario->fechaInicial,
                        'materia' => $materia ? ($materia->nombreMateria ?? $materia->nombre ?? 'Sin nombre') : 'Sin materia'
                    ];
                }

                // Asignar el GC correspondiente a cada contrato y agregarlo al resultado
                foreach ($contratosGrouped as $idContrato => $data) {
                    $data['gc'] = $rmi->gcs->firstWhere('idContrato', $idContrato);
                    $result[] = $data;
                }
            }

            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('Error al obtener RMI para admin: ' . $e->getMessage(), [
                'exception' => $e,
            ]);
            return response()->json(['message' => 'Error al obtener los datos', 'error' => $e->getMessage()], 500);
        }
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
        $idRmi = $request->idRmi;
        $nPlanilla = $request->nPlanilla;

        $rmi = Rmi::findOrFail($idRmi);
        $contrato = Contract::with([
            'persona',
            'persona.ciudadExpedicionRel',
            'persona.ciudadExpedicionRel.departamento',
            'centroFormacion'
        ])->findOrFail($idContrato);

        $actividades = ActividadInstructor::where('idRmi', $idRmi)->where('idContrato', $idContrato)->get();
        $comisiones = ComisionInstructor::where('idRmi', $idRmi)
            ->where('idContrato', $idContrato)
            ->get();


        $actividadesContrato = ActividadContrato::where('idContrato', $idContrato)->orderBy('created_at', 'asc')->get();

        // ── HORARIOS DEL PERIODO ──────────────────────────────────────────────
        $inicio = \Carbon\Carbon::createFromFormat('Y-m', $rmi->periodo)->startOfMonth();
        $fin = \Carbon\Carbon::createFromFormat('Y-m', $rmi->periodo)->endOfMonth();

        $diasSemana = [1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo'];

        // 1. Obtener horarios originales
        $horariosOriginales = \App\Models\HorarioMateria::with(['ficha.asignacion.programa'])
            ->where('idContrato', $idContrato)
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

        // 2. Obtener reemplazos realizados por este instructor
        $reemplazosHechos = \App\Models\AsignacionSesion::with(['horario.ficha.asignacion.programa', 'horario.gradoMateria.materia.padre'])
            ->where('idContrato', $idContrato)
            ->where('tipoAsignacion', 'REEMPLAZO')
            ->where(function ($q) use ($inicio, $fin) {
                $q->whereBetween('fechaInicio', [$inicio, $fin])
                    ->orWhereBetween('fechaFin', [$inicio, $fin])
                    ->orWhere(function ($q2) use ($inicio, $fin) {
                        $q2->where('fechaInicio', '<=', $inicio)
                            ->where('fechaFin', '>=', $fin);
                    });
            })
            ->get();

        // 3. Procesar horarios originales (restando cuando alguien más lo reemplaza)
        $filas = collect();
        foreach ($horariosOriginales as $h) {
            $desde = \Carbon\Carbon::parse($h->fechaInicial)->max($inicio);
            $hasta = \Carbon\Carbon::parse($h->fechaFinal)->min($fin);
                    $diaSemanaCarbon = (int) $h->idDia === 7 ? 0 : (int) $h->idDia;
            $cantSesiones = 0;
            $cursor = $desde->copy()->startOfDay();
            $finalC = $hasta->copy()->startOfDay();

            // Reemplazos que le hicieron a este horario
            $reemplazos = \App\Models\AsignacionSesion::where('idHorarioMateria', $h->id)
                ->where('tipoAsignacion', 'REEMPLAZO')
                ->get();

            while ($cursor->lte($finalC)) {
                if ((int) $cursor->dayOfWeek === $diaSemanaCarbon) {
                    $esReemplazo = $reemplazos->contains(function ($r) use ($cursor) {
                        $inicioR = \Carbon\Carbon::parse($r->fechaInicio)->startOfDay();
                        $finR = \Carbon\Carbon::parse($r->fechaFin)->startOfDay();
                        return $cursor->between($inicioR, $finR);
                    });
                    if (!$esReemplazo) {
                        $cantSesiones++;
                    }
                }
                $cursor->addDay();
            }

            if ($cantSesiones > 0) {
                $duracionSesion = round((strtotime($h->horaFinal) - strtotime($h->horaInicial)) / 3600, 2);
                $filas->push([
                    'idFicha' => $h->idFicha,
                    'ficha' => $h->ficha,
                    'dia' => $diasSemana[$h->idDia] ?? $h->idDia,
                    'horaInicial' => $h->horaInicial,
                    'horaFinal' => $h->horaFinal,
                    'cantSesiones' => $cantSesiones,
                    'horasTotales' => round($duracionSesion * $cantSesiones, 2),
                    'horarioMateria' => $h
                ]);
            }
        }

        // 4. Procesar reemplazos hechos (sumando horas)
        foreach ($reemplazosHechos as $r) {
            $h = $r->horario;
            if (!$h) continue;

            $desde = \Carbon\Carbon::parse($r->fechaInicio)->max($inicio);
            $hasta = \Carbon\Carbon::parse($r->fechaFin)->min($fin);
            $idDiaInt = (int) $h->idDia;
            $diaSemanaCarbon = $idDiaInt === 7 ? 0 : $idDiaInt;
            $cantSesiones = 0;
            $cursor = $desde->copy()->startOfDay();
            $finalC = $hasta->copy()->startOfDay();

            while ($cursor->lte($finalC)) {
                if ($cursor->dayOfWeek === $diaSemanaCarbon) {
                    $cantSesiones++;
                }
                $cursor->addDay();
            }

            if ($cantSesiones > 0) {
                $duracionSesion = round((strtotime($h->horaFinal) - strtotime($h->horaInicial)) / 3600, 2);
                $filas->push([
                    'idFicha' => $h->idFicha,
                    'ficha' => $h->ficha,
                    'dia' => ($diasSemana[$h->idDia] ?? $h->idDia) . ' (Reemplazo)',
                    'horaInicial' => $h->horaInicial,
                    'horaFinal' => $h->horaFinal,
                    'cantSesiones' => $cantSesiones,
                    'horasTotales' => round($duracionSesion * $cantSesiones, 2),
                    'horarioMateria' => $h
                ]);
            }
        }

        $horariosPorFicha = $filas->groupBy('idFicha')->map(function ($grupo) {
            $primera = $grupo->first();
            $ficha = $primera['ficha'];
            $programa = $ficha?->asignacion?->programa;

            return [
                'idFicha' => $primera['idFicha'],
                'codigoFicha' => $ficha?->codigo,
                'programaFormacion' => $programa?->nombrePrograma,
                'filas' => $grupo->map(function ($f) {
                    unset($f['idFicha'], $f['ficha'], $f['horarioMateria']);
                    return $f;
                })->values(),
                'totalHorasFicha' => $grupo->sum('horasTotales'),
            ];
        })->values();

        $horariosMaterias = $filas->pluck('horarioMateria')->unique('id')->values();
        // ─────────────────────────────────────────────────────────────────────

        // ── MATERIAS / RAPs POR FICHA CON FASE DE PROYECTO ───────────────────────────



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
                $ficha = $horariosGrupo->first()->ficha;
                $programa = $ficha?->asignacion?->programa;

                $materias = $horariosGrupo
                    ->map(function ($h) use ($fasesPorMateria) {
                        $rap = $h->gradoMateria?->materia;
                        $competencia = $rap?->padre;
                        $idMateria = $rap?->id;

                        if (!$idMateria)
                            return null;

                        // Buscar la fase asociada a este RAP
                        $fprRap = $fasesPorMateria->get($idMateria);
                        $fase = $fprRap?->fase;

                        return [
                            'idMateria' => $idMateria,
                            'resultadoAprendizaje' => $rap?->nombreMateria,
                            'competencia' => $competencia?->nombreMateria,
                            'faseProyecto' => $fase?->descripcionFase,
                            'proyectoFormativo' => $fase?->proyectoFormativo?->nombreProyecto,
                            'actividades' => $fase?->actividades
                                ?->map(fn($a) => [
                                    'id' => $a->id,
                                    'descripcionActividad' => $a->descripcionActividad,
                                ])->values()->toArray() ?? [],
                        ];
                    })
                    ->filter()
                    ->unique('idMateria')
                    ->values();

                return [
                    'idFicha' => $ficha?->id,
                    'codigoFicha' => $ficha?->codigo,
                    'programaFormacion' => $programa?->nombrePrograma,
                    'materias' => $materias,
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
                        'idMatricula' => $matricula->id,
                        'identificacion' => $person?->identificacion,
                        'nombreCompleto' => trim(implode(' ', array_filter([
                            $person?->nombre1,
                            $person?->nombre2,
                            $person?->apellido1,
                            $person?->apellido2,
                        ]))),
                        'estado' => $matricula->estado,
                        'observacion' => $matricula->observacion,
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
    public function getInformeByCoordinadorRmi(Request $request)
    {
        $idContrato = $request->idContrato;
        $idRmi = $request->idRmi;
        $nPlanilla = $request->nPlanilla;

        // Obtener el usuario actual "Coordinador"
        $user = KeyUtil::user();
        $coordinador = Person::where('id', $user->idpersona)->first();

        $rmi = Rmi::findOrFail($idRmi);
        $contrato = Contract::with([
            'persona',
            'persona.ciudadExpedicionRel',
            'persona.ciudadExpedicionRel.departamento',
            'centroFormacion'
        ])->findOrFail($idContrato);

        $actividades = ActividadInstructor::where('idRmi', $idRmi)->where('idContrato', $idContrato)->get();
        $comisiones = ComisionInstructor::where('idRmi', $idRmi)
            ->where('idContrato', $idContrato)
            ->get();


        $actividadesContrato = ActividadContrato::where('idContrato', $idContrato)->orderBy('created_at', 'asc')->get();

        // ── HORARIOS DEL PERIODO ──────────────────────────────────────────────
        $inicio = \Carbon\Carbon::createFromFormat('Y-m', $rmi->periodo)->startOfMonth();
        $fin = \Carbon\Carbon::createFromFormat('Y-m', $rmi->periodo)->endOfMonth();

        $diasSemana = [1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo'];

        // 1. Obtener horarios originales
        $horariosOriginales = \App\Models\HorarioMateria::with(['ficha.asignacion.programa'])
            ->where('idContrato', $idContrato)
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

        // 2. Obtener reemplazos realizados por este instructor
        $reemplazosHechos = \App\Models\AsignacionSesion::with(['horario.ficha.asignacion.programa', 'horario.gradoMateria.materia.padre'])
            ->where('idContrato', $idContrato)
            ->where('tipoAsignacion', 'REEMPLAZO')
            ->where(function ($q) use ($inicio, $fin) {
                $q->whereBetween('fechaInicio', [$inicio, $fin])
                    ->orWhereBetween('fechaFin', [$inicio, $fin])
                    ->orWhere(function ($q2) use ($inicio, $fin) {
                        $q2->where('fechaInicio', '<=', $inicio)
                            ->where('fechaFin', '>=', $fin);
                    });
            })
            ->get();

        // 3. Procesar horarios originales (restando cuando alguien más lo reemplaza)
        $filas = collect();
        foreach ($horariosOriginales as $h) {
            $desde = \Carbon\Carbon::parse($h->fechaInicial)->max($inicio);
            $hasta = \Carbon\Carbon::parse($h->fechaFinal)->min($fin);
            $idDiaInt = (int) $h->idDia;
            $diaSemanaCarbon = $idDiaInt === 7 ? 0 : $idDiaInt;
            $cantSesiones = 0;
            $cursor = $desde->copy()->startOfDay();
            $finalC = $hasta->copy()->startOfDay();

            // Reemplazos que le hicieron a este horario
            $reemplazos = \App\Models\AsignacionSesion::where('idHorarioMateria', $h->id)
                ->where('tipoAsignacion', 'REEMPLAZO')
                ->get();

            while ($cursor->lte($finalC)) {
                if ($cursor->dayOfWeek === $diaSemanaCarbon) {
                    $esReemplazo = $reemplazos->contains(function ($r) use ($cursor) {
                        $inicioR = \Carbon\Carbon::parse($r->fechaInicio)->startOfDay();
                        $finR = \Carbon\Carbon::parse($r->fechaFin)->startOfDay();
                        return $cursor->between($inicioR, $finR);
                    });
                    if (!$esReemplazo) {
                        $cantSesiones++;
                    }
                }
                $cursor->addDay();
            }

            if ($cantSesiones > 0) {
                $duracionSesion = round((strtotime($h->horaFinal) - strtotime($h->horaInicial)) / 3600, 2);
                $filas->push([
                    'idFicha' => $h->idFicha,
                    'ficha' => $h->ficha,
                    'dia' => $diasSemana[$h->idDia] ?? $h->idDia,
                    'horaInicial' => $h->horaInicial,
                    'horaFinal' => $h->horaFinal,
                    'cantSesiones' => $cantSesiones,
                    'horasTotales' => round($duracionSesion * $cantSesiones, 2),
                    'horarioMateria' => $h
                ]);
            }
        }

        // 4. Procesar reemplazos hechos (sumando horas)
        foreach ($reemplazosHechos as $r) {
            $h = $r->horario;
            if (!$h) continue;

            $desde = \Carbon\Carbon::parse($r->fechaInicio)->max($inicio);
            $hasta = \Carbon\Carbon::parse($r->fechaFin)->min($fin);
            $idDiaInt = (int) $h->idDia;
            $diaSemanaCarbon = $idDiaInt === 7 ? 0 : $idDiaInt;
            $cantSesiones = 0;
            $cursor = $desde->copy()->startOfDay();
            $finalC = $hasta->copy()->startOfDay();

            while ($cursor->lte($finalC)) {
                if ($cursor->dayOfWeek === $diaSemanaCarbon) {
                    $cantSesiones++;
                }
                $cursor->addDay();
            }

            if ($cantSesiones > 0) {
                $duracionSesion = round((strtotime($h->horaFinal) - strtotime($h->horaInicial)) / 3600, 2);
                $filas->push([
                    'idFicha' => $h->idFicha,
                    'ficha' => $h->ficha,
                    'dia' => ($diasSemana[$h->idDia] ?? $h->idDia) . ' (Reemplazo)',
                    'horaInicial' => $h->horaInicial,
                    'horaFinal' => $h->horaFinal,
                    'cantSesiones' => $cantSesiones,
                    'horasTotales' => round($duracionSesion * $cantSesiones, 2),
                    'horarioMateria' => $h
                ]);
            }
        }

        $horariosPorFicha = $filas->groupBy('idFicha')->map(function ($grupo) {
            $primera = $grupo->first();
            $ficha = $primera['ficha'];
            $programa = $ficha?->asignacion?->programa;

            return [
                'idFicha' => $primera['idFicha'],
                'codigoFicha' => $ficha?->codigo,
                'programaFormacion' => $programa?->nombrePrograma,
                'filas' => $grupo->map(function ($f) {
                    unset($f['idFicha'], $f['ficha'], $f['horarioMateria']);
                    return $f;
                })->values(),
                'totalHorasFicha' => $grupo->sum('horasTotales'),
            ];
        })->values();

        $horariosMaterias = $filas->pluck('horarioMateria')->unique('id')->values();

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
                $ficha = $horariosGrupo->first()->ficha;
                $programa = $ficha?->asignacion?->programa;

                $materias = $horariosGrupo
                    ->map(function ($h) use ($fasesPorMateria) {
                        $rap = $h->gradoMateria?->materia;
                        $competencia = $rap?->padre;
                        $idMateria = $rap?->id;

                        if (!$idMateria)
                            return null;

                        // Buscar la fase asociada a este RAP
                        $fprRap = $fasesPorMateria->get($idMateria);
                        $fase = $fprRap?->fase;

                        return [
                            'idMateria' => $idMateria,
                            'resultadoAprendizaje' => $rap?->nombreMateria,
                            'competencia' => $competencia?->nombreMateria,
                            'faseProyecto' => $fase?->descripcionFase,
                            'proyectoFormativo' => $fase?->proyectoFormativo?->nombreProyecto,
                            'actividades' => $fase?->actividades
                                ?->map(fn($a) => [
                                    'id' => $a->id,
                                    'descripcionActividad' => $a->descripcionActividad,
                                ])->values()->toArray() ?? [],
                        ];
                    })
                    ->filter()
                    ->unique('idMateria')
                    ->values();

                return [
                    'idFicha' => $ficha?->id,
                    'codigoFicha' => $ficha?->codigo,
                    'programaFormacion' => $programa?->nombrePrograma,
                    'materias' => $materias,
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
                        'idMatricula' => $matricula->id,
                        'identificacion' => $person?->identificacion,
                        'nombreCompleto' => trim(implode(' ', array_filter([
                            $person?->nombre1,
                            $person?->nombre2,
                            $person?->apellido1,
                            $person?->apellido2,
                        ]))),
                        'estado' => $matricula->estado,
                        'observacion' => $matricula->observacion,
                    ];
                })->values();
            });

        // ─────────────────────────────────────────────────────────────────────────────

        $pdf = Pdf::loadView('pdf.informeCoordinador', compact(
            'rmi',
            'contrato',
            'actividades',
            'comisiones',
            'nPlanilla',
            'coordinador',
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
