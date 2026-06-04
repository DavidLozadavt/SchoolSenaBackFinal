<?php

namespace App\Services\Inscripcion;

use App\Enums\EstadoSeguimientoInscripcion;
use App\Http\Controllers\gestion_pago\PagoController;
use App\Models\FormularioRespuesta;
use App\Models\Proceso;
use App\Models\SeguimientoInscripcion;
use App\Util\KeyUtil;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SolicitudesRecibidasService
{
    public function __construct(
        private readonly FormularioInscripcionDatosService $formularioService,
        private readonly PortalConsultaInscripcionService $portalConsultaService,
        private readonly SeguimientoInscripcionService $seguimientoService
    ) {
    }

    public function listar(int $idCompany): array
    {
        $formulario = $this->formularioService->obtenerFormularioInscripcion();
        if (!$formulario) {
            return [];
        }

        $respuestas = FormularioRespuesta::with('formulario')
            ->where('idFormulario', $formulario->id)
            ->orderByDesc('id')
            ->get();

        $items = [];

        foreach ($respuestas as $respuesta) {
            $datos = $this->formularioService->formatearDatosFormulario($respuesta);
            $documento = trim((string) ($datos['estudiante']['documento'] ?? ''));
            if ($documento === '') {
                continue;
            }

            if ($this->portalConsultaService->documentoTieneFacturaAcademica($documento, $idCompany)) {
                continue;
            }

            $seguimiento = SeguimientoInscripcion::where('idFormularioRespuesta', $respuesta->id)
                ->latest('id')
                ->first();

            $estado = $seguimiento?->estado_proceso ?? EstadoSeguimientoInscripcion::SOLICITUD_RECIBIDA->value;
            $nombrePrograma = $this->formularioService->resolverNombreProgramaDesdeRespuesta($respuesta);

            $items[] = [
                'id' => (int) $respuesta->id,
                'idFormularioRespuesta' => (int) $respuesta->id,
                'numeroSolicitud' => 'SOL-FORM-' . $respuesta->id,
                'nombreEstudiante' => $datos['estudiante']['nombreCompleto'] ?? 'Estudiante',
                'tipoDocumento' => $datos['estudiante']['tipoDocumento'] ?? null,
                'documento' => $documento,
                'email' => $datos['estudiante']['email'] ?? null,
                'telefono' => $datos['estudiante']['telefono'] ?? null,
                'nombrePrograma' => $nombrePrograma ?? ($datos['estudiante']['programaInteres'] ?? null),
                'fechaSolicitud' => $respuesta->created_at?->toDateString(),
                'estado' => $estado,
                'estadoEtiqueta' => EstadoSeguimientoInscripcion::tryFrom($estado)?->etiquetaPortal() ?? $estado,
                'observacionAdministrativa' => $seguimiento?->observaciones_admin,
                'idSeguimiento' => $seguimiento?->id,
                'tutor' => $datos['tutor'] ?? null,
                'documentos' => $datos['documentos'] ?? [],
            ];
        }

        return $items;
    }

    public function detalle(int $idFormularioRespuesta, int $idCompany): ?array
    {
        $respuesta = $this->formularioService->buscarRespuestaPorId($idFormularioRespuesta);
        if (!$respuesta) {
            return null;
        }

        $datos = $this->formularioService->formatearDatosFormulario($respuesta);
        $documento = trim((string) ($datos['estudiante']['documento'] ?? ''));

        if ($documento !== '' && $this->portalConsultaService->documentoTieneFacturaAcademica($documento, $idCompany)) {
            return null;
        }

        $seguimiento = SeguimientoInscripcion::where('idFormularioRespuesta', $idFormularioRespuesta)
            ->latest('id')
            ->first();

        if ($seguimiento && !$seguimiento->idFactura) {
            $seguimiento = $this->seguimientoService->marcarEnRevision($seguimiento);
        }

        $tercero = $this->formularioService->sincronizarTerceroDesdeRespuesta($respuesta, $idCompany);
        $estado = $seguimiento?->estado_proceso ?? EstadoSeguimientoInscripcion::SOLICITUD_RECIBIDA->value;
        $idProceso = $this->formularioService->resolverIdProcesoDesdeRespuesta($respuesta);

        return [
            'id' => (int) $respuesta->id,
            'idFormularioRespuesta' => (int) $respuesta->id,
            'numeroSolicitud' => 'SOL-FORM-' . $respuesta->id,
            'fechaSolicitud' => $respuesta->created_at?->toDateString(),
            'estado' => $estado,
            'estadoEtiqueta' => EstadoSeguimientoInscripcion::tryFrom($estado)?->etiquetaPortal() ?? $estado,
            'observacionAdministrativa' => $seguimiento?->observaciones_admin,
            'idTercero' => $tercero?->id,
            'idProceso' => $idProceso ?? $seguimiento?->idProceso,
            'nombrePrograma' => $this->formularioService->resolverNombreProgramaDesdeRespuesta($respuesta),
            'idSeguimiento' => $seguimiento?->id,
            'informacionConfirmada' => (bool) ($seguimiento?->fecha_correo_enviado),
            'datosFormulario' => $datos,
        ];
    }

    public function confirmarInformacion(Request $request, int $idFormularioRespuesta): array
    {
        return $this->generarFactura($request, $idFormularioRespuesta);
    }

    public function generarFactura(Request $request, int $idFormularioRespuesta): array
    {
        $idCompany = (int) ($request->input('idCompany') ?: KeyUtil::idCompany());
        $detalle = $this->detalle($idFormularioRespuesta, $idCompany);

        if (!$detalle) {
            throw new \RuntimeException('Solicitud no encontrada o ya tiene factura académica.');
        }

        $request->validate([
            'idProceso' => ['nullable', 'integer'],
            'correo' => ['required', 'email', 'max:255'],
            'fechaLimitePago' => ['nullable', 'date'],
            'conceptos' => ['nullable', 'array'],
            'observaciones' => ['nullable', 'string', 'max:500'],
        ]);

        $idProceso = (int) ($request->input('idProceso') ?: ($detalle['idProceso'] ?? 0));
        if ($idProceso <= 0) {
            throw new \RuntimeException('Debe indicar el proceso académico para generar la factura.');
        }
        $terceroId = $detalle['idTercero'] ?? null;
        if (!$terceroId) {
            throw new \RuntimeException('No se pudo vincular el tercero del estudiante.');
        }

        $payload = new Request([
            'idProceso' => $idProceso,
            'idCompany' => $idCompany,
            'idTercero' => $terceroId,
            'conceptos' => $request->input('conceptos', []),
        ]);

        /** @var PagoController $pagoController */
        $pagoController = app(PagoController::class);
        $response = $pagoController->generarFacturaValoresEconomicos($payload);
        $status = $response->getStatusCode();
        $body = json_decode($response->getContent(), true);

        if ($status >= 400) {
            throw new \RuntimeException($body['error'] ?? 'No se pudo generar la factura académica.');
        }

        $idFactura = (int) ($body['factura']['id'] ?? $body['id'] ?? 0);
        if ($idFactura <= 0) {
            throw new \RuntimeException('La factura académica no se generó correctamente.');
        }

        $proceso = Proceso::find($idProceso);
        $seguimiento = SeguimientoInscripcion::where('idFormularioRespuesta', $idFormularioRespuesta)
            ->latest('id')
            ->first();

        if (!$seguimiento) {
            $seguimiento = $this->seguimientoService->crearSeguimientoPreFactura(
                $idFormularioRespuesta,
                $idCompany,
                $terceroId,
                EstadoSeguimientoInscripcion::SOLICITUD_RECIBIDA->value
            );
        }

        $seguimiento->idFormularioRespuesta = $idFormularioRespuesta;
        $seguimiento->save();

        $factura = \App\Models\Factura::with(['detalles', 'tercero', 'transacciones.pago'])->find($idFactura);
        if (!$factura) {
            throw new \RuntimeException('Factura generada no encontrada.');
        }

        $seguimiento = $this->seguimientoService->vincularFacturaGenerada(
            $seguimiento,
            $factura,
            $proceso,
            $request->input('correo'),
            $request->input('fechaLimitePago'),
            Auth::id()
        );

        if ($request->filled('observaciones')) {
            $seguimiento->observaciones_admin = $request->input('observaciones');
            $seguimiento->save();
        }

        return [
            'message' => 'Información confirmada, factura académica generada y correo enviado al estudiante.',
            'idFactura' => $idFactura,
            'seguimiento' => $this->seguimientoService->formatearParaAdmin($seguimiento),
            'factura' => $body['factura'] ?? $body,
        ];
    }

    public function rechazar(int $idFormularioRespuesta, string $observacion, ?int $idUser = null): array
    {
        $idCompany = (int) KeyUtil::idCompany();
        $detalle = $this->detalle($idFormularioRespuesta, $idCompany);

        if (!$detalle) {
            throw new \RuntimeException('Solicitud no encontrada.');
        }

        $respuesta = $this->formularioService->buscarRespuestaPorId($idFormularioRespuesta);
        $tercero = $this->formularioService->sincronizarTerceroDesdeRespuesta($respuesta, $idCompany);

        $seguimiento = $this->seguimientoService->crearSeguimientoPreFactura(
            $idFormularioRespuesta,
            $idCompany,
            $tercero?->id,
            EstadoSeguimientoInscripcion::RECHAZADA->value,
            $observacion,
            $idUser
        );

        return [
            'message' => 'Solicitud rechazada.',
            'seguimiento' => $this->seguimientoService->formatearParaAdmin($seguimiento),
        ];
    }

    public function solicitarCorreccion(int $idFormularioRespuesta, string $observacion, ?int $idUser = null): array
    {
        $idCompany = (int) KeyUtil::idCompany();
        $detalle = $this->detalle($idFormularioRespuesta, $idCompany);

        if (!$detalle) {
            throw new \RuntimeException('Solicitud no encontrada.');
        }

        $respuesta = $this->formularioService->buscarRespuestaPorId($idFormularioRespuesta);
        $tercero = $this->formularioService->sincronizarTerceroDesdeRespuesta($respuesta, $idCompany);

        $seguimiento = $this->seguimientoService->crearSeguimientoPreFactura(
            $idFormularioRespuesta,
            $idCompany,
            $tercero?->id,
            EstadoSeguimientoInscripcion::CORRECCION_SOLICITADA->value,
            $observacion,
            $idUser
        );

        return [
            'message' => 'Se solicitó corrección al aspirante.',
            'seguimiento' => $this->seguimientoService->formatearParaAdmin($seguimiento),
        ];
    }
}
