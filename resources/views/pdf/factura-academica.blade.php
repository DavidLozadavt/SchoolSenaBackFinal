@php
    $pagada = strtoupper($factura['estado'] ?? '') === 'PAGADO';
    $numero = $factura['numeroFactura'] ?? null;
    $fechaEmision = !empty($factura['fecha'])
        ? \Carbon\Carbon::parse($factura['fecha'])->format('d/m/Y')
        : date('d/m/Y');

    // Cálculos sin cambios respecto a la versión anterior de la factura.
    $total = 0;
    $lineas = [];
    foreach ($factura['detalles'] ?? [] as $d) {
        $cantidad = max(1, (int) ($d['cantidad'] ?? 1));
        $val = (float) ($d['valor'] ?? 0) * $cantidad;
        $total += $val;
        $concepto = $d['concepto'] ?? $d['detalle'] ?? 'Concepto académico';
        $detalle = $d['detalle'] ?? null;
        $lineas[] = [
            'concepto' => $concepto,
            'detalle' => ($detalle && $detalle !== $concepto) ? $detalle : null,
            'cantidad' => $cantidad,
            'unitario' => (float) ($d['valor'] ?? 0),
            'valor' => $val,
        ];
    }
    $valorFinal = (float) ($factura['valor'] ?? 0) > 0 ? (float) ($factura['valor'] ?? 0) : $total;
    $saldo = (float) ($factura['saldoPendiente'] ?? $valorFinal);
    $iva = (float) ($factura['valorIva'] ?? 0);

    $nombreAspirante = $estudiante['nombreCompleto'] ?? ($factura['tercero']['nombre'] ?? null);
    $documentoAspirante = $estudiante['documento'] ?? ($factura['tercero']['identificacion'] ?? null);
    $tipoDocumento = $estudiante['tipoDocumento'] ?? null;
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Factura N.º {{ $numero ?? 'S/N' }}</title>
    @include('pdf.partials.documento-estilos')
</head>
<body>
    @include('pdf.partials.documento-pie', [
        'emisorNombre' => $nombreInstitucion,
        'tipoDocumento' => 'Factura',
        'numero' => $numero,
    ])

    @include('pdf.partials.documento-encabezado', [
        'company' => $company ?? null,
        'emisorNombre' => $nombreInstitucion,
        'logoArchivo' => $logoArchivo ?? null,
        'emisorLineas' => empty($company) ? ['Plataforma de gestión académica'] : [],
        'tipoDocumento' => 'Factura',
        'numero' => $numero,
        'meta' => [
            ['Fecha de emisión', $fechaEmision],
        ],
        'estado' => $pagada
            ? ['texto' => 'Pagada', 'clase' => 'pagado']
            : ['texto' => 'Pendiente de pago', 'clase' => 'pendiente'],
    ])

    <table class="partes">
        <tr>
            <td>
                <p class="bloque-titulo">Facturado a</p>
                <table class="campos">
                    <tr><td class="etq">Nombre</td><td class="val">{{ $nombreAspirante ?: '—' }}</td></tr>
                    <tr><td class="etq">Documento</td><td class="val">{{ trim(($tipoDocumento ? $tipoDocumento . ' ' : '') . ($documentoAspirante ?? '')) ?: '—' }}</td></tr>
                    <tr><td class="etq">Correo</td><td class="val">{{ $estudiante['email'] ?? ($factura['tercero']['email'] ?? '—') }}</td></tr>
                    <tr><td class="etq">Teléfono</td><td class="val">{{ $estudiante['celular'] ?? ($factura['tercero']['telefono'] ?? '—') }}</td></tr>
                </table>
            </td>
            <td>
                <p class="bloque-titulo">Detalle de la inscripción</p>
                <table class="campos">
                    <tr><td class="etq">Programa</td><td class="val">{{ $proceso['nombreProceso'] ?? '—' }}</td></tr>
                    <tr><td class="etq">Concepto</td><td class="val">Inscripción y matrícula</td></tr>
                    <tr><td class="etq">Moneda</td><td class="val">Pesos colombianos (COP)</td></tr>
                </table>
            </td>
        </tr>
    </table>

    <table class="conceptos">
        <thead>
            <tr>
                <th style="width: 6%;" class="centro">#</th>
                <th>Concepto</th>
                <th style="width: 10%;" class="centro">Cant.</th>
                <th style="width: 18%;" class="num">Valor unitario</th>
                <th style="width: 18%;" class="num">Valor</th>
            </tr>
        </thead>
        <tbody>
            @forelse($lineas as $i => $linea)
                <tr>
                    <td class="centro">{{ $i + 1 }}</td>
                    <td>
                        {{ $linea['concepto'] }}
                        @if($linea['detalle'])
                            <br><span style="color:#64748b; font-size:8pt;">{{ $linea['detalle'] }}</span>
                        @endif
                    </td>
                    <td class="centro">{{ $linea['cantidad'] }}</td>
                    <td class="num">$ {{ number_format($linea['unitario'], 0, ',', '.') }}</td>
                    <td class="num">$ {{ number_format($linea['valor'], 0, ',', '.') }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="vacio">Sin conceptos registrados</td></tr>
            @endforelse
        </tbody>
    </table>

    <table class="resumen">
        <tr>
            <td style="width: 52%; padding-right: 6mm;">
                @if($pagada)
                    <div class="nota nota-ok">
                        <strong>Pago registrado.</strong> Su inscripción se encuentra en proceso de aprobación final.
                    </div>
                @else
                    <div class="nota nota-alerta">
                        <strong>Pago pendiente.</strong> Para completar su inscripción, realice el pago del valor indicado por alguno de los medios de pago disponibles.
                    </div>
                @endif
            </td>
            <td style="width: 48%;">
                <table class="totales">
                    <tr><td class="etq">Subtotal</td><td class="val">$ {{ number_format($valorFinal, 0, ',', '.') }}</td></tr>
                    @if($iva > 0)
                        <tr><td class="etq">IVA</td><td class="val">$ {{ number_format($iva, 0, ',', '.') }}</td></tr>
                    @endif
                    <tr class="total"><td>{{ $pagada ? 'Saldo pendiente (COP)' : 'Total a pagar (COP)' }}</td><td class="val">$ {{ number_format($saldo, 0, ',', '.') }}</td></tr>
                </table>
            </td>
        </tr>
    </table>

    @if(!$pagada && !empty($metodosPago))
        <p class="bloque-titulo">Medios de pago</p>
        <table class="campos" style="margin-bottom: 4mm;">
            @foreach($metodosPago as $metodo)
                <tr>
                    <td class="val" style="width: 34%; font-weight: bold;">{{ $metodo['nombre'] }}</td>
                    <td class="val">{{ $metodo['detalle'] }}</td>
                </tr>
            @endforeach
        </table>
        @if(!empty($portalUrl))
            <div class="nota">
                Pague o consulte el estado de su inscripción en el portal del aspirante:<br>
                <a href="{{ $portalUrl }}" class="enlace">{{ $portalUrl }}</a>
            </div>
        @endif
    @endif

    <p style="font-size: 8pt; color: #64748b; margin-top: 4mm;">
        Documento generado por {{ $nombreInstitucion }}. Conserve una copia para sus registros.
    </p>
</body>
</html>
