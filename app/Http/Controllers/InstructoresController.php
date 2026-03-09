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
        ]);

        $instructors = ActivationCompanyUser::with([
            'user.persona.contracts' => function ($q) {
                $q->latest()->with([
                    'horarioMateria' => function ($h) {
                        $h->select(
                            'id',
                            'idContrato',
                            'horaInicial',
                            'horaFinal',
                            'estado',
                            'idDia'
                        )->where('estado', 'ASIGNADO');
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

                $user = $acu->user;
                $persona = $user->persona;
                $contrato = $persona->contracts->first();

                $horarios = $contrato?->horarioMateria->map(function ($h) {
                    $horaInicial = strtotime($h->horaInicial);
                    $horaFinal = strtotime($h->horaFinal);

                    // Duración en horas decimales
                    $duracionHoras = ($horaFinal - $horaInicial) / 3600;

                    return [
                        'id' => $h->id,
                        'idContrato' => $h->idContrato,
                        'horaInicial' => $h->horaInicial,
                        'horaFinal' => $h->horaFinal,
                        'estado' => $h->estado,
                        'idDia' => $h->idDia,
                        'duracionHoras' => round($duracionHoras, 2), // redondeo a 2 decimales
                    ];
                });

                return [
                    'idActivation' => $acu->id,
                    'emailUsuario' => $user->email,
                    'idContrato' => $contrato?->id,
                    'roles' => $acu->getRoleNames(),
                    'horarios' => $horarios,
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

        return response()->json($instructors);
    }
    public function getFichasByContrato(Request $request)
    {
        $validated = $request->validate([
            'idContrato' => 'required|integer|exists:contrato,id',
        ]);

        $horarios = \App\Models\HorarioMateria::with([
            'ficha.asignacion.programa',
            'gradoMateria.materia.padre',
        ])
            ->where('idContrato', $validated['idContrato'])
            ->where('estado', 'ASIGNADO')
            ->get();

        $fichas = $horarios->groupBy('idFicha')->map(function ($horariosGrupo) {
            $ficha    = $horariosGrupo->first()->ficha;
            $programa = $ficha?->asignacion?->programa;

            return [
                'idFicha'            => $ficha?->id,
                'codigoFicha'        => $ficha?->codigo,
                'programaFormacion'  => $programa?->nombrePrograma,
                'codigoPrograma'     => $programa?->codigoPrograma,
                'resultados'         => $horariosGrupo->map(function ($h) {
                    $rap         = $h->gradoMateria?->materia;         // Resultado de Aprendizaje
                    $competencia = $rap?->padre;                // Competencia (padre)

                    return [
                        'idHorario'          => $h->id,
                        'competencia'        => $competencia?->nombreMateria,
                        'resultadoAprendizaje' => $rap?->nombreMateria,
                        'horaInicial'        => $h->horaInicial,
                        'horaFinal'          => $h->horaFinal,
                        'duracionHoras'      => round((strtotime($h->horaFinal) - strtotime($h->horaInicial)) / 3600, 2),
                        'idDia'              => $h->idDia,
                    ];
                })->values(),
            ];
        })->values();

        return response()->json($fichas);
    }
}
