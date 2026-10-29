{{-- Comprobante de pago a responsable (Jobs\SendEmailToResponsable). Valores sin cambios respecto a la versión anterior. --}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Comprobante de pago N.º {{ $transaccion->id }}</title>
    @include('pdf.partials.documento-estilos')
</head>
<body>
    @include('pdf.partials.documento-pie', [
        'emisorNombre' => $company->razonSocial,
        'tipoDocumento' => 'Comprobante de pago',
        'numero' => $transaccion->id,
    ])

    @include('pdf.partials.documento-encabezado', [
        'company' => $company,
        'tipoDocumento' => 'Comprobante de pago',
        'numero' => $transaccion->id,
        'meta' => [
            ['Fecha', trim($transaccion->fechaTransaccion . ' ' . $transaccion->hora)],
            ['Registrado por', trim(($personaAuth->nombre1 ?? '') . ' ' . ($personaAuth->apellido1 ?? ''))],
        ],
    ])

    <table class="partes">
        <tr>
            <td>
                <p class="bloque-titulo">Beneficiario</p>
                <table class="campos">
                    <tr><td class="etq">Nombre</td><td class="val">{{ $persona->nombre1 }}</td></tr>
                    <tr><td class="etq">Identificación</td><td class="val">{{ $persona->identificacion }}</td></tr>
                    <tr><td class="etq">Dirección</td><td class="val">{{ $persona->direccion ?: '—' }}</td></tr>
                    <tr><td class="etq">País</td><td class="val">Colombia</td></tr>
                    <tr><td class="etq">Correo</td><td class="val">{{ $persona->email ?: '—' }}</td></tr>
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
                <th style="width: 20%;" class="num">Valor total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($prestaciones as $item)
                <tr>
                    <td class="centro">{{ isset($item['servicios']) ? count($item['servicios']) : 1 }}</td>
                    <td>
                        @if(isset($item['servicios']) && is_array($item['servicios']))
                            <ul>
                                @foreach($item['servicios'] as $servicio)
                                    <li>{{ $servicio }}</li>
                                @endforeach
                            </ul>
                        @else
                            Pago
                        @endif
                    </td>
                    <td class="num">{{ is_numeric($item['valor']) ? '$ ' . number_format((float) $item['valor'], 0, ',', '.') : $item['valor'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="resumen">
        <tr>
            <td style="width: 52%;"></td>
            <td style="width: 48%;">
                <table class="totales">
                    <tr><td class="etq">Total bruto</td><td class="val">$ {{ number_format($pago->valor, 0, ',', '.') }}</td></tr>
                    <tr><td class="etq">Descuentos</td><td class="val">$ 0</td></tr>
                    <tr><td class="etq">Subtotal</td><td class="val">$ 0</td></tr>
                    <tr><td class="etq">IVA</td><td class="val">$ 0</td></tr>
                    <tr class="total"><td>Total (COP)</td><td class="val">$ {{ number_format($pago->valor, 0, ',', '.') }}</td></tr>
                </table>
            </td>
        </tr>
    </table>

    <p style="font-size: 8pt; color: #64748b;">Conserve este documento para sus registros.</p>
</body>
</html>
