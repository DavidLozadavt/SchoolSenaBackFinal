<?php

namespace App\Http\Controllers;

use App\Models\ActivationCompanyUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InstructoresController extends Controller
{
    public function getInstructors(Request $request)
    {
        // Validamos que venga el parámetro obligatorio
        $request->validate([
            'idCentroFormacion' => 'required|integer|exists:centroFormacion,id',
        ]);

        $idCentroFormacion = $request->input('idCentroFormacion');

        $instructors = ActivationCompanyUser::with('user.persona')
            ->active()
            ->role('INSTRUCTOR SENA')
            ->whereHas('user', function ($q) use ($idCentroFormacion) {
                $q->where('idCentroFormacion', $idCentroFormacion);
            })
            ->get()
            ->map(function ($acu) {
                $user = $acu->user;
                $persona = $user->persona;

                return [
                    'idActivation' => $acu->id,
                    'emailUsuario' => $user->email,
                    'roles' => $acu->getRoleNames(),
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
}
