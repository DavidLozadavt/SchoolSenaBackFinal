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

                        if (!empty($validated['periodo'])) {
                            $inicio = \Carbon\Carbon::createFromFormat('Y-m', $validated['periodo'])->startOfMonth();
                            $fin    = \Carbon\Carbon::createFromFormat('Y-m', $validated['periodo'])->endOfMonth();

                            $h->where(function ($q) use ($inicio, $fin) {
                                $q->whereBetween('fechaInicial', [$inicio, $fin])
                                    ->orWhereBetween('fechaFinal', [$inicio, $fin])
                                    ->orWhere(function ($q2) use ($inicio, $fin) {
                                        $q2->where('fechaInicial', '<=', $inicio)
                                            ->where('fechaFinal', '>=', $fin);
                                    });
                            });
                        }
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
            ->map(function ($acu) {

                $user     = $acu->user;
                $persona  = $user->persona;
                $contrato = $persona->contracts->first();

                $horarios = $contrato?->horarioMateria->map(function ($h) {
                    $horaInicial   = strtotime($h->horaInicial);
                    $horaFinal     = strtotime($h->horaFinal);
                    $duracionHoras = ($horaFinal - $horaInicial) / 3600;

                    return [
                        'id'            => $h->id,
                        'idContrato'    => $h->idContrato,
                        'horaInicial'   => $h->horaInicial,
                        'horaFinal'     => $h->horaFinal,
                        'estado'        => $h->estado,
                        'idDia'         => $h->idDia,
                        'fechaInicial'  => $h->fechaInicial,
                        'fechaFinal'    => $h->fechaFinal,
                        'duracionHoras' => round($duracionHoras, 2),
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
            'periodo'    => 'nullable|date_format:Y-m', // ej: 2026-03
        ]);

        $query = \App\Models\HorarioMateria::with([
            'ficha.asignacion.programa',
            'gradoMateria.materia.padre',
        ])
            ->where('idContrato', $validated['idContrato'])
            ->where('estado', 'ASIGNADO');

        // Filtro por periodo (mes/año)
        if (!empty($validated['periodo'])) {
            $inicio = \Carbon\Carbon::createFromFormat('Y-m', $validated['periodo'])->startOfMonth();
            $fin    = \Carbon\Carbon::createFromFormat('Y-m', $validated['periodo'])->endOfMonth();

            $query->where(function ($q) use ($inicio, $fin) {
                $q->whereBetween('fechaInicial', [$inicio, $fin])
                    ->orWhereBetween('fechaFinal', [$inicio, $fin])
                    ->orWhere(function ($q2) use ($inicio, $fin) {
                        // Horarios que abarcan todo el mes
                        $q2->where('fechaInicial', '<=', $inicio)
                            ->where('fechaFinal', '>=', $fin);
                    });
            });
        }

        $horarios = $query->get();

        $fichas = $horarios->groupBy('idFicha')->map(function ($horariosGrupo) {
            $ficha    = $horariosGrupo->first()->ficha;
            $programa = $ficha?->asignacion?->programa;

            return [
                'idFicha'           => $ficha?->id,
                'codigoFicha'       => $ficha?->codigo,
                'programaFormacion' => $programa?->nombrePrograma,
                'codigoPrograma'    => $programa?->codigoPrograma,
                'resultados'        => $horariosGrupo->map(function ($h) {
                    $rap         = $h->gradoMateria?->materia;
                    $competencia = $rap?->padre;

                    return [
                        'idHorario'            => $h->id,
                        'idGradoMateria'       => $h->idGradoMateria,
                        'competencia'          => $competencia?->nombreMateria,
                        'resultadoAprendizaje' => $rap?->nombreMateria,
                        'horaInicial'          => $h->horaInicial,
                        'horaFinal'            => $h->horaFinal,
                        'fechaInicial'         => $h->fechaInicial,
                        'fechaFinal'           => $h->fechaFinal,
                        'duracionHoras'        => round((strtotime($h->horaFinal) - strtotime($h->horaInicial)) / 3600, 2),
                        'idDia'                => $h->idDia,
                    ];
                })->values(),
            ];
        })->values();

        return response()->json($fichas);
    }
}
