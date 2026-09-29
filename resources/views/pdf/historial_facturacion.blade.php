{{--
    Reporte PDF del Historial de Facturación.
    Incluye: resumen de KPIs, filtros utilizados, detalle completo,
    fecha de generación, usuario generador y totales generales.
--}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Historial de Facturación</title>
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { font-size: 9px; color: #1f2937; margin: 0; }
        h1 { font-size: 16px; margin: 0 0 2px; color: #111827; }
        .sub { font-size: 9px; color: #6b7280; margin-bottom: 10px; }
        .kpis { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        .kpis td {
            border: 1px solid #e5e7eb; padding: 6px 8px; width: 14.28%;
            text-align: center; background: #f9fafb;
        }
        .kpis .etiqueta { font-size: 8px; color: #6b7280; display: block; }
        .kpis .valor { font-size: 12px; font-weight: bold; color: #111827; }
        .seccion { font-size: 11px; font-weight: bold; margin: 10px 0 4px; color: #111827; }
        table.datos { width: 100%; border-collapse: collapse; }
        table.datos th {
            background: #1b84ff; color: #fff; padding: 5px 4px;
            font-size: 8px; text-align: left; border: 1px solid #1b84ff;
        }
        table.datos td { padding: 4px; border: 1px solid #e5e7eb; font-size: 8px; }
        table.datos tr:nth-child(even) td { background: #f9fafb; }
        .totales td { font-weight: bold; background: #eef2ff !important; }
        .filtros td { border: 1px solid #e5e7eb; padding: 4px 6px; font-size: 8px; }
        .badge { padding: 1px 4px; border-radius: 3px; font-size: 7px; color: #fff; }
        .b-APPROVED, .b-APROBADA { background: #17c653; }
        .b-PENDING, .b-PENDIENTE, .b-PAGO_REALIZADO, .b-MANUAL { background: #f6b100; }
        .b-DECLINED, .b-RECHAZADA, .b-ERROR, .b-VOIDED { background: #f8285a; }
        .pie { margin-top: 10px; font-size: 8px; color: #6b7280; }
    </style>
</head>
<body>
    @php
        $kpis = $reporte['kpis'];
        $moneda = fn ($valor) => '$' . number_format((float) $valor, 0, ',', '.');
        $totalValor = collect($reporte['detalle'])->sum(fn ($r) => (float) ((array) $r)['valor']);
        $totalMensajes = collect($reporte['detalle'])->sum(fn ($r) => (int) ((array) $r)['cantidadMensajes']);
    @endphp

    <h1>Historial de Facturación</h1>
    <div class="sub">
        Generado el {{ $reporte['generadoEn'] }} por {{ $generadoPor }}
    </div>

    <div class="seccion">Resumen de indicadores</div>
    <table class="kpis">
        <tr>
            <td><span class="etiqueta">Total de ventas</span><span class="valor">{{ $kpis['totalVentas'] }}</span></td>
            <td><span class="etiqueta">Total recaudado</span><span class="valor">{{ $moneda($kpis['totalRecaudado']) }}</span></td>
            <td><span class="etiqueta">Pagos aprobados</span><span class="valor">{{ $kpis['pagosAprobados'] }}</span></td>
            <td><span class="etiqueta">Pagos pendientes</span><span class="valor">{{ $kpis['pagosPendientes'] }}</span></td>
            <td><span class="etiqueta">Pagos rechazados</span><span class="valor">{{ $kpis['pagosRechazados'] }}</span></td>
            <td><span class="etiqueta">Mensajes vendidos</span><span class="valor">{{ number_format($kpis['mensajesVendidos'], 0, ',', '.') }}</span></td>
            <td><span class="etiqueta">Promedio por compra</span><span class="valor">{{ $moneda($kpis['promedioCompra']) }}</span></td>
        </tr>
    </table>

    <div class="seccion">Filtros utilizados</div>
    <table class="filtros" style="width:100%; border-collapse: collapse; margin-bottom: 10px;">
        @foreach ($reporte['filtros'] as $filtro)
            <tr>
                <td style="width: 25%; color:#6b7280;">{{ $filtro['campo'] }}</td>
                <td>{{ $filtro['valor'] }}</td>
            </tr>
        @endforeach
    </table>

    <div class="seccion">Detalle de transacciones ({{ count($reporte['detalle']) }})</div>
    <table class="datos">
        <thead>
            <tr>
                <th>Fecha del pago</th>
                <th>Usuario</th>
                <th>Empresa</th>
                <th>Plan</th>
                <th>Mensajes</th>
                <th>Valor</th>
                <th>Método</th>
                <th>Referencia</th>
                <th>Transaction ID</th>
                <th>Estado pago</th>
                <th>Estado solicitud</th>
                <th>Aprobado por</th>
                <th>Fecha aprobación</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($reporte['detalle'] as $registro)
                @php $r = (array) $registro; @endphp
                <tr>
                    <td>{{ $r['fechaPago'] }}</td>
                    <td>{{ $r['usuarioNombre'] ?: $r['usuarioEmail'] }}</td>
                    <td>{{ $r['empresaNombre'] ?: '—' }}</td>
                    <td>{{ $r['planNombre'] }}</td>
                    <td>{{ number_format((int) $r['cantidadMensajes'], 0, ',', '.') }}</td>
                    <td>{{ $moneda($r['valor']) }}</td>
                    <td>{{ $r['pagoMetodo'] ?: $r['metodoPago'] }}</td>
                    <td>{{ $r['referenciaWompi'] ?: '—' }}</td>
                    <td>{{ $r['transactionId'] ?: '—' }}</td>
                    <td><span class="badge b-{{ $r['estadoPago'] }}">{{ $r['estadoPago'] }}</span></td>
                    <td><span class="badge b-{{ $r['estadoSolicitud'] }}">{{ $r['estadoSolicitud'] }}</span></td>
                    <td>{{ $r['aprobadoPorNombre'] ?: ($r['aprobadoPorEmail'] ?: '—') }}</td>
                    <td>{{ $r['fechaAprobacion'] ?: '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="13" style="text-align:center; padding:12px; color:#6b7280;">
                    No hay transacciones para los filtros aplicados.
                </td></tr>
            @endforelse

            @if (count($reporte['detalle']) > 0)
                <tr class="totales">
                    <td colspan="4" style="text-align:right;">TOTALES GENERALES</td>
                    <td>{{ number_format($totalMensajes, 0, ',', '.') }}</td>
                    <td>{{ $moneda($totalValor) }}</td>
                    <td colspan="7"></td>
                </tr>
            @endif
        </tbody>
    </table>

    <div class="pie">
        Reporte de solo lectura generado por el módulo Historial de Facturación.
    </div>
</body>
</html>
