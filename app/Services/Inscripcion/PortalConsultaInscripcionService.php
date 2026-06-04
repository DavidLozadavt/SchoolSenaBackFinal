<?php

namespace App\Services\Inscripcion;

use App\Enums\EstadoSeguimientoInscripcion;
use App\Models\ConfiguracionPago;
use App\Models\Factura;
use App\Models\FormularioRespuesta;
use App\Models\SeguimientoInscripcion;
use App\Models\Status;
use App\Models\Tercero;
use App\Models\TipoFactura;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class PortalConsultaInscripcionService
{
    public function __construct(
        private readonly FormularioInscripcionDatosService $formularioService,
        private readonly SeguimientoInscripcionService $seguimientoService,
        private readonly FacturaAcademicaPagoService $pagoService
    ) {
    }

    /**
     * @return array{portal: array, token: ?string, redirectPath: ?string}
     */
    public function consultar(
        string $documento,
        int $idCompany = 1,
        ?string $tipoDocumento = null,
        ?string $email = null,
        ?string $fechaNacimiento = null
    ): array {
        $documento = trim($documento);
        if ($documento === '') {
            throw new \InvalidArgumentException('El número de documento es obligatorio.');
        }

        $respuestaForm = $this->formularioService->buscarRespuestaPorDocumento($documento, $idCompany);
        if (!$respuestaForm) {
            throw new \RuntimeException('No se encontró inscripción con ese documento.');
        }

        $datosForm = $this->formularioService->formatearDatosFormulario($respuestaForm);
        $this->validarDatosOpcionales($datosForm, $tipoDocumento, $email, $fechaNacimiento);

        $tercero = Tercero::where('identificacion', $documento)
            ->where(function ($q) use ($idCompany) {
                $q->where('idCompany', $idCompany)->orWhereNull('idCompany');
            })
            ->first();

        $factura = $this->buscarFacturaAcademicaPorTercero($tercero, $idCompany);
        $seguimiento = $this->resolverSeguimiento($respuestaForm, $factura, $tercero, $idCompany);

        if ($seguimiento && $factura && (int) $seguimiento->idFactura !== (int) $factura->id) {
            $seguimiento->idFactura = $factura->id;
            $seguimiento->idTercero = $factura->idTercero ?? $tercero?->id;
            $seguimiento->save();
        }

        $portal = $this->construirRespuestaPortal($seguimiento, $respuestaForm, $factura, $datosForm, $idCompany);
        $token = $seguimiento?->token;

        return [
            'portal' => $portal,
            'token' => $token,
            'redirectPath' => $token ? '/seguimiento-inscripcion/' . $token : null,
            ...$portal,
        ];
    }

    private function validarDatosOpcionales(
        array $datosForm,
        ?string $tipoDocumento,
        ?string $email,
        ?string $fechaNacimiento
    ): void {
        $est = $datosForm['estudiante'] ?? [];

        if ($tipoDocumento !== null && trim($tipoDocumento) !== '') {
            $tipoForm = Str::lower(trim((string) ($est['tipoDocumento'] ?? '')));
            $tipoIngresado = Str::lower(trim($tipoDocumento));
            if ($tipoForm !== '' && $tipoForm !== $tipoIngresado) {
                throw new \RuntimeException('Los datos ingresados no coinciden con la solicitud registrada.');
            }
        }

        if ($email !== null && trim($email) !== '') {
            $emailForm = Str::lower(trim((string) ($est['email'] ?? '')));
            $emailIngresado = Str::lower(trim($email));
            if ($emailForm !== '' && $emailForm !== $emailIngresado) {
                throw new \RuntimeException('Los datos ingresados no coinciden con la solicitud registrada.');
            }
        }

        if ($fechaNacimiento !== null && trim($fechaNacimiento) !== '') {
            $fechaForm = $this->normalizarFecha((string) ($est['fechaNacimiento'] ?? ''));
            $fechaIngresada = $this->normalizarFecha($fechaNacimiento);
            if ($fechaForm !== '' && $fechaIngresada !== '' && $fechaForm !== $fechaIngresada) {
                throw new \RuntimeException('Los datos ingresados no coinciden con la solicitud registrada.');
            }
        }
    }

    private function normalizarFecha(string $fecha): string
    {
        $fecha = trim($fecha);
        if ($fecha === '') {
            return '';
        }

        try {
            return Carbon::parse($fecha)->toDateString();
        } catch (\Throwable) {
            return $fecha;
        }
    }

    private function resolverSeguimiento(
        FormularioRespuesta $respuestaForm,
        ?Factura $factura,
        ?Tercero $tercero,
        int $idCompany
    ): ?SeguimientoInscripcion {
        if ($factura) {
            $seguimiento = $this->seguimientoService->obtenerPorFactura((int) $factura->id);
            if ($seguimiento) {
                if (!$seguimiento->idFormularioRespuesta) {
                    $seguimiento->idFormularioRespuesta = $respuestaForm->id;
                    $seguimiento->save();
                }

                return $seguimiento->fresh([
                    'factura.detalles',
                    'factura.tercero',
                    'factura.transacciones.pago.estado',
                    'matricula.ficha.jornada',
                    'matricula.ficha.asignacion',
                    'proceso',
                    'comprobantes',
                ]);
            }
        }

        $seguimiento = SeguimientoInscripcion::with([
            'factura.detalles',
            'factura.tercero',
            'factura.transacciones.pago.estado',
            'matricula.ficha.jornada',
            'matricula.ficha.asignacion',
            'proceso',
            'comprobantes',
        ])
            ->where('idFormularioRespuesta', $respuestaForm->id)
            ->latest('id')
            ->first();

        if ($seguimiento) {
            return $seguimiento;
        }

        if ($tercero) {
            $seguimiento = SeguimientoInscripcion::with([
                'factura.detalles',
                'factura.tercero',
                'factura.transacciones.pago.estado',
                'matricula.ficha.jornada',
                'proceso',
                'comprobantes',
            ])
                ->where('idCompany', $idCompany)
                ->where('idTercero', $tercero->id)
                ->whereNull('idFactura')
                ->latest('id')
                ->first();

            if ($seguimiento) {
                return $seguimiento;
            }
        }

        return null;
    }

    private function construirRespuestaPortal(
        ?SeguimientoInscripcion $seguimiento,
        FormularioRespuesta $respuestaForm,
        ?Factura $factura,
        array $datosForm,
        int $idCompany
    ): array {
        if ($seguimiento && $seguimiento->factura) {
            $portal = $this->seguimientoService->formatearParaPortal($seguimiento);
            $portal['tutor'] = $datosForm['tutor'] ?? null;
            $portal['documentos'] = $datosForm['documentos'] ?? [];
            $portal['fechaInscripcion'] = $datosForm['fechaEnvio'] ?? $respuestaForm->created_at?->toIso8601String();
            $portal['idFormularioRespuesta'] = (int) $respuestaForm->id;

            return $portal;
        }

        if ($seguimiento && !$seguimiento->factura) {
            return $this->formatearPortalSinFactura($seguimiento, $datosForm, $respuestaForm);
        }

        if ($factura) {
            $seguimientoTemp = new SeguimientoInscripcion([
                'idFactura' => $factura->id,
                'idTercero' => $factura->idTercero,
                'idFormularioRespuesta' => $respuestaForm->id,
                'idCompany' => $idCompany,
                'estado_proceso' => EstadoSeguimientoInscripcion::FACTURA_GENERADA->value,
            ]);
            $seguimientoTemp->setRelation('factura', $factura->loadMissing([
                'detalles', 'tercero', 'transacciones.pago.estado',
            ]));
            $seguimientoTemp->setRelation('comprobantes', collect());

            $portal = $this->seguimientoService->formatearParaPortal($seguimientoTemp);
            $portal['tutor'] = $datosForm['tutor'] ?? null;
            $portal['documentos'] = $datosForm['documentos'] ?? [];
            $portal['fechaInscripcion'] = $datosForm['fechaEnvio'] ?? $respuestaForm->created_at?->toIso8601String();

            return $portal;
        }

        return $this->formatearPortalSinFactura(null, $datosForm, $respuestaForm);
    }

    public function formatearPortalSinFacturaPublico(
        ?SeguimientoInscripcion $seguimiento,
        array $datosForm,
        FormularioRespuesta $respuestaForm
    ): array {
        return $this->formatearPortalSinFactura($seguimiento, $datosForm, $respuestaForm);
    }

    private function formatearPortalSinFactura(
        ?SeguimientoInscripcion $seguimiento,
        array $datosForm,
        FormularioRespuesta $respuestaForm
    ): array {
        $est = $datosForm['estudiante'] ?? [];
        $estado = EstadoSeguimientoInscripcion::SOLICITUD_RECIBIDA;

        if ($seguimiento) {
            $estadoRaw = EstadoSeguimientoInscripcion::tryFrom($seguimiento->estado_proceso);
            if ($estadoRaw) {
                $estado = $estadoRaw;
            }
        }

        $observacion = $seguimiento?->observaciones_admin;
        $mensaje = $estado->mensajePortal();
        if ($estado === EstadoSeguimientoInscripcion::RECHAZADA && $observacion) {
            $mensaje = $observacion;
        } elseif ($estado === EstadoSeguimientoInscripcion::CORRECCION_SOLICITADA && $observacion) {
            $mensaje = 'Se solicitó corrección de su solicitud: ' . $observacion;
        }

        return [
            'token' => $seguimiento?->token,
            'estadoInscripcion' => $estado->value,
            'estadoInscripcionEtiqueta' => $estado->etiquetaPortal(),
            'mensajeEstado' => $mensaje,
            'observacionAdministrativa' => $observacion,
            'fechaLimitePago' => null,
            'numeroFactura' => null,
            'saldoPendiente' => 0,
            'valorPagado' => 0,
            'facturaPagada' => false,
            'comprobantePendiente' => false,
            'ultimoComprobante' => null,
            'nombreCompleto' => $est['nombreCompleto'] ?? 'Estudiante',
            'tipoDocumento' => $est['tipoDocumento'] ?? 'CC',
            'documento' => $est['documento'] ?? '',
            'email' => $est['email'] ?? null,
            'telefono' => $est['telefono'] ?? null,
            'nombrePrograma' => $seguimiento?->proceso?->nombreProceso
                ?? ($est['programaInteres'] ?? null)
                ?? 'Proceso académico',
            'jornada' => null,
            'periodoAcademico' => null,
            'fechaInscripcion' => $datosForm['fechaEnvio'] ?? $respuestaForm->created_at?->toIso8601String(),
            'valorInscripcion' => 0,
            'valorMatricula' => 0,
            'totalPagar' => 0,
            'conceptos' => [],
            'tutor' => $datosForm['tutor'] ?? null,
            'documentos' => $datosForm['documentos'] ?? [],
            'idFormularioRespuesta' => (int) $respuestaForm->id,
            'acciones' => [
                'puedeDescargarFactura' => false,
                'puedeSubirComprobante' => false,
                'puedePagarEnLinea' => false,
            ],
            'pagosEnLinea' => $this->metodosPagoEnLineaPlaceholder(),
        ];
    }

    public function metodosPagoEnLineaPlaceholder(): array
    {
        return [
            ['codigo' => 'WOMPI', 'nombre' => 'Wompi', 'disponible' => false],
            ['codigo' => 'PSE', 'nombre' => 'PSE', 'disponible' => false],
            ['codigo' => 'TARJETA_DEBITO', 'nombre' => 'Tarjeta débito', 'disponible' => false],
            ['codigo' => 'TARJETA_CREDITO', 'nombre' => 'Tarjeta crédito', 'disponible' => false],
        ];
    }

    public function buscarFacturaAcademicaPorTercero(?Tercero $tercero, int $idCompany): ?Factura
    {
        if (!$tercero) {
            return null;
        }

        $tieneColumnaIdConfig = Schema::hasColumn('detalleFactura', 'idConfiguracionPago');

        $query = Factura::with(['detalles', 'tercero', 'transacciones.pago.estado'])
            ->where('idTipoFactura', TipoFactura::VENTA)
            ->where('idTercero', $tercero->id)
            ->where(function ($q) use ($idCompany) {
                $q->where('idCompany', $idCompany)->orWhereNull('idCompany');
            })
            ->orderByDesc('id');

        if ($tieneColumnaIdConfig) {
            $query->whereHas('detalles', fn ($q) => $q->whereNotNull('idConfiguracionPago'));
        } else {
            $idsConfig = ConfiguracionPago::where('idCompany', $idCompany)->pluck('id');
            if ($idsConfig->isEmpty()) {
                return null;
            }
            $titulos = ConfiguracionPago::whereIn('id', $idsConfig)->pluck('titulo')->filter();
            $query->whereHas('detalles', function ($q) use ($titulos) {
                $q->where(function ($inner) use ($titulos) {
                    foreach ($titulos as $titulo) {
                        $inner->orWhere('detalle', $titulo);
                    }
                });
            });
        }

        return $query->first();
    }

    public function documentoTieneFacturaAcademica(string $documento, int $idCompany): bool
    {
        $tercero = Tercero::where('identificacion', trim($documento))
            ->where(function ($q) use ($idCompany) {
                $q->where('idCompany', $idCompany)->orWhereNull('idCompany');
            })
            ->first();

        return (bool) $this->buscarFacturaAcademicaPorTercero($tercero, $idCompany);
    }
}
