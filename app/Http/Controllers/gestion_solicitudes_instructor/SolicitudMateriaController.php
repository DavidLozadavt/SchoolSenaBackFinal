<?php

namespace App\Http\Controllers\gestion_solicitudes_instructor;

use App\Http\Controllers\Controller;
use App\Http\Controllers\gestion_materias\MateriaController;
use App\Models\SolicitudMateria;
use App\Models\Materia;
use App\Models\Contract;
use App\Models\Ficha;
use App\Util\KeyUtil;
use App\Models\Notificacion;
use App\Models\User;
use App\Models\Status;
use App\Mail\MailService;
use App\Models\Programa;
use Illuminate\Support\Facades\Mail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class SolicitudMateriaController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $idCompany = KeyUtil::idCompany();
        $user = KeyUtil::user();

        $query = SolicitudMateria::with([
            'materia.categoriaFormacion',
            'ficha.asignacion.programa',
            'contrato.persona',
            'solicitante.persona'
        ]);

        // Si se quiere filtrar por el usuario logueado
        if ($request->has('mine')) {
            $contract = KeyUtil::lastContractActive();
            $query->where('idSolicitante', $contract->id);
        } else {
            // Por defecto listamos todas las solictudes de la empresa
            $query->where('idCompany', $idCompany);
        }

        $solicitudes = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'message' => 'Solicitudes obtenidas correctamente',
            'data' => $solicitudes
        ]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $idCompany = KeyUtil::idCompany();
        $contrato = KeyUtil::lastContractActive();

        $validated = $request->validate([
            'idFicha' => 'required|exists:ficha,id',
            'observacion' => 'nullable|string',
        ]);

        try {
            DB::beginTransaction();

            $solicitud = new SolicitudMateria();
            $solicitud->idFicha = $validated['idFicha'];
            $solicitud->estado = 'PENDIENTE';
            $solicitud->fechaInicio = $request->input('fechaInicio');
            $solicitud->idCompany = $idCompany;
            $solicitud->idSolicitante = $contrato->id;

            $solicitud->save();

            DB::commit();

            return response()->json([
                'message' => 'Solicitud creada correctamente',
                'data' => $solicitud
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al crear la solicitud',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Aceptar solicitud y asignar instructor.
     */
    public function aceptar(Request $request, $id)
    {
        $validated = $request->validate([
            'idContrato' => 'required|exists:contrato,id',
            'idMateria' => 'required|exists:materia,id',
            'fechaInicio' => 'nullable|date',
            'fechaFin' => 'nullable|date',
            'observacion' => 'nullable|string'
        ]);

        try {
            DB::beginTransaction();

            $solicitud = SolicitudMateria::findOrFail($id);

            if ($solicitud->estado !== 'PENDIENTE') {
                return response()->json(['message' => 'La solicitud ya ha sido procesada'], 400);
            }

            $solicitud->idContrato = $validated['idContrato'];
            $solicitud->estado = 'ACEPTADO';
            $solicitud->idMateria = $validated['idMateria'];
            if (isset($validated['fechaInicio'])) {
                $solicitud->fechaInicio = $validated['fechaInicio'];
            }
            if (isset($validated['fechaFin'])) {
                $solicitud->fechaFin = $validated['fechaFin'];
            }
            if (isset($validated['observacion'])) {
                $solicitud->observacion = $validated['observacion'];
            }
            $solicitud->save();

            // Enviar correo y notificación al solicitante y asignado
            $this->notificarInstructor($solicitud, 'ACEPTADO');

            DB::commit();

            return response()->json([
                'message' => 'Solicitud aceptada y docente asignado',
                'data' => $solicitud
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al aceptar la solicitud',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Rechazar solicitud.
     */
    public function rechazar(Request $request, $id)
    {
        $validated = $request->validate([
            'observacion' => 'required|string'
        ]);

        try {
            DB::beginTransaction();

            $solicitud = SolicitudMateria::findOrFail($id);

            if ($solicitud->estado !== 'PENDIENTE') {
                return response()->json(['message' => 'La solicitud ya ha sido procesada'], 400);
            }

            $solicitud->estado = 'RECHAZADO';
            $solicitud->observacion = $validated['observacion'];
            $solicitud->save();

            // Enviar correo y notificación al instructor líder
            $this->notificarInstructor($solicitud, 'RECHAZADA');

            DB::commit();

            return response()->json([
                'message' => 'Solicitud rechazada',
                'data' => $solicitud
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al rechazar la solicitud',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Notificar al solicitante y/o al instructor asignado sobre el cambio de estado de la solicitud.
     */
    private function notificarInstructor($solicitud, $estado)
    {
        try {
            $solicitud->load(['ficha', 'materia', 'solicitante.persona', 'contrato.persona']);
            $ficha = $solicitud->ficha;

            if (!$ficha) {
                return;
            }

            $materiaNombre = $solicitud->materia->nombreMateria ?? 'una materia';
            
            // Solicitante
            $solicitantePersona = $solicitud->solicitante->persona ?? null;
            
            // Asignado (si aplica)
            $asignadoPersona = $solicitud->contrato->persona ?? null;

            if ($estado === 'ACEPTADA' || $estado === 'ACEPTADO') {
                // Notificar al solicitante
                if ($solicitantePersona) {
                    $nombreAsignado = $asignadoPersona ? ($asignadoPersona->nombre1 . ' ' . $asignadoPersona->apellido1) : 'un instructor';
                    $asunto = "Solicitud Aceptada - Ficha {$ficha->codigo}";
                    $mensaje = "Tu solicitud para la materia $materiaNombre en la ficha {$ficha->codigo} ha sido ACEPTADA. Se ha asignado al instructor $nombreAsignado.";
                    if ($solicitud->observacion) {
                        $mensaje .= "\n\nObservación: " . $solicitud->observacion;
                    }
                    $this->enviarNotificacion($solicitantePersona, $solicitud, $asunto, $mensaje);
                }

                // Notificar al asignado
                if ($asignadoPersona) {
                    $asunto = "Nueva Asignación - Ficha {$ficha->codigo}";
                    $mensaje = "Has sido asignado para impartir la materia $materiaNombre en la ficha {$ficha->codigo}.";
                    if ($solicitud->observacion) {
                        $mensaje .= "\n\nObservación: " . $solicitud->observacion;
                    }
                    $this->enviarNotificacion($asignadoPersona, $solicitud, $asunto, $mensaje);
                }
            } else {
                // Notificar solo al solicitante si fue rechazada
                if ($solicitantePersona) {
                    $asunto = "Solicitud Rechazada - Ficha {$ficha->codigo}";
                    $mensaje = "Tu solicitud para la materia $materiaNombre en la ficha {$ficha->codigo} ha sido RECHAZADA.";
                    if ($solicitud->observacion) {
                        $mensaje .= "\n\nObservación: " . $solicitud->observacion;
                    }
                    $this->enviarNotificacion($solicitantePersona, $solicitud, $asunto, $mensaje);
                }
            }

        } catch (\Exception $e) {
            Log::error("Error al notificar instructores: " . $e->getMessage());
        }
    }

    /**
     * Enviar correo y guardar registro de notificación en base de datos.
     */
    private function enviarNotificacion($persona, $solicitud, $asunto, $mensaje)
    {
        if (!$persona) {
            return;
        }

        // Enviar Correo
        if (!empty($persona->email)) {
            try {
                Mail::to($persona->email)->send(new MailService($asunto, $mensaje));
            } catch (\Exception $e) {
                Log::error("Error al enviar correo a {$persona->email}: " . $e->getMessage());
            }
        }

        // Crear Notificación en BD
        try {
            $notification = new Notificacion();
            $notification->estado_id = Status::ID_ACTIVE;
            $notification->asunto = $asunto;
            $notification->mensaje = $mensaje;
            $notification->idUsuarioReceptor = $persona->id;
            $notification->idUsuarioRemitente = auth()->user() ? (auth()->user()->idpersona ?? $persona->id) : $persona->id;
            $notification->idEmpresa = $solicitud->idCompany ?? KeyUtil::idCompany();
            $notification->idTipoNotificacion = 1;
            $notification->fecha = Carbon::now()->toDateString();
            $notification->hora = Carbon::now()->toTimeString();
            $notification->save();
        } catch (\Exception $e) {
            Log::error("Error al guardar notificación para persona ID {$persona->id}: " . $e->getMessage());
        }
    }
}
