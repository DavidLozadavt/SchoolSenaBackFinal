<?php

namespace App\Http\Controllers;

use App\Services\MessageStatisticsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Estadísticas de Mensajes WhatsApp — SENA. Módulo de solo lectura sobre
 * datos ya existentes de Seguimiento de Aspirantes. No modifica el flujo
 * de envío, el webhook, ni ninguna fila de seguimientoAspirantes.
 */
class MessageStatisticsController extends Controller
{
    private const COLOR_ENCABEZADO = '115E59'; // verde institucional oscuro
    private const COLOR_FRANJA = 'F3F4F6';

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
            'porMes' => $this->service->porMes($request),
            'porPrograma' => $this->service->porPrograma($request),
            'porFicha' => $this->service->porFicha($request),
            'porCentro' => $this->service->porCentro($request),
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
     * GET /api/sena/message-statistics/export/pdf
     */
    public function exportPdf(Request $request)
    {
        $reporte = $this->service->reporte($request);

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.whatsapp_statistics', [
            'reporte' => $reporte,
            'generadoPor' => Auth::user()->email ?? Auth::guard('api')->user()->email ?? 'Sistema',
        ])->setPaper('a4', 'portrait');

        return $pdf->stream('estadisticas_whatsapp.pdf');
    }

    /**
     * GET /api/sena/message-statistics/export/excel
     *
     * 8 hojas, una por sección, con formato profesional (encabezados de
     * color, negrita, autofiltro, primera fila congelada, autoajuste de
     * columnas, bordes, filas alternadas, totales al final).
     */
    public function exportExcel(Request $request)
    {
        $reporte = $this->service->reporte($request);

        $spreadsheet = new Spreadsheet();
        $spreadsheet->removeSheetByIndex(0);

        $this->hojaResumenEjecutivo($spreadsheet, $reporte);
        $this->hojaTabla($spreadsheet, 'Resumen por Estado', ['Estado', 'Cantidad'],
            collect($reporte['estadosDistribucion'])->map(fn ($r) => [$this->etiquetaEstado($r['estado']), $r['total']])->all(),
            totalColumnas: [1]);

        $this->hojaTabla($spreadsheet, 'Resumen por Plantilla', ['Plantilla', 'Mensajes', 'Conversaciones'],
            collect($reporte['resumenPorPlantilla'])->map(fn ($r) => [$r['plantilla'], $r['mensajes'], $r['conversaciones']])->all(),
            totalColumnas: [1, 2]);

        $this->hojaTabla($spreadsheet, 'Resumen por Programa', ['Programa', 'Mensajes enviados', 'Conversaciones'],
            collect($reporte['resumenPorPrograma'])->map(fn ($r) => [$r['programa'], $r['mensajes'], $r['conversaciones']])->all(),
            totalColumnas: [1, 2]);

        $this->hojaTabla($spreadsheet, 'Resumen por Centro', ['Centro de Formación', 'Mensajes enviados', 'Conversaciones'],
            collect($reporte['resumenPorCentro'])->map(fn ($r) => [$r['centro_formacion'], $r['mensajes'], $r['conversaciones']])->all(),
            totalColumnas: [1, 2]);

        $this->hojaTabla($spreadsheet, 'Resumen por Ficha', ['Ficha', 'Programa', 'Mensajes enviados'],
            collect($reporte['resumenPorFicha'])->map(fn ($r) => [$r['ficha'], $r['programa'], $r['mensajes']])->all(),
            totalColumnas: [2]);

        $this->hojaTabla($spreadsheet, 'Resumen por Fecha', ['Fecha', 'Mensajes enviados', 'Conversaciones'],
            collect($reporte['resumenPorFecha'])->map(fn ($r) => [$r['fecha'], $r['mensajes'], $r['conversaciones']])->all(),
            totalColumnas: [1, 2], formatoFecha: 'A');

        $this->hojaDetalleMensajes($spreadsheet, $reporte['detalleFacturacion']);

        $spreadsheet->setActiveSheetIndex(0);

        $tmpPath = tempnam(sys_get_temp_dir(), 'wa_stats') . '.xlsx';
        (new Xlsx($spreadsheet))->save($tmpPath);

        return response()->download($tmpPath, 'Estadisticas_WhatsApp_SENA.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    /**
     * Hoja 1: Resumen Ejecutivo — solo los indicadores principales, sin
     * tablas grandes, para que sea lo primero que vea el cliente.
     */
    private function hojaResumenEjecutivo(Spreadsheet $spreadsheet, array $reporte): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Resumen Ejecutivo');

        $sheet->setCellValue('A1', 'REPORTE DE ESTADÍSTICAS WHATSAPP');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16)->getColor()->setRGB(self::COLOR_ENCABEZADO);
        $sheet->setCellValue('A2', 'Seguimiento de Aspirantes SENA');
        $sheet->getStyle('A2')->getFont()->setItalic(true)->setSize(11);

        $sheet->setCellValue('A4', 'Período consultado');
        $sheet->setCellValue('B4', ($reporte['periodo']['desde'] ?? 'Sin límite inferior') . ' — ' . ($reporte['periodo']['hasta'] ?? 'Sin límite superior'));
        $sheet->setCellValue('A5', 'Fecha de generación');
        $sheet->setCellValue('B5', now()->format('d/m/Y H:i'));
        $sheet->getStyle('A4:A5')->getFont()->setBold(true);

        $indicadores = [
            ['Total de mensajes enviados', $reporte['totales']['enviados']],
            ['Total de mensajes entregados', $reporte['totales']['entregados']],
            ['Total de mensajes leídos', $reporte['totales']['leidos']],
            ['Total de mensajes fallidos', $reporte['totales']['errores']],
            ['Total de plantillas distintas enviadas', $reporte['totales']['totalPlantillasEnviadas']],
            ['Total de conversaciones iniciadas', $reporte['totales']['totalConversaciones']],
        ];

        $fila = 7;
        $sheet->setCellValue("A{$fila}", 'Indicador');
        $sheet->setCellValue("B{$fila}", 'Total');
        $sheet->getStyle("A{$fila}:B{$fila}")->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle("A{$fila}:B{$fila}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::COLOR_ENCABEZADO);

        foreach ($indicadores as $i => [$label, $valor]) {
            $f = $fila + 1 + $i;
            $sheet->setCellValue("A{$f}", $label);
            $sheet->setCellValue("B{$f}", (int) $valor);
            $sheet->getStyle("B{$f}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("B{$f}")->getFont()->setBold(true)->setSize(13);
            if ($i % 2 === 1) {
                $sheet->getStyle("A{$f}:B{$f}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::COLOR_FRANJA);
            }
        }

        $rangoBordes = 'A' . $fila . ':B' . ($fila + count($indicadores));
        $sheet->getStyle($rangoBordes)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('D1D5DB');

        $sheet->getColumnDimension('A')->setWidth(38);
        $sheet->getColumnDimension('B')->setWidth(20);
    }

    /**
     * Escribe una hoja de tabla genérica con formato: encabezado de color,
     * autofiltro, primera fila congelada, bordes, filas alternadas, ajuste
     * de columnas y (opcional) fila de totales al final.
     */
    private function hojaTabla(
        Spreadsheet $spreadsheet,
        string $titulo,
        array $encabezados,
        array $filas,
        array $totalColumnas = [],
        ?string $formatoFecha = null
    ): void {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle($titulo);

        $ultimaColumna = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($encabezados));

        $sheet->fromArray($encabezados, null, 'A1');
        $sheet->getStyle("A1:{$ultimaColumna}1")->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle("A1:{$ultimaColumna}1")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::COLOR_ENCABEZADO);
        $sheet->getStyle("A1:{$ultimaColumna}1")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        if (empty($filas)) {
            $sheet->setCellValue('A2', 'Sin datos en el periodo consultado.');
            $sheet->mergeCells("A2:{$ultimaColumna}2");
        } else {
            $sheet->fromArray($filas, null, 'A2');

            foreach ($filas as $i => $fila) {
                $numFila = $i + 2;
                if ($i % 2 === 1) {
                    $sheet->getStyle("A{$numFila}:{$ultimaColumna}{$numFila}")->getFill()
                        ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::COLOR_FRANJA);
                }
                foreach ($totalColumnas as $colIdx) {
                    $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIdx + 1);
                    $sheet->getStyle("{$col}{$numFila}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                }
                if ($formatoFecha) {
                    $sheet->getStyle("{$formatoFecha}{$numFila}")->getNumberFormat()->setFormatCode('dd/mm/yyyy');
                }
            }

            $ultimaFila = count($filas) + 1;
            $sheet->getStyle("A1:{$ultimaColumna}{$ultimaFila}")->getBorders()->getAllBorders()
                ->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('D1D5DB');

            // Fila de totales (suma de las columnas numéricas indicadas).
            if (!empty($totalColumnas)) {
                $filaTotal = $ultimaFila + 1;
                $sheet->setCellValue("A{$filaTotal}", 'Total');
                $sheet->getStyle("A{$filaTotal}")->getFont()->setBold(true);
                foreach ($totalColumnas as $colIdx) {
                    $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIdx + 1);
                    $sheet->setCellValue("{$col}{$filaTotal}", "=SUM({$col}2:{$col}{$ultimaFila})");
                    $sheet->getStyle("{$col}{$filaTotal}")->getFont()->setBold(true);
                    $sheet->getStyle("{$col}{$filaTotal}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                }
                $sheet->getStyle("A{$filaTotal}:{$ultimaColumna}{$filaTotal}")->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('CCFBF1');
                $sheet->getStyle("A{$filaTotal}:{$ultimaColumna}{$filaTotal}")->getBorders()->getTop()
                    ->setBorderStyle(Border::BORDER_MEDIUM)->getColor()->setRGB(self::COLOR_ENCABEZADO);
            }

            $sheet->setAutoFilter("A1:{$ultimaColumna}1");
        }

        $sheet->freezePane('A2');
        foreach (range('A', $ultimaColumna) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
    }

    /**
     * Hoja 8: Detalle de Mensajes Enviados — listado completo fila por
     * mensaje, con los campos que en el futuro permitirán calcular
     * facturación (ID Conversación, plantilla, IDs de Meta) sin necesidad
     * de modificar nuevamente el módulo.
     */
    private function hojaDetalleMensajes(Spreadsheet $spreadsheet, array $detalle): void
    {
        $encabezados = [
            'Fecha de Envío', 'Aspirante', 'Celular', 'Programa', 'Ficha', 'Centro de Formación',
            'Plantilla', 'Estado', 'ID de la Empresa (Meta)', 'ID del Número (Meta)',
            'ID Mensaje (Meta)', 'ID Conversación (Meta)',
        ];

        $filas = array_map(fn ($r) => [
            $r['fecha_envio'],
            trim(($r['nombre'] ?? '') . ' ' . ($r['apellido'] ?? '')),
            $r['celular'] ?? '—',
            $r['programa'],
            $r['ficha'],
            $r['centro_formacion'],
            $r['template'] ?: 'Sin registrar',
            $this->etiquetaEstado($r['estado']),
            $r['company_id'] ?: '—',
            $r['phone_number_id'] ?: '—',
            $r['waMessageId'] ?: '—',
            $r['conversationId'] ?: '—',
        ], $detalle);

        $this->hojaTabla($spreadsheet, 'Detalle de Mensajes', $encabezados, $filas, formatoFecha: 'A');

        // Renombrar la última hoja creada por convención de nombre más claro
        // que "hojaTabla" no puede saber (necesita el ancho especial de IDs).
        $sheet = $spreadsheet->getSheetByName('Detalle de Mensajes');
        if ($sheet) {
            foreach (['I', 'J', 'K', 'L'] as $col) {
                $sheet->getColumnDimension($col)->setWidth(28);
            }
            $sheet->getStyle('A1:L1')->getAlignment()->setWrapText(true);
        }
    }

    private function etiquetaEstado(?string $estado): string
    {
        return match ($estado) {
            'sent' => 'Enviado',
            'delivered' => 'Entregado',
            'read' => 'Leído',
            'failed' => 'Fallido',
            default => $estado ? ucfirst($estado) : 'Sin estado',
        };
    }
}
