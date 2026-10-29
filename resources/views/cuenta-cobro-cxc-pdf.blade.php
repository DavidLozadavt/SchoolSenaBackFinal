{{-- Cuenta de cobro de cuentas por cobrar (ProductoEmpresarialController::generarCuentaCobroPagoCuentasPorCobrar). Valores sin cambios. --}}
@php
    $numero = 'CXC-' . str_pad($transaccion->id ?? '001', 6, '0', STR_PAD_LEFT);
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Cuenta de cobro {{ $numero }}</title>
    @include('pdf.partials.documento-estilos')
</head>
<body>
    @include('pdf.partials.documento-pie', [
        'emisorNombre' => 'Virtual Technology',
        'tipoDocumento' => 'Cuenta de cobro',
        'numero' => $numero,
    ])

    @include('pdf.partials.documento-encabezado', [
        'emisorNombre' => 'Virtual Technology',
        'emisorLineas' => ['Prestación de servicios de TI', 'NIT 10296037-9', 'Cel. 315 661 4275', 'Popayán, Colombia'],
        'logoArchivo' => public_path('default/logoweb.png'),
        'tipoDocumento' => 'Cuenta de cobro',
        'numero' => $numero,
        'meta' => [
            ['Fecha', date('d/m/Y')],
        ],
    ])

    <table class="partes">
        <tr>
            <td>
                <p class="bloque-titulo">Cliente</p>
                <table class="campos">
                    <tr><td class="etq">Nombre</td><td class="val">{{ $tercero->nombre ?? 'N/A' }}</td></tr>
                    <tr><td class="etq">NIT / CC</td><td class="val">{{ $tercero->identificacion ?? 'N/A' }}</td></tr>
                    <tr><td class="etq">Teléfono</td><td class="val">{{ $tercero->telefono ?? 'N/A' }}</td></tr>
                    <tr><td class="etq">Correo</td><td class="val">{{ $tercero->email ?? 'N/A' }}</td></tr>
                </table>
            </td>
            <td>
                <p class="bloque-titulo">Información para el pago</p>
                <table class="campos">
                    <tr><td class="etq">Banco</td><td class="val">Bancolombia</td></tr>
                    <tr><td class="etq">Tipo de cuenta</td><td class="val">Ahorros</td></tr>
                    <tr><td class="etq">N.º de cuenta</td><td class="val">XXXX-XXXX-XXXX</td></tr>
                    <tr><td class="etq">Titular</td><td class="val">David Eduardo Lozada Cerón</td></tr>
                </table>
            </td>
        </tr>
    </table>

    <table class="conceptos">
        <thead>
            <tr>
                <th style="width: 9%;" class="centro">Cant.</th>
                <th>Descripción</th>
                <th style="width: 17%;" class="num">Valor unitario</th>
                <th style="width: 17%;" class="num">Valor total</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td class="centro">1</td>
                <td>
                    Servicios de tecnología según factura N.º {{ $transaccion->numFacturaInicial ?? 'N/A' }}<br>
                    <span style="color:#64748b; font-size:8pt;">Período: {{ date('d/m/Y', strtotime($transaccion->fechaTransaccion ?? 'now')) }}</span>
                </td>
                <td class="num">$ {{ number_format($transaccion->valor ?? 0, 0, ',', '.') }}</td>
                <td class="num">$ {{ number_format($transaccion->valor ?? 0, 0, ',', '.') }}</td>
            </tr>
        </tbody>
    </table>

    <table class="resumen">
        <tr>
            <td style="width: 52%; padding-right: 6mm;">
                <div class="nota">
                    <strong>Condiciones de pago</strong><br>
                    Esta cuenta de cobro debe pagarse dentro de los 30 días calendario siguientes a su emisión.
                    Envíe el comprobante de pago a pagos@virtualt.org. Para aclaraciones, comuníquese al 315 661 4275.
                </div>
            </td>
            <td style="width: 48%;">
                <table class="totales">
                    <tr><td class="etq">Subtotal</td><td class="val">$ {{ number_format($transaccion->valor ?? 0, 0, ',', '.') }}</td></tr>
                    <tr><td class="etq">IVA (0 %)</td><td class="val">$ 0</td></tr>
                    <tr class="total"><td>Total a pagar (COP)</td><td class="val">$ {{ number_format($transaccion->valor ?? 0, 0, ',', '.') }}</td></tr>
                </table>
            </td>
        </tr>
    </table>

    <table class="firmas">
        <tr>
            <td>
                <div class="firma-linea">
                    <span class="firma-nombre">David Eduardo Lozada Cerón</span><br>
                    C.C. 10296037 de Popayán<br>
                    Gerente – Virtual Technology
                </div>
            </td>
            <td>
                <div class="firma-linea">
                    <span class="firma-nombre">Recibí conforme</span><br>
                    {{ $tercero->nombre ?? 'Cliente' }}<br>
                    Firma y sello
                </div>
            </td>
        </tr>
    </table>
</body>
</html>
