<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use App\Http\Controllers\FichaController;

class PlaneacionExcelService
{
    private const COLOR_HEADER_BG = '92D050'; // verde cabecera
    private const COLOR_FASE_BG = 'CCFFCC'; // verde claro
    private const COLOR_TITLE_RED = 'FF0000'; // rojo
    private const COLOR_ESTADO_BG = '92D050';

    public function generarExcelPlaneacion($fichaId, $codigoFicha, $instructorLider, $jornada, $programaNombre)
    {
        $controller = new FichaController();
        $response = $controller->getProyectoByFicha($fichaId);

        if ($response->status() !== 200) {
            return null;
        }

        $data = json_decode($response->getContent(), true);
        $proyectoFormativo = $data['proyectoFormativo'] ?? [];
        $fasesProyecto = $data['fasesProyecto'] ?? [];

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Planeación');

        // Orientación y ajuste
        $sheet->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE);
        $sheet->getPageSetup()->setFitToPage(true);
        $sheet->getPageSetup()->setFitToWidth(1);

        // Anchos de columna
        $colWidths = [14, 22, 30, 42, 8, 7, 9, 18, 8, 8, 16, 12, 12, 10];
        foreach ($colWidths as $i => $width) {
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i + 1);
            $sheet->getColumnDimension($colLetter)->setWidth($width);
        }

        // Fila 1
        $sheet->mergeCells('A1:B1');
        $this->setCell($sheet, 'A1', "FICHA $codigoFicha", true, 9);

        $sheet->mergeCells('C1:D1');
        $this->setCell($sheet, 'C1', 'INSTRUCTOR LÍDER', true, 9);

        $sheet->mergeCells('E1:H1');
        $this->setCell($sheet, 'E1', $instructorLider, false, 9, 'left');

        $sheet->mergeCells('I1:N1');
        $this->setCell($sheet, 'I1', 'PLANEACIÓN EJECUTÁNDOSE', true, 11, 'right', null, self::COLOR_TITLE_RED);
        $sheet->getRowDimension(1)->setRowHeight(18);

        // Fila 2
        $sheet->mergeCells('A2:B2');
        $this->setCell($sheet, 'A2', "JORNADA $jornada", true, 9);

        $sheet->mergeCells('I2:N2');
        $prog = $programaNombre ?: ($proyectoFormativo['nombre'] ?? '');
        $this->setCell($sheet, 'I2', "FICHA $codigoFicha  $prog", true, 11, 'right', null, self::COLOR_TITLE_RED);
        $sheet->getRowDimension(2)->setRowHeight(18);

        // Fila 3: Encabezados
        $headers = [
            'FASE DE' . "\n" . 'PROYECTO' . "\n" . 'FORMATIVO',
            'ACTIVIDAD DE PROYECTO' . "\n" . 'FORMATIVO (si el programa' . "\n" . 'es titulada)',
            'COMPETENCIA',
            'RESULTADOS DE APRENDIZAJE',
            'TRIMESTRE',
            'HORAS',
            'NÚMERO DE' . "\n" . 'SESIONES',
            'ACTIVIDAD DE' . "\n" . 'APRENDIZAJE',
            'TOTAL' . "\n" . 'HORAS AL' . "\n" . '100%',
            'TOTAL' . "\n" . 'HORAS AL' . "\n" . '80%',
            'INSTRUCTOR (A)',
            'FECHA DE' . "\n" . 'INICIO',
            'FECHA FIN',
            'ESTADO'
        ];

        foreach ($headers as $i => $h) {
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i + 1);
            $cell = $colLetter . '3';
            $this->setCell($sheet, $cell, $h, true, 8, 'center', self::COLOR_HEADER_BG);
            $this->applyBorder($sheet, $cell, Border::BORDER_MEDIUM);
        }
        $sheet->getRowDimension(3)->setRowHeight(42);

        // Filas de datos
        $currentRow = 4;

        foreach ($fasesProyecto as $fase) {
            $rapRows = [];

            $rapToRows = function ($rap, $actividadDesc, $isFirstOfActivity) use (&$rapRows) {
                $mat = $rap['materia'] ?? [];
                $esPadre = ($rap['idMateriaPadre'] ?? null) === null;

                $formatInstructores = function ($instructores) {
                    if (empty($instructores) || !is_array($instructores)) return '';
                    $nombres = array_map(function($i) { return $i['nombre'] ?? ''; }, $instructores);
                    return implode(', ', $nombres);
                };

                if ($esPadre) {
                    if (!empty($mat['hijas'])) {
                        foreach ($mat['hijas'] as $hijaIdx => $hija) {
                            $rapRows[] = [
                                'actividadDesc' => $actividadDesc,
                                'competencia' => $hijaIdx === 0 ? ($mat['nombre'] ?? null) : null,
                                'resultadoAprendizaje' => $hija['nombre'] ?? null,
                                'instructor' => $formatInstructores($hija['instructores'] ?? []),
                                'trimestre' => $hija['trimestre'] ?? null,
                                'horas' => $hija['horas'] ?? 0,
                                'numeroSesiones' => $hija['numeroSesiones'] ?? 0,
                                'fechaInicio' => $hija['fechaInicio'] ?? null,
                                'fechaFin' => $hija['fechaFin'] ?? null,
                                'estado' => $hija['estado'] ?? 'PENDIENTE'
                            ];
                        }
                    } else {
                        $rapRows[] = [
                            'actividadDesc' => $actividadDesc,
                            'competencia' => $mat['nombre'] ?? null,
                            'resultadoAprendizaje' => null,
                            'instructor' => $formatInstructores($mat['instructores'] ?? $rap['instructores'] ?? []),
                            'trimestre' => $mat['trimestre'] ?? $rap['trimestre'] ?? null,
                            'horas' => $mat['horas'] ?? $rap['horas'] ?? 0,
                            'numeroSesiones' => $mat['numeroSesiones'] ?? $rap['numeroSesiones'] ?? 0,
                            'fechaInicio' => $mat['fechaInicio'] ?? $rap['fechaInicio'] ?? null,
                            'fechaFin' => $mat['fechaFin'] ?? $rap['fechaFin'] ?? null,
                            'estado' => $mat['estado'] ?? $rap['estado'] ?? 'PENDIENTE'
                        ];
                    }
                } else {
                    $rapRows[] = [
                        'actividadDesc' => $actividadDesc,
                        'competencia' => null,
                        'resultadoAprendizaje' => $mat['nombre'] ?? null,
                        'instructor' => $formatInstructores($mat['instructores'] ?? $rap['instructores'] ?? []),
                        'trimestre' => $mat['trimestre'] ?? $rap['trimestre'] ?? null,
                        'horas' => $mat['horas'] ?? $rap['horas'] ?? 0,
                        'numeroSesiones' => $mat['numeroSesiones'] ?? $rap['numeroSesiones'] ?? 0,
                        'fechaInicio' => $mat['fechaInicio'] ?? $rap['fechaInicio'] ?? null,
                        'fechaFin' => $mat['fechaFin'] ?? $rap['fechaFin'] ?? null,
                        'estado' => $mat['estado'] ?? $rap['estado'] ?? 'PENDIENTE'
                    ];
                }
            };

            if (empty($fase['actividades']) && empty($fase['rapsGenerales'])) {
                $rapRows[] = [
                    'actividadDesc' => null, 'competencia' => null, 'resultadoAprendizaje' => null,
                    'instructor' => '', 'trimestre' => null, 'horas' => 0, 'numeroSesiones' => 0,
                    'fechaInicio' => null, 'fechaFin' => null, 'estado' => ''
                ];
            }

            foreach ($fase['actividades'] ?? [] as $act) {
                if (empty($act['faseProyectoRap'])) {
                    $rapRows[] = [
                        'actividadDesc' => $act['descripcionActividad'] ?? null,
                        'competencia' => null, 'resultadoAprendizaje' => null,
                        'instructor' => '', 'trimestre' => null, 'horas' => 0, 'numeroSesiones' => 0,
                        'fechaInicio' => null, 'fechaFin' => null, 'estado' => ''
                    ];
                } else {
                    foreach ($act['faseProyectoRap'] as $idx => $rap) {
                        $rapToRows($rap, $act['descripcionActividad'] ?? null, $idx === 0);
                    }
                }
            }

            foreach ($fase['rapsGenerales'] ?? [] as $rap) {
                $rapToRows($rap, null, true);
            }

            $faseStartRow = $currentRow;
            $faseEndRow = $currentRow + count($rapRows) - 1;

            if (count($rapRows) > 1) {
                $sheet->mergeCells("A{$faseStartRow}:A{$faseEndRow}");
            }
            $this->setCell($sheet, "A{$faseStartRow}", $fase['descripcionFase'] ?? '', true, 8, 'center', self::COLOR_FASE_BG);

            // Merge actividades
            $actStart = $faseStartRow;
            $lastDesc = null;
            $actGroups = [];
            foreach ($rapRows as $i => $r) {
                $absRow = $faseStartRow + $i;
                if ($r['actividadDesc'] !== $lastDesc) {
                    if ($lastDesc !== null) $actGroups[] = ['desc' => $lastDesc, 'start' => $actStart, 'end' => $absRow - 1];
                    $lastDesc = $r['actividadDesc'];
                    $actStart = $absRow;
                }
                if ($i === count($rapRows) - 1) {
                    $actGroups[] = ['desc' => $r['actividadDesc'], 'start' => $actStart, 'end' => $absRow];
                }
            }
            foreach ($actGroups as $ag) {
                if ($ag['end'] > $ag['start']) $sheet->mergeCells("B{$ag['start']}:B{$ag['end']}");
                $this->setCell($sheet, "B{$ag['start']}", $ag['desc'] ?? '', false, 8, 'center');
            }

            // Merge competencias
            $compStart = $faseStartRow;
            $lastComp = null;
            $compGroups = [];
            foreach ($rapRows as $i => $r) {
                $absRow = $faseStartRow + $i;
                if ($r['competencia'] !== null) {
                    if ($lastComp !== null) $compGroups[] = ['comp' => $lastComp, 'start' => $compStart, 'end' => $absRow - 1];
                    $lastComp = $r['competencia'];
                    $compStart = $absRow;
                }
                if ($i === count($rapRows) - 1) {
                    $compGroups[] = ['comp' => $lastComp, 'start' => $compStart, 'end' => $absRow];
                }
            }
            foreach ($compGroups as $cg) {
                if ($cg['end'] > $cg['start']) $sheet->mergeCells("C{$cg['start']}:C{$cg['end']}");
                $this->setCell($sheet, "C{$cg['start']}", $cg['comp'] ?? '', false, 8, 'left');
            }

            foreach ($rapRows as $i => $r) {
                $absRow = $faseStartRow + $i;
                $sheet->getRowDimension($absRow)->setRowHeight(28);

                $this->setCell($sheet, "D{$absRow}", $r['resultadoAprendizaje'] ?? '', false, 8, 'left');
                $this->setCell($sheet, "E{$absRow}", $r['trimestre'] ?? '', false, 8, 'center');
                $this->setCell($sheet, "F{$absRow}", $r['horas'] > 0 ? $r['horas'] : '', false, 8, 'center');
                $this->setCell($sheet, "G{$absRow}", $r['numeroSesiones'] > 0 ? $r['numeroSesiones'] : '', false, 8, 'center');
                $this->setCell($sheet, "H{$absRow}", '', false, 8, 'center'); // Actividad aprendizaje
                $this->setCell($sheet, "I{$absRow}", $r['horas'] > 0 ? $r['horas'] : '', false, 8, 'center');
                $this->setCell($sheet, "J{$absRow}", $r['horas'] > 0 ? $r['horas'] * 0.8 : '', false, 8, 'center');
                $this->setCell($sheet, "K{$absRow}", $r['instructor'], false, 8, 'left');
                $this->setCell($sheet, "L{$absRow}", $r['fechaInicio'] ?? '', false, 8, 'center');
                $this->setCell($sheet, "M{$absRow}", $r['fechaFin'] ?? '', false, 8, 'center');
                
                $bgColor = ($r['estado'] === 'FINALIZADO') ? self::COLOR_ESTADO_BG : null;
                $this->setCell($sheet, "N{$absRow}", $r['estado'] ?? '', false, 8, 'center', $bgColor);
            }

            // Apply borders to the phase
            $range = "A{$faseStartRow}:N{$faseEndRow}";
            $this->applyBorder($sheet, $range, Border::BORDER_THIN);

            $currentRow = $faseEndRow + 1;
        }

        // Guardar
        $filename = "Planeacion_Ficha_{$codigoFicha}_" . time() . ".xlsx";
        $relativePath = "portafolio-documentos/{$filename}";
        $absolutePath = storage_path("app/public/{$relativePath}");

        $dir = dirname($absolutePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $writer = new Xlsx($spreadsheet);
        $writer->save($absolutePath);

        return "storage/" . $relativePath;
    }

    private function setCell($sheet, $cell, $value, $bold = false, $size = 8, $hAlign = 'center', $bgColor = null, $fontColor = null)
    {
        $sheet->setCellValue($cell, $value);
        $style = $sheet->getStyle($cell);
        
        $style->getFont()->setName('Arial')->setSize($size)->setBold($bold);
        if ($fontColor) {
            $style->getFont()->getColor()->setARGB($fontColor);
        }

        $h = $hAlign === 'left' ? Alignment::HORIZONTAL_LEFT : ($hAlign === 'right' ? Alignment::HORIZONTAL_RIGHT : Alignment::HORIZONTAL_CENTER);
        $style->getAlignment()
              ->setHorizontal($h)
              ->setVertical(Alignment::VERTICAL_CENTER)
              ->setWrapText(true);

        if ($bgColor) {
            $style->getFill()
                  ->setFillType(Fill::FILL_SOLID)
                  ->getStartColor()->setARGB($bgColor);
        }
    }

    private function applyBorder($sheet, $range, $borderStyle)
    {
        $sheet->getStyle($range)->getBorders()->applyFromArray([
            'allBorders' => [
                'borderStyle' => $borderStyle,
                'color' => ['argb' => 'FF000000'],
            ],
        ]);
    }
}
