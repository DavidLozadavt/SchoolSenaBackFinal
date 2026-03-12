<?php

namespace App\Http\Controllers;

use App\Models\ActivationCompanyUser;
use App\Models\DetalleRmi;
use App\Models\Rmi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InstructoresController extends Controller
{
    public function getInstructors(Request $request)
    {
        $validated = $request->validate([
            'idCentroFormacion' => 'required|integer|exists:centroFormacion,id',
            'periodo'           => 'nullable|date_format:Y-m',
        ]);

        $instructors = ActivationCompanyUser::with([
            'user.persona.contracts' => function ($q) use ($validated) {
                $q->latest()->with([
                    'horarioMateria' => function ($h) use ($validated) {
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

                        // Con o sin periodo, siempre filtrar por rango de fechas
                        if (!empty($validated['periodo'])) {
                            $inicio = \Carbon\Carbon::createFromFormat('Y-m', $validated['periodo'])->startOfMonth();
                            $fin    = \Carbon\Carbon::createFromFormat('Y-m', $validated['periodo'])->endOfMonth();
                        } else {
                            $inicio = \Carbon\Carbon::now()->startOfMonth(); // ← mes actual como fallback
                            $fin    = \Carbon\Carbon::now()->endOfMonth();
                        }

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
            ->get()
            ->map(function ($acu) use ($validated) {

                $user     = $acu->user;
                $persona  = $user->persona;
                $contrato = $persona->contracts->first();

                $horarios = $contrato?->horarioMateria->map(function ($h) use ($validated) {
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

                    // Contar sesiones en el rango
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
                $periodoRmi = $validated['periodo'] ?? \Carbon\Carbon::now()->format('Y-m');
                $estadoRmi = 'PENDIENTE';
                $motivoRechazo = null;

                if ($contrato) {
                    $rmi = Rmi::where('periodo', $periodoRmi)->first();
                    if ($rmi) {
                        // Verificar si todos los detalles están en el mismo estado
                        $detallesRmi = DetalleRmi::where('idRmi', $rmi->id)
                            ->whereHas('horarioMateria', function ($q) use ($contrato) {
                                $q->where('idContrato', $contrato->id);
                            })
                            ->get();

                        if ($detallesRmi->isNotEmpty()) {
                            $estados = $detallesRmi->pluck('estado')->unique();
                            // Si todos tienen el mismo estado y no es PENDIENTE, usar ese estado
                            if ($estados->count() === 1 && $estados->first() !== 'PENDIENTE') {
                                $estadoRmi = $estados->first();
                                if ($estadoRmi === 'RECHAZADO') {
                                    $motivoRechazo = $rmi->observacion ?? $detallesRmi->first()->observacion;
                                }
                            } else {
                                // Si hay mezcla o todos son PENDIENTE, usar el estado del RMI principal
                                $estadoRmi = $rmi->estado;
                                if ($estadoRmi === 'RECHAZADO') {
                                    $motivoRechazo = $rmi->observacion;
                                }
                            }
                        } else {
                            // Si no hay detalles, usar el estado del RMI principal
                            $estadoRmi = $rmi->estado;
                            if ($estadoRmi === 'RECHAZADO') {
                                $motivoRechazo = $rmi->observacion;
                            }
                        }
                    }
                }

                return [
                    'idActivation' => $acu->id,
                    'emailUsuario' => $user->email,
                    'idContrato'   => $contrato?->id,
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

            return [
                'idFicha'           => $ficha?->id,
                'codigoFicha'       => $ficha?->codigo,
                'programaFormacion' => $programa?->nombrePrograma,
                'codigoPrograma'    => $programa?->codigoPrograma,
                'resultados'        => $horariosGrupo->map(function ($h) use ($validated) {
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

                    // Contar cuántas veces cae el día de la semana en el rango
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
                        'idHorario'            => $h->id,
                        'idGradoMateria'       => $h->idGradoMateria,
                        'competencia'          => $competencia?->nombreMateria,
                        'resultadoAprendizaje' => $rap?->nombreMateria,
                        'horaInicial'          => $h->horaInicial,
                        'horaFinal'            => $h->horaFinal,
                        'fechaInicial'         => $h->fechaInicial,
                        'fechaFinal'           => $h->fechaFinal,
                        'duracionSesion'       => $duracionSesion,
                        'cantidadSesiones'     => $cantidadSesiones,
                        'duracionHoras'        => round($duracionSesion * $cantidadSesiones, 2),
                        'idDia'                => $h->idDia,
                    ];
                })->values(),
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

            DB::commit();

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

            // Actualizar estado del RMI
            $rmi->update([
                'estado' => 'RECHAZADO',
                'observacion' => $validated['motivo']
            ]);

            DB::commit();

            return response()->json([
                'message' => 'RMI rechazado con éxito',
                'estado' => 'RECHAZADO',
                'detalles_actualizados' => $detallesActualizados
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
}
