<?php

namespace App\Http\Controllers\gestion_jornadas;

use App\Http\Controllers\Controller;
use App\Models\AsignacionDiaJornada;
use App\Models\Jornada;
use App\Models\TipoJornada;
use App\Util\KeyUtil;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Tymon\JWTAuth\Facades\JWTAuth;

class JornadaController extends Controller
{
    public function crearJornadaMaterias(Request $request)
    {
        try {
            // Validación
            $validated = $request->validate([
                'nombreJornada' => 'required|string',
                'descripcion' => 'nullable|string',
                'dias' => 'required|array|min:1',
                'horaInicial' => 'required|date_format:H:i',
                'horaFinal' => 'required|date_format:H:i',
                'idCentroFormacion' => 'required',
            ]);

            return DB::transaction(function () use ($validated) {

                // Cálculo de horas
                $hInicio = Carbon::createFromFormat('H:i', $validated['horaInicial']);
                $hFin = Carbon::createFromFormat('H:i', $validated['horaFinal']);

                if ($hFin <= $hInicio) {
                    $hFin->addDay();
                }

                $numeroHoras = $hFin->floatDiffInHours($hInicio);

                $jornada = Jornada::create([
                    'nombreJornada' => $validated['nombreJornada'],
                    'descripcion' => $validated['descripcion'],
                    'horaInicial' => $validated['horaInicial'],
                    'horaFinal' => $validated['horaFinal'],
                    'numeroHoras' => $numeroHoras,
                    'estado' => 'Activo',
                    'idCentroFormacion' => $validated['idCentroFormacion'],
                    'idTipoJornada' => TipoJornada::MATERIAS,
                ]);

                foreach ($validated['dias'] as $dia) {
                    AsignacionDiaJornada::create([
                        'idDia' => $dia['id'],
                        'idJornada' => $jornada->id,
                    ]);
                }

                return response()->json([
                    'message' => 'Jornada de materias creada exitosamente'
                ], 201);
            });
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Ocurrió un error interno'
            ], 500);
        }
    }

    public function getJornadasMaterias(Request $request)
    {
        try {
            // Obtener todas las jornadas de materias del centro de formacion
            $idcentroFormacion = $request->input('idCentroFormacion');

            $jornadas = Jornada::where('idCentroFormacion', $idcentroFormacion)
                ->where('idTipoJornada', TipoJornada::MATERIAS)
                ->with('dias')
                ->orderBy('horaInicial')
                ->get();

            return response()->json([
                'data' => $jornadas
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener las jornadas',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }

    public function eliminarJornadaMaterias(Request $request)
    {
        try {
            $validated = $request->validate([
                'id' => 'required|exists:jornadas,id',
            ]);

            $jornada = Jornada::findOrFail($validated['id']);

            DB::transaction(function () use ($jornada) {
                $jornada->dias()->detach();
                $jornada->delete();
            });

            return response()->json([
                'message' => "Jornada eliminada exitosamente",
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Ocurrió un error interno'
            ], 500);
        }
    }

    public function actualizarJornadaMaterias(Request $request)
    {
        try {
            $validated = $request->validate([
                'id' => 'required|exists:jornadas,id',
                'nombreJornada' => 'required|string|max:255',
                'descripcion' => 'nullable|string',
                'dias' => 'required|array|min:1',
                'horaInicial' => 'required|date_format:H:i',
                'horaFinal' => 'required|date_format:H:i',
                'idCentroFormacion' => 'required',
            ]);

            return DB::transaction(function () use ($validated) {
                $jornada = Jornada::findOrFail($validated['id']);

                // Cálculo de horas
                $hInicio = Carbon::createFromFormat('H:i', $validated['horaInicial']);
                $hFin = Carbon::createFromFormat('H:i', $validated['horaFinal']);
                if ($hFin <= $hInicio) $hFin->addDay();
                $numeroHoras = $hFin->floatDiffInHours($hInicio);

                // Actualizar datos básicos
                $jornada->update([
                    'nombreJornada' => $validated['nombreJornada'],
                    'descripcion' => $validated['descripcion'],
                    'horaInicial' => $validated['horaInicial'],
                    'horaFinal' => $validated['horaFinal'],
                    'numeroHoras' => $numeroHoras,
                    'idCentroFormacion' => $validated['idCentroFormacion'],
                ]);

                // Sincronizar días (Pivote)
                $idsDias = collect($validated['dias'])->pluck('id')->toArray();
                $jornada->dias()->sync($idsDias);

                return response()->json([
                    'message' => 'Jornada actualizada exitosamente',
                    'jornada' => $jornada->load('dias')
                ], 200);
            });
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Ocurrió un error interno'
            ], 500);
        }
    }

    public function cambiarEstadoJornada(Request $request)
    {
        try {
            $validated = $request->validate([
                'id' => 'required|exists:jornadas,id',
            ]);

            $jornada = Jornada::findOrFail($validated['id']);
            $nuevoEstado = $jornada->estado === 'Activo' ? 'Inactivo' : 'Activo';

            $jornada->update(['estado' => $nuevoEstado]);

            return response()->json([
                'message' => "Estado actualizado a $nuevoEstado",
                'estado' => $nuevoEstado,
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Ocurrió un error interno'
            ], 500);
        }
    }
}
