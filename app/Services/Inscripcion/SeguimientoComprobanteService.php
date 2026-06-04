<?php

namespace App\Services\Inscripcion;

use App\Enums\EstadoSeguimientoInscripcion;
use App\Models\Pago;
use App\Models\SeguimientoInscripcion;
use App\Models\SeguimientoInscripcionComprobante;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;

class SeguimientoComprobanteService
{
    public function __construct(
        private readonly FacturaAcademicaPagoService $pagoService,
        private readonly SeguimientoInscripcionService $seguimientoService
    ) {
    }

    public function subirComprobante(SeguimientoInscripcion $seguimiento, UploadedFile $archivo): SeguimientoInscripcionComprobante
    {
        $mime = $archivo->getMimeType() ?? $archivo->getClientMimeType();
        $permitidos = ['application/pdf', 'image/jpeg', 'image/jpg', 'image/png'];

        if (!in_array($mime, $permitidos, true)) {
            throw new \InvalidArgumentException('Formato no permitido. Use PDF, JPG o PNG.');
        }

        $ruta = '/storage/' . $archivo->store(Pago::RUTA_COMPROBANTE, ['disk' => 'public']);

        $comprobante = SeguimientoInscripcionComprobante::create([
            'idSeguimientoInscripcion' => $seguimiento->id,
            'idFactura' => $seguimiento->idFactura,
            'ruta_archivo' => $ruta,
            'nombre_original' => $archivo->getClientOriginalName(),
            'mime_type' => $mime,
            'estado' => 'PENDIENTE_REVISION',
            'fecha_carga' => Carbon::now(),
        ]);

        $seguimiento->estado_proceso = EstadoSeguimientoInscripcion::PAGO_EN_REVISION->value;
        $seguimiento->save();

        return $comprobante;
    }

    public function aprobar(SeguimientoInscripcionComprobante $comprobante, int $idUser, ?int $idMedioPago = 1): SeguimientoInscripcionComprobante
    {
        $comprobante->load('seguimiento.factura.transacciones.pago');
        $seguimiento = $comprobante->seguimiento;
        $factura = $seguimiento->factura;

        $resultado = $this->pagoService->registrarPago(
            $factura,
            $idMedioPago,
            null,
            null,
            $comprobante->ruta_archivo
        );

        $comprobante->idPago = $resultado['pago']->id ?? null;
        $comprobante->estado = 'APROBADO';
        $comprobante->revisado_por = $idUser;
        $comprobante->fecha_revision = Carbon::now();
        $comprobante->save();

        $seguimiento->estado_proceso = EstadoSeguimientoInscripcion::PAGO_APROBADO->value;
        $seguimiento->save();

        return $comprobante->fresh();
    }

    public function rechazar(
        SeguimientoInscripcionComprobante $comprobante,
        int $idUser,
        string $observacion
    ): SeguimientoInscripcionComprobante {
        $comprobante->estado = 'RECHAZADO';
        $comprobante->observacion_revision = $observacion;
        $comprobante->revisado_por = $idUser;
        $comprobante->fecha_revision = Carbon::now();
        $comprobante->save();

        $seguimiento = $comprobante->seguimiento;
        $seguimiento->estado_proceso = EstadoSeguimientoInscripcion::PENDIENTE_PAGO->value;
        $seguimiento->save();

        return $comprobante->fresh();
    }

    public function listarBandeja(int $idCompany, ?string $estado = null)
    {
        $query = SeguimientoInscripcionComprobante::with([
            'seguimiento.factura.tercero',
            'seguimiento.proceso',
        ])
            ->whereHas('seguimiento', fn ($q) => $q->where('idCompany', $idCompany))
            ->orderByDesc('id');

        if ($estado) {
            $query->where('estado', strtoupper($estado));
        }

        return $query->get()->map(fn ($c) => $this->formatearBandeja($c));
    }

    private function formatearBandeja(SeguimientoInscripcionComprobante $c): array
    {
        $seg = $c->seguimiento;
        $info = $this->seguimientoService->resolverInfoAcademicaEconomica($seg);

        return [
            'id' => $c->id,
            'idSeguimiento' => $seg->id,
            'idFactura' => $c->idFactura,
            'estado' => $c->estado,
            'nombreEstudiante' => $info['nombreCompleto'],
            'documento' => $info['documento'],
            'programa' => $info['nombrePrograma'],
            'nombreArchivo' => $c->nombre_original,
            'urlArchivo' => $c->urlArchivo,
            'fechaCarga' => $c->fecha_carga?->toIso8601String(),
            'observacionRevision' => $c->observacion_revision,
        ];
    }
}
