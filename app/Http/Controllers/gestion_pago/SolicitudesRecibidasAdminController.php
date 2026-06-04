<?php

namespace App\Http\Controllers\gestion_pago;

use App\Http\Controllers\Controller;
use App\Services\Inscripcion\SolicitudesRecibidasService;
use App\Util\KeyUtil;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SolicitudesRecibidasAdminController extends Controller
{
    public function __construct(
        private readonly SolicitudesRecibidasService $service
    ) {
    }

    public function index(Request $request)
    {
        $idCompany = (int) ($request->input('idCompany') ?: KeyUtil::idCompany());

        return response()->json($this->service->listar($idCompany));
    }

    public function show(int $id)
    {
        $idCompany = (int) KeyUtil::idCompany();
        $detalle = $this->service->detalle($id, $idCompany);

        if (!$detalle) {
            return response()->json(['error' => 'Solicitud no encontrada.'], 404);
        }

        return response()->json($detalle);
    }

    public function generarFactura(Request $request, int $id)
    {
        try {
            $resultado = $this->service->generarFactura($request, $id);

            return response()->json($resultado, 201);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function confirmarInformacion(Request $request, int $id)
    {
        try {
            $resultado = $this->service->confirmarInformacion($request, $id);

            return response()->json($resultado, 201);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function rechazar(Request $request, int $id)
    {
        $request->validate([
            'observacion' => ['required', 'string', 'max:1000'],
        ]);

        try {
            $resultado = $this->service->rechazar($id, $request->input('observacion'), Auth::id());

            return response()->json($resultado);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function solicitarCorreccion(Request $request, int $id)
    {
        $request->validate([
            'observacion' => ['required', 'string', 'max:1000'],
        ]);

        try {
            $resultado = $this->service->solicitarCorreccion($id, $request->input('observacion'), Auth::id());

            return response()->json($resultado);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }
}
