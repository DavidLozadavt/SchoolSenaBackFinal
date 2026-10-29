{{-- Recibo de venta de productos (Jobs\GeneratePdfAndSendEmail). Cálculos sin cambios respecto a la versión anterior. --}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Recibo de venta N.º {{ $factura->numeroFactura }}</title>
    @include('pdf.partials.documento-estilos')
</head>
<body>
    @include('pdf.partials.documento-pie', [
        'emisorNombre' => $company->razonSocial,
        'tipoDocumento' => 'Recibo de venta',
        'numero' => $factura->numeroFactura,
    ])

    @include('pdf.partials.documento-encabezado', [
        'company' => $company,
        'tipoDocumento' => 'Recibo de venta',
        'numero' => $factura->numeroFactura,
        'meta' => [
            ['Fecha', trim($transaccion->fechaTransaccion . ' ' . $transaccion->hora)],
            ['Punto de venta', $caja->puntoVenta->sede->nombreSede ?? null],
            ['Caja', $caja->puntoVenta->nombre ?? null],
            ['Vendedor', trim(($caja->usuario->persona->nombre1 ?? '') . ' ' . ($caja->usuario->persona->apellido1 ?? ''))],
        ],
    ])

    <table class="partes">
        <tr>
            <td>
                <p class="bloque-titulo">Cliente</p>
                <table class="campos">
                    <tr><td class="etq">Nombre</td><td class="val">{{ $tercero->nombre }}</td></tr>
                    <tr><td class="etq">Identificación</td><td class="val">{{ $tercero->tipoIdentificacion->detalle ?? 'No aplica' }} {{ $tercero->identificacion }}</td></tr>
                    <tr><td class="etq">Dirección</td><td class="val">{{ $tercero->direccion ?: '—' }}</td></tr>
                    <tr><td class="etq">País</td><td class="val">Colombia</td></tr>
                    <tr><td class="etq">Correo</td><td class="val">{{ $tercero->email ?: '—' }}</td></tr>
                </table>
            </td>
            <td>
                <p class="bloque-titulo">Pagos recibidos</p>
                <table class="campos">
                    <tr><td class="etq">Efectivo</td><td class="val">$ {{ number_format($pagosEfectivo, 0, ',', '.') }}</td></tr>
                    <tr><td class="etq">Transferencia</td><td class="val">$ {{ number_format($pagosTransferencia, 0, ',', '.') }}</td></tr>
                </table>
            </td>
        </tr>
    </table>

    <table class="conceptos">
        <thead>
            <tr>
                <th style="width: 9%;" class="centro">Cant.</th>
                <th>Detalle</th>
                <th style="width: 17%;" class="num">Valor unitario</th>
                <th style="width: 15%;" class="num">IVA</th>
                <th style="width: 17%;" class="num">Valor total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($items as $item)
                <tr>
                    <td class="centro">{{ $item['cantidad'] ?? 1 }}</td>
                    <td>{{ $item['nombre'] }}</td>
                    <td class="num">$ {{ number_format($item['valor_unitario'], 0, ',', '.') }}</td>
                    <td class="num">$
                        @if($item['iva'] === 'NO')
                            0
                        @else
                            {{ number_format(($item['valor_unitario'] * ($item['cantidad'] ?? 1)) * 0.19, 0, ',', '.') }}
                        @endif
                    </td>
                    <td class="num">$
                        @if(empty($item['cantidad']) || $item['cantidad'] == 0)
                            {{ number_format($item['valor_unitario'], 0, ',', '.') }}
                        @else
                            {{ number_format($item['valor_unitario'] * $item['cantidad'], 0, ',', '.') }}
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="resumen">
        <tr>
            <td style="width: 52%;"></td>
            <td style="width: 48%;">
                <table class="totales">
                    <tr><td class="etq">Total bruto</td><td class="val">$ {{ number_format($factura->valor, 0, ',', '.') }}</td></tr>
                    <tr><td class="etq">Descuentos</td><td class="val">$ 0</td></tr>
                    <tr><td class="etq">Subtotal</td><td class="val">$ {{ number_format($factura->valor, 0, ',', '.') }}</td></tr>
                    <tr><td class="etq">IVA</td><td class="val">$ {{ number_format($factura->valorIva, 0, ',', '.') }}</td></tr>
                    <tr class="total"><td>Total (COP)</td><td class="val">$ {{ number_format($factura->valorMasIva, 0, ',', '.') }}</td></tr>
                </table>
            </td>
        </tr>
    </table>

    <p style="font-size: 8pt; color: #64748b;">Gracias por su compra. Conserve este documento para sus registros.</p>
</body>
</html>
