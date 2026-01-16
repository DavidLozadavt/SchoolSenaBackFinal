<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Tiquete de Viaje</title>
    <style>
        @page {
            size: 80mm auto;
            /* Ajusta a tu impresora térmica (o usa A7) */
            margin: 0;
            /* Quita márgenes de impresión */
        }

        @media print {
            body {
                margin: 0;
                padding: 0;
            }

            .ticket {
                page-break-inside: avoid;
                /* Evita que se corte en dos páginas */
            }
        }

        body {
            font-family: Arial, sans-serif;
            font-size: 10px;
            font-weight: normal;
            margin: 0;
            padding: 0;
        }

        .ticket {
            width: 100%;
            max-width: 250px;
            /* ancho ticket físico aprox. 80mm */
            margin: 0 auto;
            padding: 8px;
            box-sizing: border-box;
        }

        .header {
            text-align: center;
            margin: 0;
            line-height: 1.1;
        }

        .dashed-horizontal {
            border-top: 1px dashed #000;
            width: 100%;
            margin: 3px 0;
        }

        .title-container {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
        }

        .line {
            flex: 1;
            border-top: 1px dashed #000;
        }

        .title {
            margin: 0 5px;
            white-space: nowrap;
        }

        .footer {
            font-size: 10px;
            text-align: center;
            margin-top: 6px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 10px;
            line-height: 1.1;
        }

        td {
            vertical-align: top;
            padding: 1px 0;
        }

        .fill-dots {
            white-space: nowrap;
            text-align: left;
            width: 45%;
            position: relative;
        }

        .fill-dots::after {
            content: "........................................................................";
            display: block;
            overflow: hidden;
            white-space: nowrap;
            position: absolute;
            left: 0;
            bottom: 0;
            width: 100%;
            text-indent: 100%;
        }

        .fill-dots+td {
            background: white;
            position: relative;
            z-index: 1;
            padding-left: 4px;
        }

        .left-content {
            display: inline-block;
            text-align: left;
            width: 100%;
        }

        .watermark {
            position: fixed;
            top: 45%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-30deg);
            font-size: 60px;
            color: rgba(0, 0, 0, 0.08);
            font-weight: 700;
            z-index: 0;
            pointer-events: none;
            white-space: nowrap;
        }
    </style>
</head>

<body>
    <div class="ticket">
        <div class="watermark">{{ $marca ?? 'COPIA' }}</div>

        <p class="header"><strong>COOP. RÁPIDO TAMBO</strong></p>
        <p class="header">Nit. {{ $nit }}</p>
        <p class="header">CLL 4 17-49 P2 TEL: 8373117 POPAYÁN</p>

        <div class="dashed-horizontal"></div>

        <table>
            <tr>
                <td colspan="2" style="text-align:center; font-weight:bold;">Tiquete de viaje - Copia</td>
            </tr>
            <tr>
                <td>Fecha: {{ $fecha }}</td>
                <td align="right">Nº: {{ $numeroTicket }}</td>
            </tr>
        </table>

        <div class="dashed-horizontal"></div>

        <table>
            <tr>
                <td class="fill-dots">Agencia:</td>
                <td>{{ $agencia }}</td>
            </tr>
            <tr>
                <td class="fill-dots">Despachador:</td>
                <td>{{ $despachador }}</td>
            </tr>
            <tr>
                <td style="font-weight: bold;" class="fill-dots">Hora Salida:</td>
                <td style="font-weight: bold;">{{ $horaSalida }}</td>
            </tr>
            <tr>
                <td class="fill-dots">Ruta:</td>
                <td>{{ $ruta }}</td>
            </tr>
            <tr>
                <td class="fill-dots">Tarifa:</td>
                <td>${{ number_format($tarifa, 0, ',', '.') }}</td>
            </tr>
            <tr>
                <td class="fill-dots">Vehículo:</td>
                <td>{{ $vehiculo }}</td>
            </tr>
            <tr>
                <td class="fill-dots">Placas:</td>
                <td>{{ $placa }}</td>
            </tr>
        </table>

        <div class="dashed-horizontal"></div>

        <table>
            <tr>
                <td class="fill-dots">Aseguradora:</td>
                <td>{{ $aseguradora }}</td>
            </tr>
            <tr>
                <td class="fill-dots">No. Póliza:</td>
                <td>{{ $noPoliza }}</td>
            </tr>
            <tr>
                <td class="fill-dots">Impresión:</td>
                <td>{{ $fechaImpresion }}</td>
            </tr>
            <tr>
                <td class="fill-dots">Cantidad:</td>
                <td>{{ $cantidad }}</td>
            </tr>
        </table>

        @if ($cufe)
        <div style="margin-top: 4px; text-align: center; border-top: 1px dashed #666; padding-top: 2px;">
            <div style="margin-top: 4px; text-align: center; border-top: 1px dashed #666; padding-top: 2px;">
                <strong>Factura Electrónica</strong><br>
                <div style="
            word-wrap: break-word;
            white-space: normal;
            font-size: 8px;
            line-height: 1.1;
            max-width: 100%;
            text-align: center;
            margin: 2px auto;
            padding: 0 4px;
        ">
                    CUFE: {{ $cufe }}
                </div>

                @if ($qrPath)
                <img src="{{ $qrPath }}" alt="QR Factura" width="70" height="70" style="margin-top: 3px;">
                @endif
            </div>

            @endif

            <p style="text-align: center; margin: 5px 0; font-size: 11px; font-weight: bold;">
                Vehículo: {{ $vehiculo }}
            </p>

            <p class="footer">*** Gracias por su compra ***</p>
        </div>
</body>

</html>