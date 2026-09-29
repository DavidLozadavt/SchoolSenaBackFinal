<?php

namespace App\Http\Controllers;

use App\Mail\MailService;
use App\Models\FormularioRespuesta;
use App\Models\SeguimientoAspirante;
use App\Models\SeguimientoRevisionHistorial;
use App\Models\TelecomConfig;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * Panel administrativo "Solicitudes de Inscripción" — Fase 5/6 del seguimiento
 * de aspirantes. Es una vista/acción distinta del listado de Seguimiento de
 * Aspirantes: no crea tablas nuevas de negocio, solo lee/actualiza
 * `seguimientoAspirantes` (estadoDocumental) y `seguimiento_revision_historial`.
 */
class SolicitudInscripcionController extends Controller
{
    /** Solo aparecen aquí aspirantes que ya entraron al flujo documental. */
    private const ESTADOS_VISIBLES = [
        'formulario_iniciado',
        'formulario_enviado',
        'documentacion_completa',
        'documentacion_incompleta',
        'pendiente_revision',
        'aprobado',
        'rechazado',
        'correccion_solicitada',
    ];

    public function index(Request $request)
    {
        // Cada usuario ve solo las solicitudes de los aspirantes que importó.
        $query = SeguimientoAspirante::visibles()
            ->whereIn('estadoDocumental', self::ESTADOS_VISIBLES);

        if ($request->filled('estadoDocumental')) {
            $query->where('estadoDocumental', $request->input('estadoDocumental'));
        }
        if ($request->filled('programa')) {
            $query->where('programa', $request->input('programa'));
        }
        if ($request->filled('ficha')) {
            $query->where('ficha', $request->input('ficha'));
        }
        if ($request->filled('centro_formacion')) {
            $query->where('centro_formacion', $request->input('centro_formacion'));
        }

        return response()->json(
            $query->orderByDesc('fechaFormularioEnviado')->paginate($request->input('per_page', 20))
        );
    }

    public function show(int $id)
    {
        $aspirante = SeguimientoAspirante::visibles()->findOrFail($id);

        $config = TelecomConfig::activa();
        $respuesta = null;
        if ($config?->idFormularioInscripcion) {
            $respuesta = FormularioRespuesta::with('formulario.preguntas')
                ->where('idFormulario', $config->idFormularioInscripcion)
                ->where('idAspirante', $aspirante->id)
                ->first();
        }

        $historial = SeguimientoRevisionHistorial::with('usuarioRevisor')
            ->where('idAspirante', $aspirante->id)
            ->orderBy('fecha')
            ->get();

        return response()->json([
            'aspirante' => $aspirante,
            'respuesta' => $respuesta,
            'historial' => $historial,
        ]);
    }

    public function aprobar(int $id)
    {
        $aspirante = SeguimientoAspirante::visibles()->findOrFail($id);
        $usuario = Auth::user() ?: Auth::guard('api')->user();

        DB::transaction(function () use ($aspirante, $usuario) {
            $aspirante->estadoDocumental = 'aprobado';
            $aspirante->save();

            SeguimientoRevisionHistorial::create([
                'idAspirante' => $aspirante->id,
                'accion' => 'aprobado',
                'motivo' => null,
                'idUsuarioRevisor' => $usuario?->id,
                'fecha' => now(),
            ]);
        });

        $this->enviarCorreo($aspirante, aprobado: true);

        return response()->json(['success' => true, 'estadoDocumental' => 'aprobado']);
    }

    public function rechazar(Request $request, int $id)
    {
        $request->validate(['motivo' => 'required|string|min:5']);

        $aspirante = SeguimientoAspirante::visibles()->findOrFail($id);
        $usuario = Auth::user() ?: Auth::guard('api')->user();
        $motivo = $request->input('motivo');

        DB::transaction(function () use ($aspirante, $usuario, $motivo) {
            $aspirante->estadoDocumental = 'rechazado';
            $aspirante->save();

            SeguimientoRevisionHistorial::create([
                'idAspirante' => $aspirante->id,
                'accion' => 'rechazado',
                'motivo' => $motivo,
                'idUsuarioRevisor' => $usuario?->id,
                'fecha' => now(),
            ]);
        });

        $this->enviarCorreo($aspirante, aprobado: false, motivo: $motivo);

        return response()->json(['success' => true, 'estadoDocumental' => 'rechazado']);
    }

    private function enviarCorreo(SeguimientoAspirante $aspirante, bool $aprobado, ?string $motivo = null): void
    {
        if (!$aspirante->correo) {
            return;
        }

        $nombre = trim($aspirante->nombre . ' ' . $aspirante->apellido);
        $link = rtrim(config('app.frontend_url'), '/') . '/formulario-aspirante/' . $aspirante->tokenPublico;

        // La vista email-contratacion imprime $messageContent escapado con
        // white-space:pre-line (evita XSS en Mailables compartidos con otros
        // módulos) — texto plano con saltos de línea, nada de HTML aquí.
        if ($aprobado) {
            $subject = 'Inscripción Aprobada';
            $mensaje = "Hola {$nombre}.\n\n"
                . "Tu documentación fue revisada.\n"
                . "Tu inscripción fue aprobada exitosamente.\n\n"
                . "Próximamente recibirás la información correspondiente.\n\n"
                . "Gracias por elegir el SENA.";
        } else {
            $subject = 'Revisión de tu inscripción';
            $mensaje = "Hola {$nombre}.\n\n"
                . "Tu documentación fue revisada.\n"
                . "No fue posible aprobar tu inscripción.\n\n"
                . "Motivo: {$motivo}\n\n"
                . "Puedes corregir la información ingresando nuevamente al siguiente enlace:\n"
                . "{$link}\n\n"
                . "Gracias.";
        }

        Mail::to($aspirante->correo)->send(new MailService($subject, $mensaje, 'SENA - Seguimiento de Aspirantes'));
    }
}
