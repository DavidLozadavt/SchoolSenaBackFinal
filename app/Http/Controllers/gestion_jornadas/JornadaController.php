<?php

namespace App\Http\Controllers\gestion_jornadas;

use App\Http\Controllers\Controller;
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
            // ✅ Validación
            $validated = $request->validate([
                'nombreJornada' => 'required|string|max:255',
                'descripcion' => 'nullable|string',
                'dias' => 'required|array|min:1',
                'dias.*' => 'in:Lunes,Martes,Miércoles,Jueves,Viernes,Sábado,Domingo',
                'horaInicial' => 'required|date_format:H:i',
                'horaFinal' => 'required|date_format:H:i',
            ]);

            // 🏢 Empresa activa DESDE EL TOKEN
            $payload = JWTAuth::parseToken()->getPayload();
            $idEmpresa = $payload->get('idCompany');

            if (!$idEmpresa) {
                return response()->json([
                    'message' => 'No hay empresa activa seleccionada'
                ], 400);
            }

            return DB::transaction(function () use ($validated, $idEmpresa) {

                // 🧠 Grupo de jornada (bandera)
                $grupoJornada = (Jornada::where('idCompany', $idEmpresa)->max('grupoJornada') ?? 0) + 1;

                // ⏱️ Cálculo de horas
                $hInicio = Carbon::createFromFormat('H:i', $validated['horaInicial']);
                $hFin = Carbon::createFromFormat('H:i', $validated['horaFinal']);

                if ($hFin <= $hInicio) {
                    $hFin->addDay();
                }

                $numeroHoras = $hFin->floatDiffInHours($hInicio);

                $jornadas = [];

                foreach ($validated['dias'] as $dia) {
                    $jornadas[] = Jornada::create([
                        'nombreJornada' => $validated['nombreJornada'],
                        'descripcion' => $validated['descripcion'],
                        'diaSemana' => $dia,
                        'horaInicial' => $validated['horaInicial'],
                        'horaFinal' => $validated['horaFinal'],
                        'numeroHoras' => $numeroHoras,
                        'estado' => 'Activo',
                        'grupoJornada' => $grupoJornada,
                        'idEmpresa' => $idEmpresa,
                        'idCompany' => $idEmpresa,
                        'idTipoJornada' => TipoJornada::MATERIAS,
                    ]);
                }

                return response()->json([
                    'message' => 'Jornadas de materias creadas exitosamente',
                    'grupoJornada' => $grupoJornada,
                    'jornadas' => $jornadas,
                ], 201);
            });

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            \Log::error($e);
            return response()->json([
                'message' => 'Ocurrió un error interno'
            ], 500);
        }
    }

    public function getJornadasMaterias()
    {
        try {
            // 🏢 Empresa desde el token
            $payload = JWTAuth::parseToken()->getPayload();
            $idEmpresa = $payload->get('idCompany');

            if (!$idEmpresa) {
                return response()->json([
                    'message' => 'No hay empresa activa seleccionada'
                ], 400);
            }

            // Obtener todas las jornadas de materias de la empresa
            $jornadas = Jornada::where('idEmpresa', $idEmpresa)
                ->where('idTipoJornada', TipoJornada::MATERIAS)
                ->orderBy('grupoJornada')
                ->orderBy('horaInicial')
                ->get()
                ->groupBy('grupoJornada')
                ->map(function ($grupo) {

                    $primera = $grupo->first();
                    if (!$primera)
                        return null;

                    $tipoHorario = 'No definido';

                    $tipoHorario = 'No definido';

                    if ($primera->horaInicial) {
                        try {
                            // Acepta H:i o H:i:s
                            $horaInicio = Carbon::parse($primera->horaInicial);

                            if ($horaInicio->between(Carbon::createFromTime(5, 0), Carbon::createFromTime(11, 59))) {
                                $tipoHorario = 'Mañana';
                            } elseif ($horaInicio->between(Carbon::createFromTime(12, 0), Carbon::createFromTime(17, 59))) {
                                $tipoHorario = 'Tarde';
                            } else {
                                $tipoHorario = 'Nocturna';
                            }
                        } catch (\Exception $e) {
                            $tipoHorario = 'No definido';
                        }
                    }


                    return [
                        'grupoJornada' => $primera->grupoJornada,
                        'nombreJornada' => $primera->nombreJornada,
                        'descripcion' => $primera->descripcion,
                        'horaInicial' => $primera->horaInicial,
                        'horaFinal' => $primera->horaFinal,
                        'numeroHoras' => $primera->numeroHoras,
                        'tipoHorario' => $tipoHorario,
                        'dias' => $grupo->pluck('diaSemana')->values(),
                        'estado' => $primera->estado,
                    ];
                })
                ->filter() // eliminar posibles grupos vacíos
                ->values();

            return response()->json([
                'data' => $jornadas
            ], 200);

        } catch (\Exception $e) {
            \Log::error($e);

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
            // ✅ Validación del grupoJornada
            $validated = $request->validate([
                'grupoJornada' => 'required|integer|min:1',
            ]);

            $grupoJornada = $validated['grupoJornada'];

            // 🏢 Empresa activa desde token
            $payload = JWTAuth::parseToken()->getPayload();
            $idEmpresa = $payload->get('idCompany');

            if (!$idEmpresa) {
                return response()->json([
                    'message' => 'No hay empresa activa seleccionada'
                ], 400);
            }

            // Buscar jornadas de ese grupo
            $jornadas = Jornada::where('idEmpresa', $idEmpresa)
                ->where('grupoJornada', $grupoJornada)
                ->get();

            if ($jornadas->isEmpty()) {
                return response()->json([
                    'message' => "No se encontraron jornadas para el grupoJornada $grupoJornada"
                ], 404);
            }

            // Eliminación dentro de transacción
            DB::transaction(function () use ($jornadas) {
                foreach ($jornadas as $j) {
                    $j->delete();
                }
            });

            return response()->json([
                'message' => "Se eliminaron las jornadas del grupoJornada $grupoJornada exitosamente",
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            \Log::error($e);
            return response()->json([
                'message' => 'Ocurrió un error interno'
            ], 500);
        }
    }

    public function actualizarJornadaMaterias(Request $request)
    {
        try {
            // ✅ Validación
            $validated = $request->validate([
                'grupoJornada' => 'required|integer|min:1',
                'nombreJornada' => 'required|string|max:255',
                'descripcion' => 'nullable|string',
                'dias' => 'required|array|min:1',
                'dias.*' => 'in:Lunes,Martes,Miércoles,Jueves,Viernes,Sábado,Domingo',
                'horaInicial' => 'required|date_format:H:i',
                'horaFinal' => 'required|date_format:H:i',
            ]);

            // 🏢 Empresa activa desde token
            $payload = JWTAuth::parseToken()->getPayload();
            $idEmpresa = $payload->get('idCompany');

            if (!$idEmpresa) {
                return response()->json([
                    'message' => 'No hay empresa activa seleccionada'
                ], 400);
            }

            return DB::transaction(function () use ($validated, $idEmpresa) {

                $grupoJornada = $validated['grupoJornada'];

                // Obtener todas las jornadas actuales del grupo
                $jornadasActuales = Jornada::where('idEmpresa', $idEmpresa)
                    ->where('grupoJornada', $grupoJornada)
                    ->get();

                if ($jornadasActuales->isEmpty()) {
                    return response()->json([
                        'message' => "No se encontraron jornadas para el grupoJornada $grupoJornada"
                    ], 404);
                }

                // ⏱️ Cálculo de horas
                $hInicio = Carbon::createFromFormat('H:i', $validated['horaInicial']);
                $hFin = Carbon::createFromFormat('H:i', $validated['horaFinal']);
                if ($hFin <= $hInicio) $hFin->addDay();
                $numeroHoras = $hFin->floatDiffInHours($hInicio);

                // Comparar días para notificaciones
                $diasActuales = $jornadasActuales->pluck('diaSemana')->toArray();
                $diasNuevos = $validated['dias'];

                $diasAgregados = array_diff($diasNuevos, $diasActuales);
                $diasEliminados = array_diff($diasActuales, $diasNuevos);

                // Eliminar jornadas que ya no están en los días nuevos
                Jornada::where('idEmpresa', $idEmpresa)
                    ->where('grupoJornada', $grupoJornada)
                    ->whereNotIn('diaSemana', $diasNuevos)
                    ->delete();

                $actualizadas = [];

                foreach ($diasNuevos as $dia) {
                    $jornada = Jornada::updateOrCreate(
                        [
                            'idEmpresa' => $idEmpresa,
                            'grupoJornada' => $grupoJornada,
                            'diaSemana' => $dia
                        ],
                        [
                            'nombreJornada' => $validated['nombreJornada'],
                            'descripcion' => $validated['descripcion'],
                            'horaInicial' => $validated['horaInicial'],
                            'horaFinal' => $validated['horaFinal'],
                            'numeroHoras' => $numeroHoras,
                            'estado' => 'Activo',
                            'idTipoJornada' => TipoJornada::MATERIAS,
                        ]
                    );
                    $actualizadas[] = $jornada;
                }

                // Preparar mensaje de notificación
                $notificacion = [];
                if (!empty($diasAgregados)) $notificacion[] = 'Se agregaron días: ' . implode(', ', $diasAgregados);
                if (!empty($diasEliminados)) $notificacion[] = 'Se eliminaron días: ' . implode(', ', $diasEliminados);

                return response()->json([
                    'message' => 'Jornadas actualizadas exitosamente',
                    'grupoJornada' => $grupoJornada,
                    'jornadas' => $actualizadas,
                    'notificacion' => $notificacion
                ], 200);
            });

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            \Log::error($e);
            return response()->json([
                'message' => 'Ocurrió un error interno'
            ], 500);
        }
    }
    
    public function cambiarEstadoJornada(Request $request)
    {
        try {
            // ✅ Validación simple
            $validated = $request->validate([
                'grupoJornada' => 'required|integer|min:1',
            ]);

            // 🏢 Empresa activa desde token
            $payload = JWTAuth::parseToken()->getPayload();
            $idEmpresa = $payload->get('idCompany');

            if (!$idEmpresa) {
                return response()->json([
                    'message' => 'No hay empresa activa seleccionada'
                ], 400);
            }

            $grupoJornada = $validated['grupoJornada'];

            // Obtener todas las jornadas del grupo
            $jornadas = Jornada::where('idEmpresa', $idEmpresa)
                ->where('grupoJornada', $grupoJornada)
                ->get();

            if ($jornadas->isEmpty()) {
                return response()->json([
                    'message' => "No se encontraron jornadas para el grupoJornada $grupoJornada"
                ], 404);
            }

            // Cambiar estado: si alguna está Activo -> Inactivo, si todas Inactivo -> Activo
            $nuevoEstado = $jornadas->first()->estado === 'Activo' ? 'Inactivo' : 'Activo';

            Jornada::where('idEmpresa', $idEmpresa)
                ->where('grupoJornada', $grupoJornada)
                ->update(['estado' => $nuevoEstado]);

            return response()->json([
                'message' => "Estado actualizado a $nuevoEstado para todas las jornadas del grupo $grupoJornada",
                'estado' => $nuevoEstado,
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            \Log::error($e);
            return response()->json([
                'message' => 'Ocurrió un error interno'
            ], 500);
        }
    }




}