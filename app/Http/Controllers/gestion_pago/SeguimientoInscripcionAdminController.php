<?php

namespace App\Http\Controllers\gestion_pago;

use App\Http\Controllers\Controller;
use App\Models\SeguimientoInscripcionComprobante;
use App\Services\Inscripcion\SeguimientoComprobanteService;
use App\Util\KeyUtil;
use Illuminate\Http\Request;

class SeguimientoInscripcionAdminController extends Controller
{
    public function __construct(
        private readonly SeguimientoComprobanteService $comprobanteService
    ) {
    }

    public function listarComprobantes(Request $request)
    {
        $idCompany = (int) ($request->input('idCompany') ?: KeyUtil::idCompany());
        $estado = $request->input('estado');

        return response()->json(
            $this->comprobanteService->listarBandeja($idCompany, $estado)
        );
    }

    public function aprobarComprobante(Request $request, int $id)
    {
        $request->validate([
            'idMedioPago' => ['nullable', 'integer'],
        ]);

        $idCompany = (int) KeyUtil::idCompany();
        $comprobante = SeguimientoInscripcionComprobante::with('seguimiento')
            ->where('id', $id)
            ->whereHas('seguimiento', fn ($q) => $q->where('idCompany', $idCompany))
            ->first();

        if (!$comprobante) {
            return response()->json(['error' => 'Comprobante no encontrado.'], 404);
        }

        if ($comprobante->estado !== 'PENDIENTE_REVISION') {
            return response()->json(['error' => 'El comprobante ya fue revisado.'], 422);
        }

        $idUser = auth()->id() ?? 0;

        $this->comprobanteService->aprobar(
            $comprobante,
            (int) $idUser,
            (int) $request->input('idMedioPago', 1)
        );

        return response()->json(['message' => 'Comprobante aprobado y pago registrado.']);
    }

    public function rechazarComprobante(Request $request, int $id)
    {
        $request->validate([
            'observacion' => ['required', 'string', 'max:500'],
        ]);

        $idCompany = (int) KeyUtil::idCompany();
        $comprobante = SeguimientoInscripcionComprobante::with('seguimiento')
            ->where('id', $id)
            ->whereHas('seguimiento', fn ($q) => $q->where('idCompany', $idCompany))
            ->first();

        if (!$comprobante) {
            return response()->json(['error' => 'Comprobante no encontrado.'], 404);
        }

        $idUser = auth()->id() ?? 0;

        $this->comprobanteService->rechazar(
            $comprobante,
            (int) $idUser,
            $request->input('observacion')
        );

        return response()->json(['message' => 'Comprobante rechazado.']);
    }
}
