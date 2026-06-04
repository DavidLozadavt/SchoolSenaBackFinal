<?php

namespace App\Http\Controllers\publico;

use App\Http\Controllers\Controller;
use App\Services\Inscripcion\PortalConsultaInscripcionService;
use App\Services\Inscripcion\SeguimientoComprobanteService;
use App\Services\Inscripcion\SeguimientoInscripcionService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class SeguimientoInscripcionPublicoController extends Controller
{
    public function __construct(
        private readonly SeguimientoInscripcionService $seguimientoService,
        private readonly SeguimientoComprobanteService $comprobanteService,
        private readonly PortalConsultaInscripcionService $consultaService
    ) {
    }

    public function show(string $token)
    {
        $seguimiento = $this->seguimientoService->obtenerPorToken($token);

        if (!$seguimiento) {
            return response()->json(['error' => 'Enlace no válido o expirado.'], 404);
        }

        if ($seguimiento->token_expires_at && $seguimiento->token_expires_at->isPast()) {
            return response()->json(['error' => 'El enlace de seguimiento ha expirado.'], 410);
        }

        return response()->json(
            $this->seguimientoService->formatearParaPortal($seguimiento)
        );
    }

    public function consultar(Request $request)
    {
        $request->validate([
            'documento' => ['required_without:identificacion', 'string', 'max:40'],
            'identificacion' => ['required_without:documento', 'string', 'max:40'],
            'tipoDocumento' => ['nullable', 'string', 'max:80'],
            'email' => ['nullable', 'email', 'max:255'],
            'fechaNacimiento' => ['nullable', 'string', 'max:30'],
            'idCompany' => ['nullable', 'integer'],
        ]);

        $documento = trim((string) ($request->input('documento') ?: $request->input('identificacion')));
        $idCompany = (int) ($request->input('idCompany') ?: 1);

        try {
            $resultado = $this->consultaService->consultar(
                $documento,
                $idCompany,
                $request->input('tipoDocumento'),
                $request->input('email'),
                $request->input('fechaNacimiento')
            );

            return response()->json($resultado);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 404);
        }
    }

    public function buscar(Request $request)
    {
        return $this->consultar($request);
    }

    public function descargarFacturaPdf(string $token)
    {
        $seguimiento = $this->seguimientoService->obtenerPorToken($token);

        if (!$seguimiento) {
            return response()->json(['error' => 'Enlace no válido.'], 404);
        }

        if (!$seguimiento->factura) {
            return response()->json(['error' => 'Aún no tiene factura académica generada.'], 422);
        }

        $info = $this->seguimientoService->resolverInfoAcademicaEconomica($seguimiento);
        $factura = $seguimiento->factura;

        $pdf = Pdf::loadView('pdf.factura-academica-inscripcion', [
            'numeroFactura' => $factura->numeroFactura,
            'fecha' => $factura->fecha,
            'nombreEstudiante' => $info['nombreCompleto'],
            'documento' => $info['documento'],
            'programa' => $info['nombrePrograma'],
            'detalles' => $info['conceptos'],
            'total' => $info['totalPagar'],
            'referenciaPago' => $factura->numeroFactura,
        ]);

        return $pdf->download('factura-' . ($factura->numeroFactura ?? $factura->id) . '.pdf');
    }

    public function subirComprobante(Request $request, string $token)
    {
        $request->validate([
            'archivo' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ]);

        $seguimiento = $this->seguimientoService->obtenerPorToken($token);

        if (!$seguimiento) {
            return response()->json(['error' => 'Enlace no válido.'], 404);
        }

        if (!$seguimiento->factura) {
            return response()->json(['error' => 'Aún no tiene factura académica para cargar comprobante.'], 422);
        }

        $portal = $this->seguimientoService->formatearParaPortal($seguimiento);
        if ($portal['facturaPagada'] ?? false) {
            return response()->json(['error' => 'La factura ya está pagada.'], 422);
        }

        try {
            $comprobante = $this->comprobanteService->subirComprobante(
                $seguimiento,
                $request->file('archivo')
            );

            return response()->json([
                'message' => 'Comprobante cargado. Será revisado por el área administrativa.',
                'comprobante' => [
                    'id' => $comprobante->id,
                    'estado' => $comprobante->estado,
                ],
                'portal' => $this->seguimientoService->formatearParaPortal($seguimiento->fresh()),
            ], 201);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }
}
