<?php

namespace App\Http\Controllers;

use App\Models\Formulario;
use App\Models\FormularioRespuesta;
use App\Models\SeguimientoAspirante;
use App\Models\SeguimientoRevisionHistorial;
use App\Models\TelecomConfig;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * Flujo público de inscripción del aspirante (Fase 3/4 del seguimiento de
 * aspirantes). Identifica al aspirante únicamente por `tokenPublico`, nunca
 * por login. Reutiliza el módulo de Formularios existente: el formulario de
 * documentos se asigna una sola vez desde TelecomConfig.idFormularioInscripcion.
 */
class AspiranteInscripcionController extends Controller
{
    private function resolverAspirante(string $token): SeguimientoAspirante
    {
        return SeguimientoAspirante::where('tokenPublico', $token)->firstOrFail();
    }

    private function resolverFormulario(): Formulario
    {
        $config = TelecomConfig::activa();
        abort_if(!$config || !$config->idFormularioInscripcion, 404, 'No hay un formulario de inscripción configurado.');

        return Formulario::with('preguntas.opciones')->findOrFail($config->idFormularioInscripcion);
    }

    /**
     * Estado de disponibilidad del formulario (borrador/pausado/fechas/límite).
     * El flujo de aspirantes no tiene concepto de "usuario autenticado" que
     * pueda saltarse restricciones (a diferencia del formulario genérico
     * público): cualquier bloqueo aplica siempre.
     */
    private function evaluarDisponibilidad(Formulario $formulario): ?string
    {
        $now = Carbon::now();

        if ($formulario->estado === 'borrador') {
            return 'borrador';
        }
        if ($formulario->estado === 'pausado') {
            return 'pausado';
        }
        if ($formulario->fechaInicio && $now->lt(Carbon::parse($formulario->fechaInicio))) {
            return 'no_iniciado';
        }
        if ($formulario->fechaLimite && $now->gt(Carbon::parse($formulario->fechaLimite))) {
            return 'expirado';
        }
        if ($formulario->limiteRespuestas && $formulario->limiteRespuestas > 0) {
            $total = FormularioRespuesta::where('idFormulario', $formulario->id)->count();
            if ($total >= $formulario->limiteRespuestas) {
                return 'limite_alcanzado';
            }
        }

        return null;
    }

    /**
     * GET /api/inscripcion-aspirante/{token}
     */
    public function show(string $token)
    {
        $aspirante = $this->resolverAspirante($token);
        $formulario = $this->resolverFormulario();
        $motivoNoDisponible = $this->evaluarDisponibilidad($formulario);

        if (!$motivoNoDisponible && in_array($aspirante->estadoDocumental, [null, 'link_enviado'], true)) {
            $aspirante->estadoDocumental = 'formulario_iniciado';
            $aspirante->save();
        }

        $respuesta = FormularioRespuesta::where('idFormulario', $formulario->id)
            ->where('idAspirante', $aspirante->id)
            ->first();

        return response()->json([
            'aspirante' => [
                'nombre' => $aspirante->nombre,
                'apellido' => $aspirante->apellido,
                'celular' => $aspirante->celular,
                'correo' => $aspirante->correo,
                'centroFormacion' => $aspirante->centro_formacion,
                'programa' => $aspirante->programa,
                'ficha' => $aspirante->ficha,
                'estadoDocumental' => $aspirante->estadoDocumental,
            ],
            'formulario' => $formulario,
            'respuestaPrevia' => $respuesta?->respuestas,
            'motivoNoDisponible' => $motivoNoDisponible,
        ]);
    }

    /**
     * POST /api/inscripcion-aspirante/{token}/responder
     * Reemplaza (no duplica) la respuesta existente del aspirante y valida
     * automáticamente si la documentación obligatoria quedó completa.
     */
    public function responder(Request $request, string $token)
    {
        $aspirante = $this->resolverAspirante($token);
        $formulario = $this->resolverFormulario();

        if ($motivo = $this->evaluarDisponibilidad($formulario)) {
            return response()->json(['error' => 'El formulario no está disponible.', 'motivo' => $motivo], 403);
        }

        $request->validate([
            'respuestas' => 'required|array|min:1',
        ]);

        $respuestas = $request->input('respuestas', []);
        $eraCorreccion = in_array($aspirante->estadoDocumental, ['rechazado', 'correccion_solicitada'], true);

        DB::transaction(function () use ($aspirante, $formulario, $respuestas, $eraCorreccion) {
            FormularioRespuesta::updateOrCreate(
                ['idFormulario' => $formulario->id, 'idAspirante' => $aspirante->id],
                ['respuestas' => $respuestas, 'ipAddress' => request()->ip()]
            );

            $faltantes = $this->calcularFaltantes($formulario, $respuestas);
            $completa = empty($faltantes);

            $aspirante->fechaFormularioEnviado = now();
            $aspirante->estadoDocumental = $completa ? 'pendiente_revision' : 'documentacion_incompleta';
            $aspirante->save();

            SeguimientoRevisionHistorial::create([
                'idAspirante' => $aspirante->id,
                'accion' => $eraCorreccion ? 'reenviado' : 'formulario_enviado',
                'motivo' => null,
                'idUsuarioRevisor' => null,
                'fecha' => now(),
            ]);

            SeguimientoRevisionHistorial::create([
                'idAspirante' => $aspirante->id,
                'accion' => $completa ? 'documentacion_completa' : 'documentacion_incompleta',
                'motivo' => $completa ? null : ('Faltan: ' . implode(', ', $faltantes)),
                'idUsuarioRevisor' => null,
                'fecha' => now(),
            ]);
        });

        return response()->json([
            'success' => true,
            'estadoDocumental' => $aspirante->fresh()->estadoDocumental,
        ]);
    }

    /**
     * Compara las preguntas tipo 'archivo' marcadas obligatorias contra las
     * respuestas enviadas. Devuelve los títulos de las que falten.
     */
    private function calcularFaltantes(Formulario $formulario, array $respuestas): array
    {
        $respondidas = collect($respuestas)
            ->filter(fn ($r) => isset($r['valor']) && trim((string) $r['valor']) !== '')
            ->pluck('idPregunta')
            ->map(fn ($id) => (int) $id)
            ->all();

        return $formulario->preguntas
            ->where('tipo', 'archivo')
            ->where('esObligatoria', true)
            ->reject(fn ($pregunta) => in_array($pregunta->id, $respondidas, true))
            ->pluck('titulo')
            ->values()
            ->all();
    }
}
