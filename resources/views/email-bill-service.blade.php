{{-- Recibo de venta de servicios (Jobs\GenerateAndSendPdf). Cálculos sin cambios respecto a la versión anterior. --}}
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
            ['Fecha', $pago->fechaReg],
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
                    <tr><td class="etq">Identificación</td><td class="val">{{ $tercero->tipoIdentificacion->detalle ?? '' }} {{ $tercero->identificacion }}</td></tr>
                    <tr><td class="etq">Dirección</td><td class="val">{{ $tercero->direccion ?: '—' }}</td></tr>
                    <tr><td class="etq">País</td><td class="val">Colombia</td></tr>
                    <tr><td class="etq">Correo</td><td class="val">{{ $tercero->email ?: '—' }}</td></tr>
                </table>
            </td>
            <td>
                <p class="bloque-titulo">Forma de pago</p>
                <table class="campos">
                    <tr><td class="etq">Forma</td><td class="val">{{ $transaccion->tipoPago->detalleTipoPago ?? '—' }}</td></tr>
                    <tr><td class="etq">Medio</td><td class="val">{{ $pago->medioPago->detalleMedioPago ?? '—' }}</td></tr>
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
            @foreach($detalles as $detalle)
                <tr>
                    <td class="centro">1</td>
                    <td>{{ $detalle->servicio->nombre }}</td>
                    <td class="num">$ {{ number_format($detalle->valor, 0, ',', '.') }}</td>
                    <td class="num">$ {{ number_format($detalle->valorIvaServicio, 0, ',', '.') }}</td>
                    <td class="num">$ {{ number_format($detalle->valor + $detalle->valorIvaServicio, 0, ',', '.') }}</td>
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

    <p style="font-size: 8pt; color: #64748b;">Gracias por su pago. Conserve este documento para sus registros.</p>
</body>
</html>
