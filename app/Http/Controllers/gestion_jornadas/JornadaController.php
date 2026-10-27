<?php

namespace App\Http\Controllers\gestion_jornadas;

use App\Http\Controllers\Controller;
use App\Models\Jornada;
use App\Models\CentrosFormacion;
use App\Models\Dia;
use App\Models\TipoJornada;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Validation\ValidationException;

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
                'dias.*' => 'required',
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

            $idCentroFormacion = $this->authenticatedCenterId();
            if (!$idCentroFormacion) {
                return $this->noAssignedCenterResponse();
            }

            if (!$this->centroBelongsToCompany($idCentroFormacion, (int) $idEmpresa)) {
                return response()->json(['message' => 'El centro no pertenece a la empresa activa'], 403);
            }

            $dayIds = $this->resolveDayIds($validated['dias']);
            $horaInicial = $validated['horaInicial'];
            $horaFinal = $validated['horaFinal'];
            $hInicio = Carbon::createFromFormat('H:i', $horaInicial);
            $hFin = Carbon::createFromFormat('H:i', $horaFinal);
            if ($hFin <= $hInicio) {
                $hFin->addDay();
            }
            $numeroHoras = $hInicio->diffInMinutes($hFin) / 60;

            $jornada = DB::transaction(function () use ($validated, $dayIds, $idCentroFormacion, $numeroHoras) {
                $jornada = Jornada::create([
                    'nombreJornada' => $validated['nombreJornada'],
                    'descripcion' => $validated['descripcion'] ?? null,
                    'horaInicial' => $validated['horaInicial'],
                    'horaFinal' => $validated['horaFinal'],
                    'numeroHoras' => $numeroHoras,
                    'estado' => 'Activo',
                    'idCentroFormacion' => $idCentroFormacion,
                    'idTipoJornada' => TipoJornada::MATERIAS,
                ]);
                $jornada->dias()->sync($dayIds);

                return $jornada->load('dias:id,dia');
            });

            return response()->json([
                'message' => 'Jornada de materias creada exitosamente',
                'grupoJornada' => $jornada->id,
                'jornadas' => [$this->formatJornada($jornada)],
            ], 201);

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

            $idCentroFormacion = $this->authenticatedCenterId();
            if (!$idCentroFormacion) {
                return $this->noAssignedCenterResponse();
            }
            if (!$this->centroBelongsToCompany($idCentroFormacion, (int) $idEmpresa)) {
                return response()->json(['message' => 'El centro no pertenece a la empresa activa'], 403);
            }

            $jornadas = $this->jornadasDelCentro($idCentroFormacion)
                ->with('dias:id,dia')
                ->orderBy('horaInicial')
                ->get()
                ->map(fn (Jornada $jornada) => $this->formatJornada($jornada))
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
            $validated = $request->validate([
                'id' => 'required_without:grupoJornada|integer|min:1',
                'grupoJornada' => 'required_without:id|integer|min:1',
            ]);
            $idEmpresa = $this->activeCompanyId();
            if (!$idEmpresa) {
                return $this->noActiveCompanyResponse();
            }

            $idCentroFormacion = $this->authenticatedCenterId();
            if (!$idCentroFormacion) {
                return $this->noAssignedCenterResponse();
            }
            if (!$this->centroBelongsToCompany($idCentroFormacion, $idEmpresa)) {
                return response()->json(['message' => 'El centro no pertenece a la empresa activa'], 403);
            }

            $jornadaId = (int) ($validated['id'] ?? $validated['grupoJornada']);
            $jornada = $this->jornadasDelCentro($idCentroFormacion)->whereKey($jornadaId)->first();
            if (!$jornada) {
                return response()->json(['message' => 'No se encontró la jornada'], 404);
            }

            DB::transaction(function () use ($jornada) {
                $jornada->dias()->detach();
                $jornada->delete();
            });

            return response()->json(['message' => 'Jornada eliminada exitosamente']);

        } catch (ValidationException $e) {
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
            $validated = $request->validate([
                'id' => 'required_without:grupoJornada|integer|min:1',
                'grupoJornada' => 'required_without:id|integer|min:1',
                'nombreJornada' => 'required|string|max:255',
                'descripcion' => 'nullable|string',
                'dias' => 'required|array|min:1',
                'dias.*' => 'required',
                'horaInicial' => 'required|date_format:H:i',
                'horaFinal' => 'required|date_format:H:i',
            ]);
            $idEmpresa = $this->activeCompanyId();
            if (!$idEmpresa) {
                return $this->noActiveCompanyResponse();
            }

            $idCentroFormacion = $this->authenticatedCenterId();
            if (!$idCentroFormacion) {
                return $this->noAssignedCenterResponse();
            }
            if (!$this->centroBelongsToCompany($idCentroFormacion, $idEmpresa)) {
                return response()->json(['message' => 'El centro no pertenece a la empresa activa'], 403);
            }

            $jornadaId = (int) ($validated['id'] ?? $validated['grupoJornada']);
            $jornada = $this->jornadasDelCentro($idCentroFormacion)->whereKey($jornadaId)->first();
            if (!$jornada) {
                return response()->json(['message' => 'No se encontró la jornada'], 404);
            }

            $dayIds = $this->resolveDayIds($validated['dias']);
            $diasActuales = $jornada->dias()->pluck('dia')->all();
            $diasNuevos = Dia::whereIn('id', $dayIds)->pluck('dia')->all();
            $notificacion = [];
            $diasAgregados = array_diff($diasNuevos, $diasActuales);
            $diasEliminados = array_diff($diasActuales, $diasNuevos);
            if ($diasAgregados) {
                $notificacion[] = 'Se agregaron días: ' . implode(', ', $diasAgregados);
            }
            if ($diasEliminados) {
                $notificacion[] = 'Se eliminaron días: ' . implode(', ', $diasEliminados);
            }

            $hInicio = Carbon::createFromFormat('H:i', $validated['horaInicial']);
            $hFin = Carbon::createFromFormat('H:i', $validated['horaFinal']);
            if ($hFin <= $hInicio) {
                $hFin->addDay();
            }
            $numeroHoras = $hInicio->diffInMinutes($hFin) / 60;

            DB::transaction(function () use ($jornada, $validated, $idCentroFormacion, $dayIds, $numeroHoras) {
                $jornada->fill([
                    'nombreJornada' => $validated['nombreJornada'],
                    'descripcion' => $validated['descripcion'] ?? null,
                    'horaInicial' => $validated['horaInicial'],
                    'horaFinal' => $validated['horaFinal'],
                    'numeroHoras' => $numeroHoras,
                    'idCentroFormacion' => $idCentroFormacion,
                ])->save();
                $jornada->dias()->sync($dayIds);
            });

            $jornada->load('dias:id,dia');

            return response()->json([
                'message' => 'Jornada actualizada exitosamente',
                'grupoJornada' => $jornada->id,
                'jornadas' => [$this->formatJornada($jornada)],
                'notificacion' => $notificacion,
            ]);
        } catch (ValidationException $e) {
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
            $validated = $request->validate([
                'id' => 'required_without:grupoJornada|integer|min:1',
                'grupoJornada' => 'required_without:id|integer|min:1',
            ]);
            $idEmpresa = $this->activeCompanyId();
            if (!$idEmpresa) {
                return $this->noActiveCompanyResponse();
            }

            $idCentroFormacion = $this->authenticatedCenterId();
            if (!$idCentroFormacion) {
                return $this->noAssignedCenterResponse();
            }
            if (!$this->centroBelongsToCompany($idCentroFormacion, $idEmpresa)) {
                return response()->json(['message' => 'El centro no pertenece a la empresa activa'], 403);
            }

            $jornadaId = (int) ($validated['id'] ?? $validated['grupoJornada']);
            $jornada = $this->jornadasDelCentro($idCentroFormacion)->whereKey($jornadaId)->first();
            if (!$jornada) {
                return response()->json(['message' => 'No se encontró la jornada'], 404);
            }

            $jornada->estado = $jornada->estado === 'Activo' ? 'Inactivo' : 'Activo';
            $jornada->save();

            return response()->json([
                'message' => "Estado actualizado a {$jornada->estado}",
                'estado' => $jornada->estado,
            ]);

        } catch (ValidationException $e) {
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

    private function activeCompanyId(): ?int
    {
        $idEmpresa = JWTAuth::parseToken()->getPayload()->get('idCompany');

        return $idEmpresa ? (int) $idEmpresa : null;
    }

    private function authenticatedCenterId(): ?int
    {
        $user = JWTAuth::parseToken()->authenticate();
        $idCentroFormacion = $user?->idCentroFormacion;

        return $idCentroFormacion ? (int) $idCentroFormacion : null;
    }

    private function noActiveCompanyResponse()
    {
        return response()->json(['message' => 'No hay empresa activa seleccionada'], 400);
    }

    private function noAssignedCenterResponse()
    {
        return response()->json(['message' => 'El usuario autenticado no tiene un centro de formación asignado'], 422);
    }

    private function jornadasDelCentro(int $idCentroFormacion): Builder
    {
        return Jornada::query()
            ->where('idTipoJornada', TipoJornada::MATERIAS)
            ->where('idCentroFormacion', $idCentroFormacion);
    }

    private function centroBelongsToCompany(int $idCentroFormacion, int $idEmpresa): bool
    {
        return CentrosFormacion::query()
            ->whereKey($idCentroFormacion)
            ->where('idEmpresa', $idEmpresa)
            ->exists();
    }

    private function resolveDayIds(array $dias): array
    {
        $availableDays = Dia::query()->get(['id', 'dia']);
        $daysByName = $availableDays->keyBy(fn (Dia $day) => $this->normalizeDay($day->dia));
        $dayIds = [];

        foreach ($dias as $index => $value) {
            $day = is_numeric($value)
                ? $availableDays->firstWhere('id', (int) $value)
                : $daysByName->get($this->normalizeDay((string) $value));

            if (!$day) {
                throw ValidationException::withMessages([
                    "dias.$index" => 'El día seleccionado no existe.',
                ]);
            }

            $dayIds[] = (int) $day->id;
        }

        return array_values(array_unique($dayIds));
    }

    private function normalizeDay(string $day): string
    {
        return strtr(mb_strtoupper(trim($day), 'UTF-8'), [
            'Á' => 'A',
            'É' => 'E',
            'Í' => 'I',
            'Ó' => 'O',
            'Ú' => 'U',
        ]);
    }

    private function formatJornada(Jornada $jornada): array
    {
        $tipoHorario = 'No definido';
        if ($jornada->horaInicial) {
            $horaInicio = Carbon::parse($jornada->horaInicial);
            if ($horaInicio->between(Carbon::createFromTime(5, 0), Carbon::createFromTime(11, 59))) {
                $tipoHorario = 'Mañana';
            } elseif ($horaInicio->between(Carbon::createFromTime(12, 0), Carbon::createFromTime(17, 59))) {
                $tipoHorario = 'Tarde';
            } else {
                $tipoHorario = 'Nocturna';
            }
        }

        return [
            'id' => $jornada->id,
            'grupoJornada' => $jornada->id,
            'idCentroFormacion' => $jornada->idCentroFormacion,
            'nombreJornada' => $jornada->nombreJornada,
            'descripcion' => $jornada->descripcion,
            'horaInicial' => $jornada->horaInicial,
            'horaFinal' => $jornada->horaFinal,
            'numeroHoras' => $jornada->numeroHoras,
            'tipoHorario' => $tipoHorario,
            'dias' => $jornada->dias->pluck('dia')->values(),
            'estado' => $jornada->estado,
        ];
    }



}
