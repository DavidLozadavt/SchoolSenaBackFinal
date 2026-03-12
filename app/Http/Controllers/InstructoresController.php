<?php

namespace App\Http\Controllers;

use App\Models\ActivationCompanyUser;
use Illuminate\Http\Request;

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

                return [
                    'idActivation' => $acu->id,
                    'emailUsuario' => $user->email,
                    'idContrato'   => $contrato?->id,
                    'roles'        => $acu->getRoleNames(),
                    'horarios'     => $horarios,
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
}
