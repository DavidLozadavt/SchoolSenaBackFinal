<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Ticket Reserva Viaje</title>
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
            margin: 5px 0;
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

        .qr-container {
            text-align: center;
            margin: 10px 0;
            padding: 10px 0;
        }

        .qr-container img {
            width: 150px;
            height: 150px;
        }

        .codigo-reserva {
            text-align: center;
            font-weight: bold;
            font-size: 12px;
            margin: 5px 0;
            letter-spacing: 2px;
        }

        .estado-badge {
            text-align: center;
            padding: 5px;
            margin: 10px 0;
            /* border: 2px solid #000; */
            font-weight: bold;
            font-size: 11px;
        }

        .estado-RESERVADO {
            /* background-color: #fff3cd; */
            /* border-color: #ffc107; */
        }

        .estado-VENDIDO {
            background-color: #d4edda;
            border-color: #28a745;
        }

        .estado-PORDESPACHAR {
            background-color: #d1ecf1;
            border-color: #17a2b8;
        }
    </style>
</head>

<body>
    <div class="ticket">
        <p class="header">COOP. RÁPIDO TAMBO</p>
        <p class="header">Nit. {{ $nit }}</p>
        <p class="header">CLL 4 17-49 P2 TEL: 8373117 POPAYÁN</p>

        <table style="border-collapse: collapse; width: 100%;">
            <tr>
                <td colspan="1">
                    ---------------
                </td>
                <td colspan="1"><span class="title">[RESERVA DE VIAJE]</span></td>
                <td colspan="1">
                    ---------------
                </td>
            </tr>
        </table>

        <table>
            <tr>
                <td align="left">Fecha: {{ $fecha }}</td>
            </tr>
        </table>

        <div class="dashed-horizontal"></div>

        <!-- Estado de la reserva -->
        <div class="estado-badge estado-{{ $estado }}">
            ESTADO: {{ $estado }}
        </div>

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
                <td class="fill-dots">Pasajero:</td>
                <td colspan="3">{{ $pasajero }}</td>
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
                <td class="fill-dots">Cantidad:</td>
                <td colspan="3">{{ $cantidad }}</td>
            </tr>
            <tr>
                <td class="fill-dots">Valor Total:</td>
                <td colspan="3" style="font-weight: bold;">${{ number_format($valorTotal, 0, ',', '.') }}</td>
            </tr>
        </table>

        <div class="dashed-horizontal"></div>

        <!-- Código de reserva -->
        <p class="codigo-reserva">CÓDIGO DE RESERVA</p>
        <p class="codigo-reserva" style="font-size: 14px;">{{ $codigoReserva }}</p>

        <div class="dashed-horizontal"></div>

                <!-- @if($logoPath)
            <img src="{{ $logoPath }}" alt="Logo Empresa" style="width: 80px;">
        @endif -->
        @if($qrPath)
            <div style="text-align: center; margin-top: 10px;">
                <img src="{{ $qrPath }}" alt="Código QR" style="width: 100px;">
            </div>
        @endif

        <div class="dashed-horizontal"></div>

        <p class="footer">
            Nota: {{ $nota }}
        </p>

        <p class="footer" style="margin-top: 10px; font-weight: bold;">
            Presente este código al momento de abordar
        </p>

        <p class="footer">*** Gracias por su reserva ***</p>
    </div>
</body>

</html>