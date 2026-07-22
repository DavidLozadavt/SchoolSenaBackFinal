<?php

namespace App\Http\Controllers\gestion_horarios;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\AsignacionSesion;
use App\Models\HorarioMateria;
use App\Models\SesionMateria;
use App\Models\Contract;
use App\Jobs\SendBasicEmail;
use App\Services\TrimestreActualFichaService;

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

        $bloqueo = TrimestreActualFichaService::abortSiHorarioNoActual((int) $request->idHorarioMateria);
        if ($bloqueo) {
            return $bloqueo;
        }

        DB::beginTransaction();

        try {
            $asignacion = AsignacionSesion::create([
                'idHorarioMateria' => $request->idHorarioMateria,
                'tipoAsignacion'   => $request->tipoAsignacion,
                'fechaInicio'      => $request->fechaInicio,
                'fechaFin'         => $request->fechaFin,
                'idContrato'       => $request->idContrato,
                'observacion'      => $request->observacion,
            ]);

            if (
                $request->tipoAsignacion === 'HORARIO COMPARTIDO'
                && $asignacion->idContrato
            ) {
                HorarioMateria::duplicarParaAsignacionCompartida($asignacion);
            }

            if ($request->tipoAsignacion === 'REEMPLAZO' && $asignacion->idContrato) {
                try {
                    $this->enviarEmailReemplazo($asignacion);
                } catch (\Exception $e) {
                    // El reemplazo se creó aunque falle el correo.
                }
            }

            $asignacion->load('contrato.persona');

            DB::commit();

            return response()->json([
                'message' => 'Asignación creada correctamente',
                'data'    => $asignacion->toAsignacionSesionApi(),
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

            $query = AsignacionSesion::whereIn('idHorarioMateria', $horarioIds);
            if ($idContrato) {
                $query->where('idContrato', $idContrato);
            }
            $asignaciones = $query->get();

            if ($asignaciones->isEmpty()) {
                DB::rollBack();

                return response()->json([
                    'message' => 'No se encontraron asignaciones para desasignar',
                ], 404);
            }

            foreach ($asignaciones as $asignacion) {
                if ($asignacion->tipoAsignacion === 'HORARIO COMPARTIDO') {
                    $this->eliminarHorarioCompartido($asignacion);
                } else {
                    $this->eliminarReemplazoClase($asignacion);
                }
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

    private function eliminarReemplazoClase(AsignacionSesion $asignacion): void
    {
        $hasAsistencia = SesionMateria::where('idHorarioMateria', $asignacion->idHorarioMateria)
            ->where('idContrato', $asignacion->idContrato)
            ->whereBetween('fechaSesion', [$asignacion->fechaInicio, $asignacion->fechaFin])
            ->whereHas('asistencia', fn ($q) => $q->where('asistio', true))
            ->exists();

        if ($hasAsistencia) {
            throw new \RuntimeException('No es posible desasignar este reemplazo porque ya tiene asistencias registradas.');
        }

        SesionMateria::where('idHorarioMateria', $asignacion->idHorarioMateria)
            ->where('idContrato', $asignacion->idContrato)
            ->whereBetween('fechaSesion', [$asignacion->fechaInicio, $asignacion->fechaFin])
            ->each(function ($sesion) {
                $sesion->asistencia()->delete();
                $sesion->delete();
            });

        $asignacion->delete();
    }

    private function eliminarHorarioCompartido(AsignacionSesion $asignacion): void
    {
        $horario = HorarioMateria::find($asignacion->idHorarioMateria);
        if (!$horario || !$asignacion->idContrato) {
            $asignacion->delete();

            return;
        }

        $clon = HorarioMateria::where('idFicha', $horario->idFicha)
            ->where('idGradoMateria', $horario->idGradoMateria)
            ->where('idDia', $horario->idDia)
            ->where('horaInicial', $horario->horaInicial)
            ->where('horaFinal', $horario->horaFinal)
            ->where('idContrato', $asignacion->idContrato)
            ->where('id', '!=', $horario->id)
            ->first();

        if ($clon) {
            $hasAsistencia = $clon->sesionMaterias()
                ->whereHas('asistencia', fn ($q) => $q->where('asistio', true))
                ->exists();

            if ($hasAsistencia) {
                throw new \RuntimeException('No es posible desasignar este profesor porque ya tiene asistencias registradas en este horario.');
            }

            $hasActiveRmi = $clon->detallesRmi()->where(function ($q) {
                $q->where('estado', '!=', 'PENDIENTE')
                    ->orWhereNotNull('archivoPago')
                    ->orWhereNotNull('urlInforme');
            })->exists();

            if ($hasActiveRmi) {
                throw new \RuntimeException('No es posible desasignar este profesor porque tiene reportes de RMI activos.');
            }

            $clon->sesionMaterias()->each(function ($sesion) {
                $sesion->asistencia()->delete();
                $sesion->delete();
            });
            $clon->detallesRmi()->delete();
            $clon->delete();
        }

        $asignacion->delete();
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
