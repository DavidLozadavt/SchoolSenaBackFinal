<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Recibo de Venta</title>
    <style>
        @page {
            size: 80mm auto;
            margin: 0;
        }

        @media print {
            body {
                margin: 0;
                padding: 0;
            }

            .ticket {
                page-break-inside: avoid;
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

        .items-table {
            margin: 5px 0;
        }

        .items-table th {
            border-bottom: 1px solid #000;
            padding: 2px 0;
            text-align: left;
            font-weight: bold;
        }

        .items-table td {
            padding: 2px 0;
            font-size: 9px;
        }

        .text-right {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        .bold {
            font-weight: bold;
        }
    </style>
</head>

<body>
    <div class="ticket">
        <p class="header"><strong>{{ $company->razonSocial }}</strong></p>
        <p class="header">NIT: {{ $company->nit }}-{{ $company->digitoVerificacion }}</p>
        <p class="header">{{ $company->direccion }}</p>
        <p class="header">TEL: {{ $company->telefono }}</p>
        <p class="header">{{ $company->email }}</p>

        <div class="dashed-horizontal"></div>

        <table>
            <tr>
                <td colspan="2" style="text-align:center; font-weight:bold;">RECIBO DE VENTA</td>
            </tr>
            <tr>
                <td>Fecha: {{ $transaccion->fechaTransaccion }}</td>
                <td align="right">{{ $transaccion->hora }}</td>
            </tr>
            <tr>
                <td colspan="2" align="right">Nº: {{ $factura->numeroFactura }}</td>
            </tr>
        </table>

        <div class="dashed-horizontal"></div>

        <table>
            <tr>
                <td class="fill-dots">Vendedor:</td>
                <td>{{ $caja->usuario->persona->nombre1 }} {{ $caja->usuario->persona->apellido1 }}</td>
            </tr>
            <tr>
                <td class="fill-dots">Tienda:</td>
                <td>{{ $caja->puntoVenta->sede->nombreSede }}</td>
            </tr>
            <tr>
                <td class="fill-dots">Caja:</td>
                <td>{{ $caja->puntoVenta->nombre }}</td>
            </tr>
        </table>

        <div class="dashed-horizontal"></div>

        <table>
            <tr>
                <td class="fill-dots">Cliente:</td>
                <td>{{ $tercero->nombre }}</td>
            </tr>
            <tr>
                <td class="fill-dots">Identificación:</td>
                <td>{{ $tercero->identificacion }}</td>
            </tr>
            @if($tercero->tipoIdentificacion)
            <tr>
                <td class="fill-dots">Tipo ID:</td>
                <td>{{ $tercero->tipoIdentificacion->detalle }}</td>
            </tr>
            @endif
            @if($tercero->direccion)
            <tr>
                <td class="fill-dots">Dirección:</td>
                <td>{{ $tercero->direccion }}</td>
            </tr>
            @endif
            @if($tercero->email)
            <tr>
                <td class="fill-dots">Correo:</td>
                <td>{{ $tercero->email }}</td>
            </tr>
            @endif
        </table>

        <div class="dashed-horizontal"></div>

        <table class="items-table">
            <thead>
                <tr>
                    <th>Cant.</th>
                    <th>Detalle</th>
                    <th class="text-right">V. Unit</th>
                    <th class="text-right">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($items as $item)
                <tr>
                    <td>{{ $item['cantidad'] ?? 1 }}</td>
                    <td>{{ $item['nombre'] }}</td>
                    <td class="text-right">${{ number_format($item['valor_unitario'], 0, ',', '.') }}</td>
                    <td class="text-right">
                        @if(empty($item['cantidad']) || $item['cantidad'] == 0)
                            ${{ number_format($item['valor_unitario'], 0, ',', '.') }}
                        @else
                            ${{ number_format($item['valor_unitario'] * $item['cantidad'], 0, ',', '.') }}
                        @endif
                    </td>
                </tr>
                @if($item['iva'] !== 'NO')
                <tr>
                    <td colspan="3" style="padding-left: 10px; font-size: 8px;">IVA (19%)</td>
                    <td class="text-right" style="font-size: 8px;">
                        ${{ number_format(($item['valor_unitario'] * ($item['cantidad'] ?? 1)) * 0.19, 0, ',', '.') }}
                    </td>
                </tr>
                @endif
                @endforeach
            </tbody>
        </table>

        <div class="dashed-horizontal"></div>

        <table>
            <tr>
                <td class="fill-dots">Total Bruto:</td>
                <td class="text-right">${{ number_format($factura->valor, 0, ',', '.') }}</td>
            </tr>
            <tr>
                <td class="fill-dots">Descuentos:</td>
                <td class="text-right">$0</td>
            </tr>
            <tr>
                <td class="fill-dots">Sub Total:</td>
                <td class="text-right">${{ number_format($factura->valor, 0, ',', '.') }}</td>
            </tr>
            <tr>
                <td class="fill-dots">I.V.A:</td>
                <td class="text-right">${{ number_format($factura->valorIva, 0, ',', '.') }}</td>
            </tr>
            <tr>
                <td class="fill-dots bold">TOTAL:</td>
                <td class="text-right bold">${{ number_format($factura->valorMasIva, 0, ',', '.') }}</td>
            </tr>
        </table>

        <div class="dashed-horizontal"></div>

        <table>
            <tr>
                <td class="fill-dots">Efectivo:</td>
                <td class="text-right">${{ number_format($pagosEfectivo, 0, ',', '.') }}</td>
            </tr>
            <tr>
                <td class="fill-dots">Transferencia:</td>
                <td class="text-right">${{ number_format($pagosTransferencia, 0, ',', '.') }}</td>
            </tr>
        </table>

        <div class="dashed-horizontal"></div>

        <p class="footer">*** Gracias por su compra ***</p>
        <p class="footer" style="font-size: 8px;">Este documento es un comprobante de venta</p>
    </div>
</body>

</html>
