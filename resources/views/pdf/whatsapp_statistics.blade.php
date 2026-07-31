<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 1.5cm; }
        body { font-family: Arial, Helvetica, sans-serif; font-size: 10px; }
        h2 { text-align: center; margin-bottom: 4px; }
        p.subtitle { text-align: center; color: #666; margin-top: 0; margin-bottom: 20px; }
        h3 { margin-top: 24px; margin-bottom: 8px; color: #16a34a; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        th, td { border: 1px solid #999; padding: 5px 8px; text-align: left; }
        th { background-color: #16a34a; color: #fff; }
        .kpis { width: 100%; margin-bottom: 10px; }
        .kpis td { border: none; text-align: center; padding: 10px; }
        .kpi-box { border: 1px solid #ddd; border-radius: 6px; padding: 10px; }
        .kpi-value { font-size: 18px; font-weight: bold; color: #16a34a; }
        .kpi-label { font-size: 9px; text-transform: uppercase; color: #777; }
    </style>
</head>
<body>
    <h2>Estadísticas de Mensajes WhatsApp — SENA</h2>
    <p class="subtitle">
        Periodo: {{ $reporte['periodo']['desde'] ?? 'Sin límite' }} — {{ $reporte['periodo']['hasta'] ?? 'Sin límite' }}
        &nbsp;|&nbsp; Generado el {{ now()->format('d/m/Y H:i') }}
    </p>

    <table class="kpis">
        <tr>
            <td class="kpi-box"><div class="kpi-value">{{ $reporte['totales']['enviados'] }}</div><div class="kpi-label">Enviados</div></td>
            <td class="kpi-box"><div class="kpi-value">{{ $reporte['totales']['entregados'] }}</div><div class="kpi-label">Entregados</div></td>
            <td class="kpi-box"><div class="kpi-value">{{ $reporte['totales']['leidos'] }}</div><div class="kpi-label">Leídos</div></td>
            <td class="kpi-box"><div class="kpi-value">{{ $reporte['totales']['errores'] }}</div><div class="kpi-label">Con error</div></td>
        </tr>
    </table>

    <h3>Detalle por plantilla</h3>
    <table>
        <thead><tr><th>Plantilla</th><th>Total</th></tr></thead>
        <tbody>
            @forelse ($reporte['porPlantilla'] as $row)
                <tr><td>{{ $row['plantilla'] }}</td><td>{{ $row['total'] }}</td></tr>
            @empty
                <tr><td colspan="2">Sin datos en el periodo consultado.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h3>Detalle por programa</h3>
    <table>
        <thead><tr><th>Programa</th><th>Total</th></tr></thead>
        <tbody>
            @forelse ($reporte['porPrograma'] as $row)
                <tr><td>{{ $row['programa'] }}</td><td>{{ $row['total'] }}</td></tr>
            @empty
                <tr><td colspan="2">Sin datos en el periodo consultado.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
