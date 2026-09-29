<?php

namespace App\Http\Controllers\gestion_horarios;

use App\Http\Controllers\Controller;
use App\Models\CalificacionSesion;
use App\Models\SesionMateria;
use App\Models\MatriculaAcademica;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CalificacionSesionController extends Controller
{
    /**
     * Registrar/Actualizar calificación de una sesión (Estudiante)
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'idSesionMateria' => 'required|integer|exists:sesionMateria,id',
            'estrellas' => 'required|integer|min:1|max:5',
            'comentarios' => 'nullable|string|max:1000',
        ]);

        $user = auth()->user();
        if (!$user || !$user->idpersona) {
            return response()->json(['message' => 'No autorizado'], 401);
        }

        $sesion = SesionMateria::findOrFail($request->idSesionMateria);
        $horario = $sesion->horarioMateria;

        if (!$horario) {
            return response()->json(['message' => 'Horario no encontrado para esta sesión'], 404);
        }

        $gradoMateria = \DB::table('gradoMateria')->where('id', $horario->idGradoMateria)->first();
        if (!$gradoMateria) {
            return response()->json(['message' => 'Asignatura no encontrada'], 404);
        }

        $matriculaAcademica = MatriculaAcademica::whereHas('matricula', function($q) use ($user) {
            $q->where('idPersona', $user->idpersona);
        })
        ->where('idFicha', $horario->idFicha)
        ->where('idMateria', $gradoMateria->idMateria)
        ->first();

        if (!$matriculaAcademica) {
            return response()->json(['message' => 'No estás matriculado en esta materia/ficha'], 403);
        }

        $calificacion = CalificacionSesion::updateOrCreate([
            'idSesionMateria' => $request->idSesionMateria,
            'idMatriculaAcademica' => $matriculaAcademica->id,
        ], [
            'estrellas' => $request->estrellas,
            'comentarios' => $request->comentarios,
        ]);

        return response()->json([
            'message' => 'Calificación registrada con éxito',
            'data' => $calificacion
        ], 201);
    }

    /**
     * Obtener las calificaciones de una sesión (Instructor)
     */
    public function getPorSesion(int $idSesionMateria): JsonResponse
    {
        $calificaciones = CalificacionSesion::where('idSesionMateria', $idSesionMateria)
            ->with(['matriculaAcademica.matricula.person'])
            ->get();

        $formated = $calificaciones->map(function ($calif) {
            $persona = $calif->matriculaAcademica?->matricula?->person;
            $backUrl = env('APP_URL') ?? '';
            $fotoUrl = ($persona && $persona->rutaFoto) ? $backUrl . $persona->rutaFoto : null;

            return [
                'id' => $calif->id,
                'estrellas' => $calif->estrellas,
                'comentarios' => $calif->comentarios,
                'fechaCalificacion' => $calif->created_at ? $calif->created_at->toDateTimeString() : null,
                'alumno' => $persona ? [
                    'nombre' => trim($persona->nombre1 . ' ' . $persona->nombre2 . ' ' . $persona->apellido1 . ' ' . $persona->apellido2),
                    'identificacion' => $persona->identificacion,
                    'email' => $persona->email,
                    'rutaFotoUrl' => $fotoUrl,
                ] : null
            ];
        });

        return response()->json($formated);
    }
}
