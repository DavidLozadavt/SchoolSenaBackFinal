<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page {
            margin: 2.6cm 1.4cm 1.6cm 1.4cm;
        }

        * { box-sizing: border-box; }

        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 10px;
            color: #1f2937;
        }

        /* ---------- Encabezado repetido en cada página ---------- */
        #encabezado {
            position: fixed;
            top: -2.2cm;
            left: 0;
            right: 0;
            height: 2cm;
            border-bottom: 2px solid #0f766e;
            padding-bottom: 6px;
        }
        #encabezado .marca {
            float: left;
            font-size: 13px;
            font-weight: bold;
            color: #0f766e;
        }
        #encabezado .titulo-doc {
            text-align: right;
            font-size: 9px;
            color: #6b7280;
        }

        /* ---------- Portada / título principal ---------- */
        .portada {
            text-align: center;
            padding: 10px 0 18px 0;
            border-bottom: 3px solid #0f766e;
            margin-bottom: 18px;
        }
        .portada h1 {
            font-size: 20px;
            color: #0f766e;
            margin: 0 0 4px 0;
            letter-spacing: 0.5px;
        }
        .portada h2 {
            font-size: 12px;
            font-weight: normal;
            color: #4b5563;
            margin: 0 0 12px 0;
        }
        .meta-info {
            display: block;
            font-size: 9px;
            color: #6b7280;
        }
        .meta-info span { margin: 0 10px; }

        /* ---------- Secciones ---------- */
        .seccion {
            margin-top: 22px;
            margin-bottom: 6px;
        }
        .seccion h3 {
            font-size: 12.5px;
            color: #ffffff;
            background-color: #0f766e;
            padding: 6px 10px;
            border-radius: 3px;
            margin: 0 0 10px 0;
        }

        /* ---------- Resumen ejecutivo (tarjetas) ---------- */
        table.tarjetas { width: 100%; border-collapse: separate; border-spacing: 6px; margin-bottom: 4px; }
        table.tarjetas td {
            width: 16.6%;
            text-align: center;
            padding: 12px 4px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            background-color: #f0fdfa;
        }
        .tarjeta-valor { font-size: 20px; font-weight: bold; color: #0f766e; display: block; }
        .tarjeta-label { font-size: 7.8px; text-transform: uppercase; color: #6b7280; letter-spacing: 0.3px; }

        /* ---------- Tablas de detalle ---------- */
        table.detalle {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 4px;
        }
        table.detalle thead { display: table-header-group; }
        table.detalle th {
            background-color: #115e59;
            color: #ffffff;
            font-size: 9px;
            text-align: left;
            padding: 6px 8px;
            border: 1px solid #0f766e;
        }
        table.detalle td {
            font-size: 9px;
            padding: 5px 8px;
            border: 1px solid #e5e7eb;
        }
        table.detalle tr:nth-child(even) td { background-color: #f3f4f6; }
        table.detalle td.num { text-align: center; }
        table.detalle tfoot td {
            font-weight: bold;
            background-color: #ccfbf1;
            border-top: 2px solid #0f766e;
        }

        .badge {
            display: inline-block;
            padding: 1px 6px;
            border-radius: 8px;
            font-size: 8px;
            color: #ffffff;
        }
        .badge-sent { background-color: #3b82f6; }
        .badge-delivered { background-color: #06b6d4; }
        .badge-read { background-color: #22c55e; }
        .badge-failed { background-color: #ef4444; }
        .badge-default { background-color: #9ca3af; }

        .nota-vacio { color: #9ca3af; font-style: italic; padding: 8px; }
    </style>
</head>
<body>

    <div id="encabezado">
        <span class="marca">SENA · Seguimiento de Aspirantes</span>
        <span class="titulo-doc">Reporte de Estadísticas WhatsApp</span>
        <div style="clear:both;"></div>
    </div>

    <div class="portada">
        <h1>REPORTE DE ESTADÍSTICAS WHATSAPP</h1>
        <h2>Seguimiento de Aspirantes SENA</h2>
        <span class="meta-info">
            <span><strong>Generado:</strong> {{ now()->format('d/m/Y H:i') }}</span>
            <span><strong>Generado por:</strong> {{ $generadoPor ?? 'Sistema' }}</span>
            <span><strong>Período:</strong> {{ $reporte['periodo']['desde'] ?? 'Sin límite inferior' }} — {{ $reporte['periodo']['hasta'] ?? 'Sin límite superior' }}</span>
        </span>
    </div>

    {{-- ================= RESUMEN EJECUTIVO ================= --}}
    <div class="seccion">
        <h3>Indicadores Generales</h3>
        <table class="tarjetas">
            <tr>
                <td><span class="tarjeta-valor">{{ $reporte['totales']['enviados'] }}</span><span class="tarjeta-label">Enviados</span></td>
                <td><span class="tarjeta-valor">{{ $reporte['totales']['entregados'] }}</span><span class="tarjeta-label">Entregados</span></td>
                <td><span class="tarjeta-valor">{{ $reporte['totales']['leidos'] }}</span><span class="tarjeta-label">Leídos</span></td>
                <td><span class="tarjeta-valor">{{ $reporte['totales']['errores'] }}</span><span class="tarjeta-label">Fallidos</span></td>
                <td><span class="tarjeta-valor">{{ $reporte['totales']['totalPlantillasEnviadas'] }}</span><span class="tarjeta-label">Plantillas distintas</span></td>
                <td><span class="tarjeta-valor">{{ $reporte['totales']['totalConversaciones'] }}</span><span class="tarjeta-label">Conversaciones</span></td>
            </tr>
        </table>
    </div>

    {{-- ================= RESUMEN POR ESTADO ================= --}}
    <div class="seccion">
        <h3>Resumen por Estado</h3>
        @php
            $etiquetasEstado = ['sent' => 'Enviado', 'delivered' => 'Entregado', 'read' => 'Leído', 'failed' => 'Fallido'];
            $totalEstados = collect($reporte['estadosDistribucion'])->sum('total');
        @endphp
        <table class="detalle">
            <thead><tr><th>Estado</th><th class="num">Cantidad</th></tr></thead>
            <tbody>
                @forelse ($reporte['estadosDistribucion'] as $row)
                    <tr><td>{{ $etiquetasEstado[$row['estado']] ?? ucfirst($row['estado'] ?? 'Sin estado') }}</td><td class="num">{{ $row['total'] }}</td></tr>
                @empty
                    <tr><td colspan="2" class="nota-vacio">Sin datos en el periodo consultado.</td></tr>
                @endforelse
            </tbody>
            @if (count($reporte['estadosDistribucion']))
                <tfoot><tr><td>Total</td><td class="num">{{ $totalEstados }}</td></tr></tfoot>
            @endif
        </table>
    </div>

    {{-- ================= RESUMEN POR PLANTILLA ================= --}}
    <div class="seccion">
        <h3>Resumen por Plantilla</h3>
        <table class="detalle">
            <thead><tr><th>Plantilla</th><th class="num">Mensajes</th><th class="num">Conversaciones</th></tr></thead>
            <tbody>
                @forelse ($reporte['resumenPorPlantilla'] as $row)
                    <tr><td>{{ $row['plantilla'] }}</td><td class="num">{{ $row['mensajes'] }}</td><td class="num">{{ $row['conversaciones'] }}</td></tr>
                @empty
                    <tr><td colspan="3" class="nota-vacio">Sin datos en el periodo consultado.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- ================= RESUMEN POR PROGRAMA ================= --}}
    <div class="seccion">
        <h3>Resumen por Programa</h3>
        <table class="detalle">
            <thead><tr><th>Programa</th><th class="num">Mensajes enviados</th><th class="num">Conversaciones</th></tr></thead>
            <tbody>
                @forelse ($reporte['resumenPorPrograma'] as $row)
                    <tr><td>{{ $row['programa'] }}</td><td class="num">{{ $row['mensajes'] }}</td><td class="num">{{ $row['conversaciones'] }}</td></tr>
                @empty
                    <tr><td colspan="3" class="nota-vacio">Sin datos en el periodo consultado.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- ================= RESUMEN POR CENTRO ================= --}}
    <div class="seccion">
        <h3>Resumen por Centro de Formación</h3>
        <table class="detalle">
            <thead><tr><th>Centro</th><th class="num">Mensajes enviados</th><th class="num">Conversaciones</th></tr></thead>
            <tbody>
                @forelse ($reporte['resumenPorCentro'] as $row)
                    <tr><td>{{ $row['centro_formacion'] }}</td><td class="num">{{ $row['mensajes'] }}</td><td class="num">{{ $row['conversaciones'] }}</td></tr>
                @empty
                    <tr><td colspan="3" class="nota-vacio">Sin datos en el periodo consultado.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- ================= RESUMEN POR FICHA ================= --}}
    <div class="seccion">
        <h3>Resumen por Ficha</h3>
        <table class="detalle">
            <thead><tr><th>Ficha</th><th>Programa</th><th class="num">Mensajes enviados</th></tr></thead>
            <tbody>
                @forelse ($reporte['resumenPorFicha'] as $row)
                    <tr><td>{{ $row['ficha'] }}</td><td>{{ $row['programa'] }}</td><td class="num">{{ $row['mensajes'] }}</td></tr>
                @empty
                    <tr><td colspan="3" class="nota-vacio">Sin datos en el periodo consultado.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- ================= RESUMEN POR FECHA ================= --}}
    <div class="seccion">
        <h3>Resumen por Fecha</h3>
        <table class="detalle">
            <thead><tr><th>Fecha</th><th class="num">Mensajes enviados</th><th class="num">Conversaciones</th></tr></thead>
            <tbody>
                @forelse ($reporte['resumenPorFecha'] as $row)
                    <tr><td>{{ \Carbon\Carbon::parse($row['fecha'])->format('d/m/Y') }}</td><td class="num">{{ $row['mensajes'] }}</td><td class="num">{{ $row['conversaciones'] }}</td></tr>
                @empty
                    <tr><td colspan="3" class="nota-vacio">Sin datos en el periodo consultado.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- ================= DETALLE DE MENSAJES (última sección) ================= --}}
    <div class="seccion" style="page-break-before: always;">
        <h3>Detalle de Mensajes Enviados</h3>
        <table class="detalle">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Aspirante</th>
                    <th>Celular</th>
                    <th>Programa</th>
                    <th>Ficha</th>
                    <th>Centro</th>
                    <th>Plantilla</th>
                    <th>Estado</th>
                    <th>ID Mensaje (Meta)</th>
                    <th>ID Conversación (Meta)</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($reporte['detalleFacturacion'] as $row)
                    @php
                        $claseBadge = ['sent' => 'badge-sent', 'delivered' => 'badge-delivered', 'read' => 'badge-read', 'failed' => 'badge-failed'][$row['estado']] ?? 'badge-default';
                    @endphp
                    <tr>
                        <td>{{ \Carbon\Carbon::parse($row['fecha_envio'])->format('d/m/Y H:i') }}</td>
                        <td>{{ trim(($row['nombre'] ?? '') . ' ' . ($row['apellido'] ?? '')) }}</td>
                        <td>{{ $row['celular'] ?? '—' }}</td>
                        <td>{{ $row['programa'] }}</td>
                        <td>{{ $row['ficha'] }}</td>
                        <td>{{ $row['centro_formacion'] }}</td>
                        <td>{{ $row['template'] ?: 'Sin registrar' }}</td>
                        <td><span class="badge {{ $claseBadge }}">{{ $etiquetasEstado[$row['estado']] ?? $row['estado'] }}</span></td>
                        <td style="font-size:7.5px;">{{ $row['waMessageId'] ?: '—' }}</td>
                        <td style="font-size:7.5px;">{{ $row['conversationId'] ?: '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="10" class="nota-vacio">Sin mensajes registrados en el periodo consultado.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <script type="text/php">
        if (isset($pdf)) {
            $font = $fontMetrics->getFont("Arial", "normal");
            $size = 8;
            $y = 812;
            $pdf->text(40, $y, "Reporte generado el {{ now()->format('d/m/Y H:i') }} · SENA - Seguimiento de Aspirantes", $font, $size, array(0.42, 0.45, 0.5));
            $pdf->text(500, $y, "Página {PAGE_NUM} de {PAGE_COUNT}", $font, $size, array(0.42, 0.45, 0.5));
        }
    </script>
</body>
</html>
