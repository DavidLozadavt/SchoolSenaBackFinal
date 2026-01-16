<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Planilla de Viaje</title>
    <style>
        @page {
            margin: 10px;
            size: auto;
        }

        body {
            font-family: "Courier New", monospace;
            font-size: 10px;
            margin: 0;
            padding: 0;
        }

        .ticket {
            padding: 5px;
            width: 250px;
            margin: 0 auto;
            page-break-inside: avoid;
        }

        .header {
            text-align: center;
            font-weight: bold;
        }

        .dashed-horizontal {
            border-top: 1px dashed #000;
            width: 100%;
            margin: 3px 0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        td {
            vertical-align: top;
        }

        .fill-dots {
            white-space: nowrap;
            text-align: left;
            width: 50%;
            position: relative;
        }

        .fill-dots::after {
            content: ".............................................................";
            display: block;
            overflow: hidden;
            white-space: nowrap;
            position: absolute;
            left: 0;
            bottom: 0;
            width: 100%;
            text-indent: 100%;
        }

        .no-wrap {
            white-space: nowrap;
            word-break: keep-all;
        }

        .footer {
            font-size: 10px;
            text-align: center;
        }

        /* Evita saltos de página */
        table, tr, td, p, div {
            page-break-inside: avoid;
        }

        /* 🔹 Titulares centrados con guiones */
        .section-title {
            text-align: center;
            font-weight: bold;
            white-space: nowrap;
        }

        .section-title::before,
        .section-title::after {
            content: "-------";
            display: inline-block;
            vertical-align: middle;
            margin: 0 3px;
        }
    </style>
</head>
<body>
    <div class="ticket">
        <p class="header">COOP. RÁPIDO TAMBO</p>
        <p class="header">Nit. {{ $nit }}</p>
        <p class="header">CLL 4 17-49 P2 Tel: 312312 Popayán</p>

        <p class="section-title">[ Planilla de Viaje ]</p>

        <table>
            <tr>
                <td align="left">Fecha: {{ $fecha }}</td>
                <td align="right">No: {{ $numeroPlanilla }}</td>
            </tr>
        </table>

        <div class="dashed-horizontal"></div>

        <table>
            <tr><td class="fill-dots">Agencia:</td><td>{{ $agencia }}</td></tr>
            <tr><td class="fill-dots">Despachador:</td><td>{{ $despachador }}</td></tr>
            <tr><td class="fill-dots">Trayecto:</td><td class="no-wrap">{{ $ruta }}</td></tr>
            <tr><td class="fill-dots">Hora Salida:</td><td>{{ $horaSalida }}</td></tr>
            <tr><td class="fill-dots">Vehículo:</td><td>{{ $vehiculo }}</td></tr>
            <tr><td class="fill-dots">Placa:</td><td>{{ $placa }}</td></tr>
            <tr><td class="fill-dots">Motorista:</td><td>{{ $motorista }}</td></tr>
            <tr><td class="fill-dots">Propietario:</td><td>{{ $propietario }}</td></tr>
        </table>

        <div class="dashed-horizontal"></div>

        <table>
            <tr>
                <td>Tiquetes / Pasajes</td>
                <td>Cant</td>
                <td>Valor</td>
            </tr>
        </table>
        <div class="dashed-horizontal"></div>

        <table>
            <tr>
                <td>---------------------</td>
                <td>Total$</td>
                <td>{{ $valor }}</td>
            </tr>
        </table>

        <p class="section-title">[ GASTOS Y DEDUCCIONES ]</p>

        <table>
            <tr>
                <td>Cpt Descripción</td>
                <td align="right">Valor</td>
            </tr>
            @forelse($descuentos as $d)
            <tr>
                <td>{{ $d->descuento->nombre ?? 'N/A' }}</td>
                <td align="right">${{ number_format($d->valor, 0, ',', '.') }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="2">No hay deducciones</td>
            </tr>
            @endforelse
        </table>

        <div class="dashed-horizontal"></div>

        <table>
            <tr>
                <td>----------</td>
                <td>Total Deducciones$</td>
                <td align="right">{{ $totalDeducciones }}</td>
            </tr>
            <tr>
                <td>----------</td>
                <td>Recaudo Efectivo$</td>
                <td align="right">{{ $recaudoEfectivo }}</td>
                 <!-- <td align="right">{{ $totalDeducciones }}</td> -->
        </table>

        <p class="footer">Impres: {{ $fechaImpresion }}</p>
    </div>
</body>
</html>
