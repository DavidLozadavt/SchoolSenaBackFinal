<?php

namespace App\Http\Controllers\gestion_horarios;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\AsignacionSesion;
use App\Models\HorarioCompartido;
use App\Models\HorarioMateria;
use App\Models\SesionMateria;
use App\Models\Contract;
use App\Jobs\SendBasicEmail;

class AsignacionSesionController extends Controller
{
    /**
     * Asignar un reemplazo de clase o registrar un horario compartido.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'idHorarioMateria' => 'required|integer',
            'tipoAsignacion'   => 'required|in:REEMPLAZO,HORARIO COMPARTIDO',
            'fechaInicio'      => 'required|date',
            'fechaFin'         => 'required|date|after_or_equal:fechaInicio',
            'idContrato'       => 'nullable|integer',
        ]);

        DB::beginTransaction();

        try {
            if ($request->tipoAsignacion === 'HORARIO COMPARTIDO') {
                $data = $this->crearHorarioCompartido($request);
            } else {
                $data = $this->crearReemplazoClase($request);
            }

            DB::commit();

            return response()->json([
                'message' => 'Asignación creada correctamente',
                'data'    => $data,
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Error al asignar sesión',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Eliminar reemplazo o horario compartido.
     */
    public function desasignarSesiones(Request $request): JsonResponse
    {
        try {
            $horarioIds = $request->input('horarios', []);
            $idContrato = $request->input('idContrato');

            if (empty($horarioIds)) {
                return response()->json(['message' => 'No se enviaron horarios'], 400);
            }

            DB::beginTransaction();

            $reemplazos = AsignacionSesion::whereIn('idHorarioMateria', $horarioIds);
            if ($idContrato) {
                $reemplazos->where('idContratoRemplazo', $idContrato);
            }
            $reemplazos = $reemplazos->get();

            foreach ($reemplazos as $reemplazo) {
                $this->eliminarReemplazoClase($reemplazo);
            }

            $compartidos = HorarioCompartido::where(function ($q) use ($horarioIds) {
                $q->whereIn('idHorarioMateria', $horarioIds)
                    ->orWhereIn('idHorarioMateriaSecundario', $horarioIds);
            });
            if ($idContrato) {
                $compartidos->where('idContratoSecundario', $idContrato);
            }
            $compartidos = $compartidos->get();

            foreach ($compartidos as $compartido) {
                $this->eliminarHorarioCompartido($compartido);
            }

            if ($reemplazos->isEmpty() && $compartidos->isEmpty()) {
                DB::rollBack();

                return response()->json([
                    'message' => 'No se encontraron asignaciones para desasignar',
                ], 404);
            }

            DB::commit();

            return response()->json([
                'message' => 'Asignación eliminada correctamente',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Error al eliminar asignación',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    private function crearReemplazoClase(Request $request): array
    {
        $horario = HorarioMateria::find($request->idHorarioMateria);

        $payload = [
            'fechaInicio'      => $request->fechaInicio,
            'fechaFin'         => $request->fechaFin,
            'idContrato'       => $request->idContrato,
            'idHorarioMateria' => $request->idHorarioMateria,
            'observacion'      => $request->observacion,
            'estado'           => 'ACTIVO',
        ];

        if ($horario?->idContrato) {
            $payload['idContratoTrabajador'] = $horario->idContrato;
        }

        $reemplazo = AsignacionSesion::create($payload);

        if ($reemplazo->idContrato) {
            try {
                $this->enviarEmailReemplazo($reemplazo);
            } catch (\Exception $e) {
                // El reemplazo se creó aunque falle el correo.
            }
        }

        return $reemplazo->toAsignacionSesionApi();
    }

    private function crearHorarioCompartido(Request $request): array
    {
        $estado = $request->idContrato ? 'ACTIVO' : 'PENDIENTE';

        $compartido = HorarioCompartido::create([
            'idHorarioMateria'     => $request->idHorarioMateria,
            'idContratoSecundario' => $request->idContrato,
            'fechaInicial'         => $request->fechaInicio,
            'fechaFinal'           => $request->fechaFin,
            'observacion'          => $request->observacion,
            'estado'               => $estado,
        ]);

        if ($compartido->idContratoSecundario) {
            $clon = HorarioMateria::duplicarParaHorarioCompartido($compartido);
            if ($clon) {
                $compartido->update(['idHorarioMateriaSecundario' => $clon->id]);
            }
        }

        $compartido->load('contratoSecundario.persona');

        return $compartido->toAsignacionSesionApi();
    }

    private function eliminarReemplazoClase(AsignacionSesion $reemplazo): void
    {
        $hasAsistencia = SesionMateria::where('idHorarioMateria', $reemplazo->idHorarioMateria)
            ->where('idContrato', $reemplazo->idContrato)
            ->whereBetween('fechaSesion', [$reemplazo->fechaInicio, $reemplazo->fechaFin])
            ->whereHas('asistencia', fn ($q) => $q->where('asistio', true))
            ->exists();

        if ($hasAsistencia) {
            throw new \RuntimeException('No es posible desasignar este reemplazo porque ya tiene asistencias registradas.');
        }

        SesionMateria::where('idHorarioMateria', $reemplazo->idHorarioMateria)
            ->where('idContrato', $reemplazo->idContrato)
            ->whereBetween('fechaSesion', [$reemplazo->fechaInicio, $reemplazo->fechaFin])
            ->each(function ($sesion) {
                $sesion->asistencia()->delete();
                $sesion->delete();
            });

        $reemplazo->delete();
    }

    private function eliminarHorarioCompartido(HorarioCompartido $compartido): void
    {
        $idHorarioSecundario = $compartido->idHorarioMateriaSecundario;
        $horario = $idHorarioSecundario
            ? HorarioMateria::find($idHorarioSecundario)
            : null;

        if ($horario) {
            $hasAsistencia = $horario->sesionMaterias()
                ->whereHas('asistencia', fn ($q) => $q->where('asistio', true))
                ->exists();

            if ($hasAsistencia) {
                throw new \RuntimeException('No es posible desasignar este profesor porque ya tiene asistencias registradas en este horario.');
            }

            $hasActiveRmi = $horario->detallesRmi()->where(function ($q) {
                $q->where('estado', '!=', 'PENDIENTE')
                    ->orWhereNotNull('archivoPago')
                    ->orWhereNotNull('urlInforme');
            })->exists();

            if ($hasActiveRmi) {
                throw new \RuntimeException('No es posible desasignar este profesor porque tiene reportes de RMI activos.');
            }

            $totalEnSlot = HorarioMateria::where('idFicha', $horario->idFicha)
                ->where('idGradoMateria', $horario->idGradoMateria)
                ->where('idDia', $horario->idDia)
                ->where('horaInicial', $horario->horaInicial)
                ->where('horaFinal', $horario->horaFinal)
                ->count();

            if ($totalEnSlot > 1) {
                $horario->sesionMaterias()->each(function ($sesion) {
                    $sesion->asistencia()->delete();
                    $sesion->delete();
                });
                $horario->detallesRmi()->delete();
                $horario->delete();
            }
        }

        $compartido->delete();
    }

    private function enviarEmailReemplazo(AsignacionSesion $asignacion): void
    {
        $contrato = Contract::with('persona')->find($asignacion->idContrato);
        $horario = HorarioMateria::with(['ficha', 'gradoMateria.materia', 'dia'])->find($asignacion->idHorarioMateria);

        if ($contrato && $contrato->persona && $horario) {
            $instructor = $contrato->persona;
            $nombreInstructor = $instructor->nombre1 . ' ' . $instructor->apellido1;
            $nombreMateria = $horario->gradoMateria->materia->nombreMateria ?? 'Materia';
            $codigoFicha = $horario->ficha->codigo ?? 'N/A';
            $diaSemana = $horario->dia->dia ?? 'N/A';

            $correo = $instructor->email;
            $asunto = 'Asignación de Reemplazo: ' . $nombreMateria;

            $mensaje = "Hola $nombreInstructor,\n\n"
                . "Se te ha asignado un reemplazo para la materia $nombreMateria.\n\n"
                . "Detalles del horario:\n"
                . "- Ficha: $codigoFicha\n"
                . "- Día: $diaSemana\n"
                . "- Hora: " . $horario->horaInicial . ' - ' . $horario->horaFinal . "\n"
                . '- Periodo: ' . $asignacion->fechaInicio . ' hasta ' . $asignacion->fechaFin . "\n\n"
                . "Por favor revisa tu horario en la plataforma.\n\n"
                . 'Gracias.';

            SendBasicEmail::dispatch($correo, $asunto, $mensaje);
        }
    }
}
