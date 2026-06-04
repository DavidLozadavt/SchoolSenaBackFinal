<?php

namespace App\Services\Inscripcion;

use App\Enums\EstadoSeguimientoInscripcion;
use App\Mail\MailService;
use App\Models\ConfiguracionPago;
use App\Models\Factura;
use App\Models\Matricula;
use App\Models\Person;
use App\Models\Proceso;
use App\Models\SeguimientoInscripcion;
use App\Models\Status;
use App\Models\Tercero;
use App\Models\FormularioRespuesta;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class SeguimientoInscripcionService
{
    public function __construct(
        private readonly FacturaAcademicaPagoService $pagoService
    ) {
    }

    public function obtenerPorFactura(int $idFactura): ?SeguimientoInscripcion
    {
        return SeguimientoInscripcion::where('idFactura', $idFactura)->latest('id')->first();
    }

    public function obtenerPorToken(string $token): ?SeguimientoInscripcion
    {
        return SeguimientoInscripcion::with([
            'factura.detalles',
            'factura.tercero',
            'factura.transacciones.pago.estado',
            'matricula.ficha.jornada',
            'matricula.ficha.asignacion',
            'proceso',
            'comprobantes',
        ])->where('token', $token)->first();
    }

    public function buscarPorIdentificacion(string $identificacion, int $idCompany): ?SeguimientoInscripcion
    {
        $persona = Person::where('identificacion', $identificacion)->first();
        $tercero = Tercero::where('identificacion', $identificacion)
            ->where(function ($q) use ($idCompany) {
                $q->where('idCompany', $idCompany)->orWhereNull('idCompany');
            })
            ->first();

        $query = SeguimientoInscripcion::with([
            'factura.detalles',
            'factura.tercero',
            'factura.transacciones.pago.estado',
            'matricula.ficha.jornada',
            'proceso',
        ])->where('idCompany', $idCompany);

        if ($persona) {
            $query->where(function ($q) use ($persona, $tercero) {
                $q->where('idPersona', $persona->id);
                if ($tercero) {
                    $q->orWhere('idTercero', $tercero->id);
                }
            });
        } elseif ($tercero) {
            $query->where('idTercero', $tercero->id);
        } else {
            return null;
        }

        return $query->latest('id')->first();
    }

    public function registrarSolicitudDesdeFormulario(
        FormularioRespuesta $respuesta,
        int $idCompany,
        ?Tercero $tercero = null
    ): SeguimientoInscripcion {
        $existente = SeguimientoInscripcion::where('idFormularioRespuesta', $respuesta->id)
            ->whereNull('idFactura')
            ->latest('id')
            ->first();

        if ($existente) {
            if ($existente->estado_proceso === EstadoSeguimientoInscripcion::RECHAZADA->value) {
                return $existente;
            }

            if ($tercero && !$existente->idTercero) {
                $existente->idTercero = $tercero->id;
                $existente->save();
            }

            return $existente;
        }

        if (SeguimientoInscripcion::where('idFormularioRespuesta', $respuesta->id)->whereNotNull('idFactura')->exists()) {
            return SeguimientoInscripcion::where('idFormularioRespuesta', $respuesta->id)->latest('id')->first();
        }

        /** @var FormularioInscripcionDatosService $formService */
        $formService = app(FormularioInscripcionDatosService::class);
        $idProceso = $formService->resolverIdProcesoDesdeRespuesta($respuesta);

        return SeguimientoInscripcion::create([
            'token' => null,
            'idFactura' => null,
            'idFormularioRespuesta' => $respuesta->id,
            'idTercero' => $tercero?->id,
            'idProceso' => $idProceso,
            'idCompany' => $idCompany,
            'estado_proceso' => EstadoSeguimientoInscripcion::SOLICITUD_RECIBIDA->value,
        ]);
    }

    public function marcarEnRevision(SeguimientoInscripcion $seguimiento): SeguimientoInscripcion
    {
        if ($seguimiento->idFactura || $seguimiento->estado_proceso === EstadoSeguimientoInscripcion::RECHAZADA->value) {
            return $seguimiento;
        }

        if ($seguimiento->estado_proceso === EstadoSeguimientoInscripcion::SOLICITUD_RECIBIDA->value) {
            $seguimiento->estado_proceso = EstadoSeguimientoInscripcion::EN_REVISION->value;
            $seguimiento->save();
        }

        return $seguimiento->fresh();
    }

    public function crearOActualizarBorrador(
        Factura $factura,
        ?Proceso $proceso,
        int $idCompany,
        ?Matricula $matricula = null,
        ?Person $persona = null
    ): SeguimientoInscripcion {
        $existente = $this->obtenerPorFactura((int) $factura->id);

        if ($existente) {
            return $existente;
        }

        return SeguimientoInscripcion::create([
            'token' => $this->generarToken(),
            'idFactura' => $factura->id,
            'idMatricula' => $matricula?->id,
            'idPersona' => $persona?->id ?? $matricula?->idPersona,
            'idTercero' => $factura->idTercero,
            'idProceso' => $proceso?->id,
            'idCompany' => $idCompany,
            'estado_proceso' => EstadoSeguimientoInscripcion::BORRADOR->value,
            'fecha_limite_pago' => Carbon::today()->addDays(15),
        ]);
    }

    public function confirmarInformacionYEnviarCorreo(
        SeguimientoInscripcion $seguimiento,
        string $correoDestino,
        ?int $idUser = null,
        ?string $fechaLimite = null,
        ?string $observaciones = null
    ): SeguimientoInscripcion {
        if ($fechaLimite) {
            $seguimiento->fecha_limite_pago = Carbon::parse($fechaLimite);
        }

        $seguimiento->correo_destino = $correoDestino;
        $seguimiento->observaciones_admin = $observaciones;
        $seguimiento->created_by = $idUser;
        $seguimiento->estado_proceso = $this->pagoService->facturaEstaPagada($seguimiento->factura)
            ? EstadoSeguimientoInscripcion::PAGO_APROBADO->value
            : EstadoSeguimientoInscripcion::FACTURA_GENERADA->value;
        $seguimiento->fecha_correo_enviado = Carbon::now();
        $seguimiento->save();

        $this->enviarCorreoAceptacion($seguimiento);

        return $seguimiento->fresh();
    }

    public function sincronizarEstadoDesdeFactura(SeguimientoInscripcion $seguimiento): SeguimientoInscripcion
    {
        if (!$seguimiento->idFactura || !$seguimiento->factura) {
            return $seguimiento;
        }

        $seguimiento->loadMissing(['factura.transacciones.pago', 'comprobantes']);

        if ($seguimiento->estado_proceso === EstadoSeguimientoInscripcion::INSCRIPCION_APROBADA->value) {
            return $seguimiento;
        }

        if ($seguimiento->estado_proceso === EstadoSeguimientoInscripcion::RECHAZADA->value) {
            return $seguimiento;
        }

        if ($seguimiento->estado_proceso === EstadoSeguimientoInscripcion::CORRECCION_SOLICITADA->value) {
            return $seguimiento;
        }

        $comprobantePendiente = $seguimiento->comprobantes
            ->where('estado', 'PENDIENTE_REVISION')
            ->isNotEmpty();

        if ($comprobantePendiente) {
            $seguimiento->estado_proceso = EstadoSeguimientoInscripcion::PAGO_EN_REVISION->value;
        } elseif ($this->pagoService->facturaEstaPagada($seguimiento->factura)) {
            $seguimiento->estado_proceso = EstadoSeguimientoInscripcion::PAGO_APROBADO->value;
        } elseif ($seguimiento->fecha_correo_enviado) {
            if (!in_array($seguimiento->estado_proceso, [
                EstadoSeguimientoInscripcion::FACTURA_GENERADA->value,
                EstadoSeguimientoInscripcion::ACEPTADA->value,
            ], true)) {
                $seguimiento->estado_proceso = EstadoSeguimientoInscripcion::PENDIENTE_PAGO->value;
            }
        }

        if ($seguimiento->exists) {
            $seguimiento->save();
        }

        return $seguimiento;
    }

    public function vincularFacturaGenerada(
        SeguimientoInscripcion $seguimiento,
        Factura $factura,
        ?Proceso $proceso,
        string $correoDestino,
        ?string $fechaLimite = null,
        ?int $idUser = null
    ): SeguimientoInscripcion {
        if (empty($seguimiento->token)) {
            $seguimiento->token = $this->generarToken();
        }

        $seguimiento->idFactura = $factura->id;
        $seguimiento->idTercero = $factura->idTercero ?? $seguimiento->idTercero;
        $seguimiento->idProceso = $proceso?->id ?? $seguimiento->idProceso;
        $seguimiento->correo_destino = $correoDestino;
        $seguimiento->fecha_limite_pago = $fechaLimite
            ? Carbon::parse($fechaLimite)
            : ($seguimiento->fecha_limite_pago ?? Carbon::today()->addDays(15));
        $seguimiento->observaciones_admin = null;
        $seguimiento->created_by = $idUser;
        $seguimiento->estado_proceso = $this->pagoService->facturaEstaPagada($factura)
            ? EstadoSeguimientoInscripcion::PAGO_APROBADO->value
            : EstadoSeguimientoInscripcion::FACTURA_GENERADA->value;
        $seguimiento->fecha_correo_enviado = Carbon::now();
        $seguimiento->save();

        $seguimiento->load([
            'factura.detalles',
            'factura.tercero',
            'factura.transacciones.pago.estado',
            'proceso',
        ]);

        $this->enviarCorreoAceptacion($seguimiento);

        return $seguimiento->fresh();
    }

    public function crearSeguimientoPreFactura(
        int $idFormularioRespuesta,
        int $idCompany,
        ?int $idTercero,
        string $estado,
        ?string $observaciones = null,
        ?int $idUser = null
    ): SeguimientoInscripcion {
        $existente = SeguimientoInscripcion::where('idFormularioRespuesta', $idFormularioRespuesta)
            ->whereNull('idFactura')
            ->latest('id')
            ->first();

        if ($existente) {
            $existente->estado_proceso = $estado;
            $existente->observaciones_admin = $observaciones;
            $existente->created_by = $idUser;
            $existente->save();

            return $existente->fresh();
        }

        return SeguimientoInscripcion::create([
            'token' => null,
            'idFactura' => null,
            'idFormularioRespuesta' => $idFormularioRespuesta,
            'idTercero' => $idTercero,
            'idCompany' => $idCompany,
            'estado_proceso' => $estado,
            'observaciones_admin' => $observaciones,
            'created_by' => $idUser,
        ]);
    }

    public function marcarInscripcionAprobada(SeguimientoInscripcion $seguimiento): SeguimientoInscripcion
    {
        $seguimiento->estado_proceso = EstadoSeguimientoInscripcion::INSCRIPCION_APROBADA->value;
        $seguimiento->save();

        return $seguimiento;
    }

    public function formatearParaAdmin(?SeguimientoInscripcion $seguimiento): ?array
    {
        if (!$seguimiento) {
            return null;
        }

        $seguimiento = $this->sincronizarEstadoDesdeFactura($seguimiento);
        $info = $this->resolverInfoAcademicaEconomica($seguimiento);

        return [
            'id' => $seguimiento->id,
            'token' => $seguimiento->token,
            'estadoProceso' => $seguimiento->estado_proceso,
            'estadoProcesoEtiqueta' => EstadoSeguimientoInscripcion::tryFrom($seguimiento->estado_proceso)?->etiquetaPortal(),
            'fechaLimitePago' => $seguimiento->fecha_limite_pago?->toDateString(),
            'fechaCorreoEnviado' => $seguimiento->fecha_correo_enviado?->toIso8601String(),
            'correoDestino' => $seguimiento->correo_destino,
            'urlPortal' => $this->urlPortal($seguimiento->token),
            'informacionConfirmada' => (bool) $seguimiento->fecha_correo_enviado,
            ...$info,
        ];
    }

    public function formatearParaPortal(SeguimientoInscripcion $seguimiento): array
    {
        if (!$seguimiento->idFactura || !$seguimiento->factura) {
            /** @var PortalConsultaInscripcionService $portalService */
            $portalService = app(PortalConsultaInscripcionService::class);
            $formService = app(FormularioInscripcionDatosService::class);
            $respuesta = $seguimiento->idFormularioRespuesta
                ? $formService->buscarRespuestaPorId((int) $seguimiento->idFormularioRespuesta)
                : null;

            if ($respuesta) {
                $datosForm = $formService->formatearDatosFormulario($respuesta);

                return $portalService->formatearPortalSinFacturaPublico($seguimiento, $datosForm, $respuesta);
            }
        }

        $seguimiento = $this->sincronizarEstadoDesdeFactura($seguimiento);
        $factura = $seguimiento->factura;
        $info = $this->resolverInfoAcademicaEconomica($seguimiento);
        $pago = $factura->transacciones->first()?->pago?->first();
        $total = (float) ($factura->valorMasIva ?? $factura->valor);
        $saldo = $pago ? max(0, (float) $pago->excedente) : $total;
        $valorPagado = max(0, round($total - $saldo, 2));
        $facturaPagada = $this->pagoService->facturaEstaPagada($factura);
        $comprobantePendiente = $seguimiento->comprobantes->where('estado', 'PENDIENTE_REVISION')->isNotEmpty();

        $estadoEnum = EstadoSeguimientoInscripcion::tryFrom($seguimiento->estado_proceso)
            ?? EstadoSeguimientoInscripcion::PENDIENTE_PAGO;
        $mensaje = $estadoEnum->mensajePortal();
        if ($estadoEnum === EstadoSeguimientoInscripcion::RECHAZADA && $seguimiento->observaciones_admin) {
            $mensaje = $seguimiento->observaciones_admin;
        }

        $formService = app(FormularioInscripcionDatosService::class);
        $datosForm = $formService->resolverParaFactura(
            $factura->tercero,
            (int) $seguimiento->idCompany,
            $seguimiento->idFormularioRespuesta ? (int) $seguimiento->idFormularioRespuesta : null
        ) ?? [];

        $puedeDescargar = (bool) $factura;
        $puedeSubir = !$facturaPagada && !$comprobantePendiente
            && $seguimiento->estado_proceso !== EstadoSeguimientoInscripcion::RECHAZADA->value;

        /** @var PortalConsultaInscripcionService $portalService */
        $portalService = app(PortalConsultaInscripcionService::class);

        return [
            'token' => $seguimiento->token,
            'estadoInscripcion' => $seguimiento->estado_proceso,
            'estadoInscripcionEtiqueta' => $estadoEnum->etiquetaPortal(),
            'mensajeEstado' => $mensaje,
            'observacionAdministrativa' => $seguimiento->observaciones_admin,
            'fechaLimitePago' => $seguimiento->fecha_limite_pago?->toDateString(),
            'numeroFactura' => $factura->numeroFactura,
            'saldoPendiente' => $saldo,
            'valorPagado' => $valorPagado,
            'facturaPagada' => $facturaPagada,
            'comprobantePendiente' => $comprobantePendiente,
            'ultimoComprobante' => $seguimiento->comprobantes->sortByDesc('id')->first()?->only([
                'estado', 'observacion_revision', 'fecha_carga',
            ]),
            'fechaInscripcion' => $datosForm['fechaEnvio'] ?? null,
            'tutor' => $datosForm['tutor'] ?? null,
            'documentos' => $datosForm['documentos'] ?? [],
            'idFormularioRespuesta' => $seguimiento->idFormularioRespuesta
                ? (int) $seguimiento->idFormularioRespuesta
                : ($datosForm['idFormularioRespuesta'] ?? null),
            'acciones' => [
                'puedeDescargarFactura' => (bool) $puedeDescargar,
                'puedeSubirComprobante' => $puedeSubir,
                'puedePagarEnLinea' => !$facturaPagada && $saldo > 0,
            ],
            'pagosEnLinea' => $portalService->metodosPagoEnLineaPlaceholder(),
            ...$info,
        ];
    }

    public function resolverInfoAcademicaEconomica(SeguimientoInscripcion $seguimiento): array
    {
        $seguimiento->loadMissing([
            'factura.detalles',
            'factura.tercero',
            'matricula.person.tipoIdentificacion',
            'matricula.ficha.jornada',
            'matricula.ficha.asignacion',
            'proceso',
        ]);

        $factura = $seguimiento->factura;
        $matricula = $seguimiento->matricula;
        $persona = $matricula?->person;
        $tercero = $factura->tercero;

        $nombre = $this->formatearNombrePersona($persona) ?? ($tercero->nombre ?? 'Estudiante');
        $tipoDoc = $persona?->tipoIdentificacion?->tipo ?? 'CC';
        $documento = $persona?->identificacion ?? ($tercero->identificacion ?? '');

        /** @var \App\Services\Inscripcion\FormularioInscripcionDatosService $formService */
        $formService = app(\App\Services\Inscripcion\FormularioInscripcionDatosService::class);
        $datosForm = $formService->resolverParaFactura(
            $tercero,
            (int) $seguimiento->idCompany,
            $seguimiento->idFormularioRespuesta ? (int) $seguimiento->idFormularioRespuesta : null
        );
        $estForm = $datosForm['estudiante'] ?? [];

        if (!empty($estForm['nombreCompleto'])) {
            $nombre = $estForm['nombreCompleto'];
        }
        if (!empty($estForm['tipoDocumento'])) {
            $tipoDoc = $estForm['tipoDocumento'];
        }
        if (!empty($estForm['documento'])) {
            $documento = $estForm['documento'];
        }

        $valorInscripcion = 0.0;
        $valorMatricula = 0.0;
        $conceptos = [];

        foreach ($factura->detalles as $detalle) {
            $titulo = strtolower((string) ($detalle->detalle ?? ''));
            $valor = (float) $detalle->valor;
            $conceptos[] = [
                'concepto' => $detalle->detalle,
                'valor' => $valor,
            ];

            if (str_contains($titulo, 'inscrip')) {
                $valorInscripcion += $valor;
            } elseif (str_contains($titulo, 'matr')) {
                $valorMatricula += $valor;
            }
        }

        $total = (float) ($factura->valorMasIva ?? $factura->valor);
        if ($valorInscripcion <= 0 && $valorMatricula <= 0 && count($conceptos) === 1) {
            $valorInscripcion = $total;
        }

        $jornada = $matricula?->ficha?->jornada?->nombreJornada
            ?? $matricula?->ficha?->jornada?->nombre
            ?? null;

        $periodo = $matricula?->ficha?->asignacion?->nombre ?? null;

        return [
            'nombreCompleto' => $nombre,
            'tipoDocumento' => $tipoDoc,
            'documento' => $documento,
            'email' => $persona?->email ?? ($estForm['email'] ?? null) ?? $tercero?->email,
            'telefono' => $persona?->celular ?? ($estForm['telefono'] ?? null) ?? $tercero?->telefono,
            'nombrePrograma' => $seguimiento->proceso?->nombreProceso ?? 'Proceso académico',
            'jornada' => $jornada,
            'periodoAcademico' => $periodo,
            'valorInscripcion' => $valorInscripcion,
            'valorMatricula' => $valorMatricula,
            'totalPagar' => $total,
            'conceptos' => $conceptos,
        ];
    }

    private function generarToken(): string
    {
        return hash('sha256', Str::uuid()->toString() . microtime(true) . Str::random(32));
    }

    private function urlPortal(string $token): string
    {
        $frontend = rtrim((string) env('FRONTEND_URL', env('APP_URL', 'http://localhost:5173')), '/');

        return $frontend . '/seguimiento-inscripcion/' . $token;
    }

    private function enviarCorreoAceptacion(SeguimientoInscripcion $seguimiento): void
    {
        if (empty($seguimiento->correo_destino)) {
            return;
        }

        $info = $this->resolverInfoAcademicaEconomica($seguimiento);
        $url = $this->urlPortal($seguimiento->token);

        $html = view('emails.inscripcion-aceptada', [
            'nombre' => $info['nombreCompleto'],
            'documento' => $info['documento'],
            'programa' => $info['nombrePrograma'],
            'jornada' => $info['jornada'],
            'total' => number_format($info['totalPagar'], 0, ',', '.'),
            'valorInscripcion' => number_format($info['valorInscripcion'] > 0 ? $info['valorInscripcion'] : $info['totalPagar'], 0, ',', '.'),
            'fechaLimite' => $seguimiento->fecha_limite_pago?->format('d/m/Y'),
            'estadoActual' => EstadoSeguimientoInscripcion::tryFrom($seguimiento->estado_proceso)?->etiquetaPortal()
                ?? 'Factura generada',
            'urlPortal' => $url,
        ])->render();

        Mail::to($seguimiento->correo_destino)->send(
            new MailService(
                'Confirmación de inscripción — seguimiento de pago',
                $html
            )
        );
    }

    private function formatearNombrePersona(?Person $persona): ?string
    {
        if (!$persona) {
            return null;
        }

        $partes = array_filter([
            $persona->nombre1,
            $persona->nombre2,
            $persona->apellido1,
            $persona->apellido2,
        ]);

        $nombre = trim(implode(' ', $partes));

        return $nombre !== '' ? $nombre : null;
    }
}
