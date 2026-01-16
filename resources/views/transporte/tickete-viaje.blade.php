<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Tiquete de Viaje</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 10px;
            font-weight: normal;
        }

        .ticket {
            padding: 10px;
            max-width: 250px;
            margin: 0 auto;
        }

        .header {
            text-align: center;
        }

        .dashed-horizontal {
            border-top: 1px dashed #000;
            width: 100%;
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
            content: "............................................................................................................................................................................................................................";
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
            padding-left: 5px;
        }

        .left-content {
            display: inline-block;
            text-align: left;
            width: 100%;
        }
    </style>
</head>

<body>
    <div class="ticket">
        <p class="header">COOP. PRUEBAS</p>
        <p class="header">Nit. {{ $nit }}</p>
        <p class="header">CLL 4 17-49 P2 TEL: 8373117 POPAYÁN</p>

        <table style="border-collapse: collapse; width: 100%;">
            <tr>
                <td colspan="1">
                    ---------------
                </td>
                <td colspan="1"><span class="title">[Tickete de viaje]</span></td>
                <td colspan="1">
                    ---------------
                </td>
            </tr>
        </table>

        <table>
            <tr>
                <td align="left">Fecha: {{ $fecha }}</td>
                <td align="right">Nº: {{ $numeroTicket }}</td>
            </tr>
        </table>

        <div class="dashed-horizontal"></div>

        <table style="border-collapse: collapse; width: 100%;">
            <tr>
                <td class="fill-dots">Agencia:</td>
                <td colspan="3">{{ $agencia }}</td>
            </tr>
            <tr>
                <td class="fill-dots">Despachador:</td>
                <td colspan="3">{{ $despachador }}</td>
            </tr>
            <tr>
                <td style="color: black; font-weight: bold; font-size: 12px;" class="fill-dots">Hora Salida:</td>
                <td style="color: black; font-weight: bold; font-size: 12px;" colspan="3">{{ $horaSalida }}</td>
            </tr>
            <tr>
                <td class="fill-dots">Ruta:</td>
                <td style="font-size: 10px; white-space: nowrap; width: 80px;" colspan="3">{{ $ruta }}</td>
            </tr>
            <tr>
                <td class="fill-dots">Tarifa:</td>
                <td colspan="3">${{ number_format($tarifa, 0, ',', '.') }}</td>
            </tr>
            <tr>
                <td class="fill-dots">Vehículo:</td>
                <td>{{ $vehiculo }}</td>
            </tr>
            <tr>
                <td class="fill-dots">Placas:</td>
                <td style="font-size: 10px; white-space: nowrap; width: 80px;">{{ $placa }}</td>
            </tr>
            <!-- <tr>
                <td class="fill-dots"># Pasajes:</td>
                <td>{{ $numeroPasajes }}</td>
            </tr> -->
            <tr>

                <td class="fill-dots">TOTAL:</td>
                <td>${{ number_format($valorTotal, 0, ',', '.') }}</td>
            </tr>

            <!-- <tr>
                <td class="fill-dots">Puesto No.:</td>
                <td>{{ $numeroPuesto }}</td>
            </tr> -->
        </table>

        <div class="dashed-horizontal"></div>

        <table style="font-size: 10px; border-collapse: collapse; width: 100%;">
            <tr>
                <td class="fill-dots">Aseguradora:</td>
                <td class="right-align"><span class="left-content">{{ $aseguradora }}</span></td>
            </tr>
            <tr>
                <td class="fill-dots">No. Póliza:</td>
                <td class="right-align"><span class="left-content">{{ $noPoliza }}</span></td>
            </tr>
            <tr>
                <td class="fill-dots">Impresión:</td>
                <td class="right-align"><span class="left-content">{{ $fechaImpresion }}</span></td>
            </tr>
            <tr>
                <td class="fill-dots">Cantidad:</td>
                <td class="right-align"><span class="left-content">{{ $cantidad }}</span></td>
            </tr>
        </table>

        <div class="dashed-horizontal"></div>

        <p class="footer">
            Nota: {{ $nota }}
        </p>

        <p style="text-align: center; margin: 10px 0; font-size: 15px; color: black; font-weight: bold;">
            Vehículo:
            <span style="color: black; font-weight: bold; font-size: 15px;">
                {{ $vehiculo }}
            </span>
        </p>

        <p class="footer">*** Gracias por su compra ***</p>
    </div>
</body>

</html>