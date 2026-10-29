{{-- Cuenta de cobro de Virtual Technology por abono de contrato (ProductoEmpresarialController). Valores sin cambios. --}}
@php
    $archivoFirma = public_path('default/firmaG.png');
    // Ruta local: dompdf no admite data:// en esta instalación.
    $firmaData = is_file($archivoFirma) ? realpath($archivoFirma) : null;
    $fechaDocumento = \Carbon\Carbon::parse($fechaActual)->format('d/m/Y');
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Cuenta de cobro</title>
    @include('pdf.partials.documento-estilos')
</head>
<body>
    @include('pdf.partials.documento-pie', [
        'emisorNombre' => 'Virtual Technology',
        'tipoDocumento' => 'Cuenta de cobro',
        'numero' => $pago2->id ?? $transaccion->id,
    ])

    @include('pdf.partials.documento-encabezado', [
        'emisorNombre' => 'Virtual Technology',
        'emisorLineas' => ['Prestación de servicios de TI', 'NIT 10296037-9', 'Cel. 315 661 4275', 'Popayán, Colombia'],
        'logoArchivo' => public_path('default/logoweb.png'),
        'tipoDocumento' => 'Cuenta de cobro',
        'numero' => $pago2->id ?? $transaccion->id,
        'meta' => [
            ['Fecha', $fechaDocumento],
        ],
    ])

    <table class="partes">
        <tr>
            <td>
                <p class="bloque-titulo">Cliente</p>
                <table class="campos">
                    <tr><td class="etq">Nombre</td><td class="val">{{ $tercero->nombre }}</td></tr>
                    <tr><td class="etq">NIT / CC</td><td class="val">{{ $tercero->identificacion }}</td></tr>
                    <tr><td class="etq">Teléfono</td><td class="val">{{ $tercero->telefono ?: '—' }}</td></tr>
                </table>
            </td>
            <td>
                <p class="bloque-titulo">Información para el pago</p>
                <table class="campos">
                    <tr><td class="etq">Banco</td><td class="val">Bancolombia</td></tr>
                    <tr><td class="etq">Tipo de cuenta</td><td class="val">Ahorros</td></tr>
                    <tr><td class="etq">N.º de cuenta</td><td class="val">{{ $nCuenta }}</td></tr>
                    <tr><td class="etq">Titular</td><td class="val">David Eduardo Lozada Cerón</td></tr>
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
                <th style="width: 17%;" class="num">Valor total</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td class="centro">1</td>
                <td>{{ $contrato->descripcion }}. Correspondiente al {{ $pago->porcentaje }}% del valor total de $ {{ number_format($transaccion->valor, 0, ',', '.') }} COP.</td>
                <td class="num">$ {{ number_format($pago2->valor, 0, ',', '.') }}</td>
                <td class="num">$ {{ number_format($pago2->valor, 0, ',', '.') }}</td>
            </tr>
        </tbody>
    </table>

    <table class="resumen">
        <tr>
            <td style="width: 52%;"></td>
            <td style="width: 48%;">
                <table class="totales">
                    <tr class="total"><td>Total (COP)</td><td class="val">$ {{ number_format($pago2->valor, 0, ',', '.') }}</td></tr>
                </table>
            </td>
        </tr>
    </table>

    <table class="firmas">
        <tr>
            <td>
                @if($firmaData)
                    <img src="{{ $firmaData }}" class="firma-imagen" alt="">
                @endif
                <div class="firma-linea">
                    <span class="firma-nombre">David Eduardo Lozada Cerón</span><br>
                    C.C. 10296037 de Popayán, Cauca<br>
                    Gerente
                </div>
            </td>
            <td>
                <div class="firma-linea">
                    <span class="firma-nombre">Recibí conforme</span><br>
                    {{ $tercero->nombre }}<br>
                    Firma y sello
                </div>
            </td>
        </tr>
    </table>
</body>
</html>
