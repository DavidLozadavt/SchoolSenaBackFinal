{{-- Recibo de pago de vacaciones (Console\Commands\SendNotificationVacacionNomina; actualmente desactivado). --}}
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
            ['Medio de pago', 'Transferencia bancaria'],
        ],
    ])

    <table class="conceptos">
        <thead>
            <tr>
                <th style="width: 9%;" class="centro">Cant.</th>
                <th>Detalle</th>
                <th style="width: 17%;" class="num">Valor unitario</th>
                <th style="width: 12%;" class="num">IVA</th>
                <th style="width: 17%;" class="num">Valor total</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td class="centro">1</td>
                <td>Vacaciones</td>
                <td class="num">$ {{ number_format($solicitud->valor, 0, ',', '.') }}</td>
                <td class="num">$ 0</td>
                <td class="num">$ {{ number_format($solicitud->valor, 0, ',', '.') }}</td>
            </tr>
        </tbody>
    </table>

    <table class="resumen">
        <tr>
            <td style="width: 52%;"></td>
            <td style="width: 48%;">
                <table class="totales">
                    <tr><td class="etq">Total bruto</td><td class="val">$ {{ number_format($solicitud->valor, 0, ',', '.') }}</td></tr>
                    <tr><td class="etq">Descuentos</td><td class="val">$ 0</td></tr>
                    <tr><td class="etq">Subtotal</td><td class="val">$ {{ number_format($solicitud->valor, 0, ',', '.') }}</td></tr>
                    <tr><td class="etq">IVA</td><td class="val">$ 0</td></tr>
                    <tr class="total"><td>Total (COP)</td><td class="val">$ {{ number_format($solicitud->valor, 0, ',', '.') }}</td></tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
