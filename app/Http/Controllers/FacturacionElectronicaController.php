<?php

namespace App\Http\Controllers;

use App\Models\FacturaElectronica;
use App\Jobs\CreateInvoiceJob;
use App\Util\KeyUtil;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class FacturacionElectronicaController extends Controller
{
    
    public function getFacturasElectronicas()
    {
        $facturasElectronicas = FacturaElectronica::with('ticket.tercero') 
            ->where('idEmpresa', KeyUtil::idCompany())
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $facturasElectronicas
        ]);
    }

    /**
     * Obtener estadísticas de facturas por estado
     */
    public function getFacturasStats()
    {
        $stats = FacturaElectronica::where('idEmpresa', KeyUtil::idCompany())
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->get()
            ->pluck('total', 'status');

        return response()->json([
            'success' => true,
            'data' => [
                'validated' => $stats['validated'] ?? 0,
                'pending' => $stats['pending'] ?? 0,
                'error' => $stats['error'] ?? 0,
                'total' => $stats->sum()
            ]
        ]);
    }

    /**
     * Reintentar facturas fallidas o pendientes
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function retryFailedInvoices(Request $request)
    {
        try {
            $limit = $request->input('limit', 20);
            $statuses = $request->input('statuses', ['error', 'pending']);

            // Validar que el límite sea razonable
            if ($limit > 100) {
                return response()->json([
                    'success' => false,
                    'message' => 'El límite máximo es 100 facturas'
                ], 400);
            }

            Log::info("🔄 Iniciando reintento de facturas desde API", [
                'empresa' => KeyUtil::idCompany(),
                'limit' => $limit,
                'statuses' => $statuses,
                'usuario' => auth()->user()->id ?? 'desconocido'
            ]);

            // Buscar facturas con los estados especificados
            $facturas = FacturaElectronica::whereIn('status', $statuses)
                ->where('idEmpresa', KeyUtil::idCompany())
                ->whereHas('ticket')
                ->limit($limit)
                ->get();

            if ($facturas->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'message' => 'No se encontraron facturas para reintentar',
                    'data' => [
                        'processed' => 0,
                        'facturas' => []
                    ]
                ]);
            }

            $procesadas = [];

            foreach ($facturas as $factura) {
                $ticket = $factura->ticket;
                
                if (!$ticket) {
                    Log::warning("⚠️ Factura {$factura->id} sin ticket asociado");
                    continue;
                }

                // Eliminar la factura fallida para que se cree una nueva
                $factura->delete();

                // Despachar el job con delay para evitar sobrecarga
                dispatch(new CreateInvoiceJob($ticket))->delay(now()->addSeconds(5));
                
                $procesadas[] = [
                    'ticket_id' => $ticket->id,
                    'reference_code' => 'ticket-' . $ticket->numeroTicket,
                    'previous_status' => $factura->status,
                ];

                Log::info("📤 Factura reenviada a cola", [
                    'ticket_id' => $ticket->id,
                    'reference_code' => 'ticket-' . $ticket->numeroTicket
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => "Se han reenviado {$facturas->count()} facturas a la cola de procesamiento",
                'data' => [
                    'processed' => count($procesadas),
                    'facturas' => $procesadas
                ]
            ]);

        } catch (\Throwable $e) {
            Log::error("❌ Error al reintentar facturas desde API", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al procesar el reintento de facturas',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener detalles de una factura específica
     */
    public function getFacturaDetails($id)
    {
        $factura = FacturaElectronica::with('ticket.tercero', 'ticket.ruta.ciudadOrigen', 'ticket.ruta.ciudadDestino')
            ->where('idEmpresa', KeyUtil::idCompany())
            ->find($id);

        if (!$factura) {
            return response()->json([
                'success' => false,
                'message' => 'Factura no encontrada'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $factura
        ]);
    }
      
}
