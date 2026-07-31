<?php

namespace App\Http\Controllers;

use App\Services\MessageStatisticsService;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Estadísticas de Mensajes WhatsApp — SENA. Módulo de solo lectura sobre
 * datos ya existentes de Seguimiento de Aspirantes. No modifica el flujo
 * de envío, el webhook, ni ninguna fila de seguimientoAspirantes.
 */
class MessageStatisticsController extends Controller
{
    public function __construct(private MessageStatisticsService $service)
    {
    }

    /**
     * GET /api/sena/message-statistics — tabla paginada con filtros.
     */
    public function index(Request $request)
    {
        return response()->json($this->service->listado($request));
    }

    /**
     * GET /api/sena/message-statistics/dashboard — KPIs + datos para gráficos.
     */
    public function dashboard(Request $request)
    {
        return response()->json([
            'kpis' => $this->service->kpis($request),
            'porDia' => $this->service->porDia($request),
            'porPrograma' => $this->service->porPrograma($request),
            'porFicha' => $this->service->porFicha($request),
            'plantillasMasUsadas' => $this->service->plantillasMasUsadas($request),
            'estadosDistribucion' => $this->service->estadosDistribucion($request),
        ]);
    }

    /**
     * GET /api/sena/message-statistics/report — reporte agregado (JSON).
     */
    public function report(Request $request)
    {
        return response()->json($this->service->reporte($request));
    }

    /**
     * GET /api/sena/message-statistics/export/excel
     */
    public function exportExcel(Request $request)
    {
        $reporte = $this->service->reporte($request);

        $spreadsheet = new Spreadsheet();

        // Hoja 1: resumen
        $resumen = $spreadsheet->getActiveSheet();
        $resumen->setTitle('Resumen');
        $resumen->fromArray([
            ['Reporte de Estadísticas WhatsApp - SENA'],
            ['Periodo', ($reporte['periodo']['desde'] ?? 'Sin límite') . ' a ' . ($reporte['periodo']['hasta'] ?? 'Sin límite')],
            [],
            ['Indicador', 'Total'],
            ['Enviados', $reporte['totales']['enviados']],
            ['Entregados', $reporte['totales']['entregados']],
            ['Leídos', $reporte['totales']['leidos']],
            ['Con error', $reporte['totales']['errores']],
        ], null, 'A1');
        $resumen->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $resumen->getStyle('A4:B4')->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $resumen->getStyle('A4:B4')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('16a34a');
        foreach (range('A', 'B') as $col) {
            $resumen->getColumnDimension($col)->setAutoSize(true);
        }

        // Hoja 2: por plantilla
        $hojaPlantilla = $spreadsheet->createSheet();
        $hojaPlantilla->setTitle('Por Plantilla');
        $hojaPlantilla->fromArray([['Plantilla', 'Total']], null, 'A1');
        $hojaPlantilla->getStyle('A1:B1')->getFont()->setBold(true);
        $filas = array_map(fn ($r) => [$r['plantilla'], $r['total']], $reporte['porPlantilla']);
        $hojaPlantilla->fromArray($filas, null, 'A2');
        foreach (range('A', 'B') as $col) {
            $hojaPlantilla->getColumnDimension($col)->setAutoSize(true);
        }

        // Hoja 3: por programa
        $hojaPrograma = $spreadsheet->createSheet();
        $hojaPrograma->setTitle('Por Programa');
        $hojaPrograma->fromArray([['Programa', 'Total']], null, 'A1');
        $hojaPrograma->getStyle('A1:B1')->getFont()->setBold(true);
        $filasPrograma = array_map(fn ($r) => [$r['programa'], $r['total']], $reporte['porPrograma']);
        $hojaPrograma->fromArray($filasPrograma, null, 'A2');
        foreach (range('A', 'B') as $col) {
            $hojaPrograma->getColumnDimension($col)->setAutoSize(true);
        }

        $spreadsheet->setActiveSheetIndex(0);

        $tmpPath = tempnam(sys_get_temp_dir(), 'wa_stats') . '.xlsx';
        (new Xlsx($spreadsheet))->save($tmpPath);

        return response()->download($tmpPath, 'estadisticas_whatsapp.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    /**
     * GET /api/sena/message-statistics/export/pdf
     */
    public function exportPdf(Request $request)
    {
        $reporte = $this->service->reporte($request);

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.whatsapp_statistics', ['reporte' => $reporte])
            ->setPaper('a4', 'portrait');

        return $pdf->stream('estadisticas_whatsapp.pdf');
    }
}
