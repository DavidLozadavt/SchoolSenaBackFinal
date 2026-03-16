<?php

namespace App\Http\Controllers;

use App\Mail\MailService;
use App\Models\ActivationCompanyUser;
use App\Models\DetalleRmi;
use App\Models\NotificacionSistema;
use App\Models\Rmi;
use App\Util\KeyUtil;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class InstructoresController extends Controller
{
    public function getInstructors(Request $request)
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

                if ($contrato) {
                    $rmi = Rmi::where('periodo', $periodoReq)->first();
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

            try {

                $html = "
                <div style='font-family: Arial, sans-serif; line-height:1.6; color:#333'>
                    <h2 style='color:#2c3e50;'>Notificación de RMI</h2>

                    <p>Estimado(a) <strong>{$nombre}</strong>,</p>

                    <p>
                        Nos complace informarle que su 
                        <strong>Registro Mensual de Instructor (RMI)</strong>
                        correspondiente al periodo <strong>{$periodo}</strong>
                        ha sido <span style='color:green; font-weight:bold;'>APROBADO</span>.
                    </p>

                    <div style='background:#f4f6f7;padding:12px;border-left:4px solid #2ecc71;margin:15px 0;'>
                        No se requieren acciones adicionales por su parte.
                    </div>

                    <p>
                        Si tiene alguna inquietud, puede comunicarse con el equipo administrativo.
                    </p>

                    <br>

                    <p>
                        Atentamente,<br>
                        <strong>Equipo Administrativo</strong><br>
                        Sistema de Gestión Académica
                    </p>
                </div>
                ";

                Mail::html($html, function ($message) use ($email) {
                    $message->to($email)
                        ->subject('Notificación de aprobación de RMI');
                });
            } catch (\Exception $e) {
                \Log::error('Error enviando correo RMI: ' . $e->getMessage());
            }

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

            try {

                $html = "
                <p>Estimado(a) <strong>{$nombre}</strong>,</p>

                <p>
                    Le informamos que su <strong>RMI</strong> correspondiente al periodo 
                    <strong>{$periodo}</strong> ha sido <span style='color:red;'><strong>rechazado</strong></span>.
                </p>

                <p><strong>Motivo del rechazo:</strong></p>

                <blockquote style='background:#f8f9fa;padding:10px;border-left:4px solid #dc3545;'>
                    {$validated['motivo']}
                </blockquote>

                <p>
                    Por favor revise las observaciones y realice las correcciones necesarias.
                </p>

                <br>

                <p>
                    Atentamente,<br>
                    <strong>Equipo administrativo</strong>
                </p>
                ";

                Mail::html($html, function ($message) use ($email) {
                    $message->to($email)
                        ->subject('Rechazo de RMI');
                });
            } catch (\Exception $e) {
                \Log::error('Error enviando correo RMI: ' . $e->getMessage());
            }

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
}
