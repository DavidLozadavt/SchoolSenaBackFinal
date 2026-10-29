{{--
    Encabezado de factura/recibo.
    Variables:
      $company   Modelo Company (emisor). Opcional si se envía $emisorNombre.
      $emisorNombre  Nombre del emisor cuando no hay modelo.
      $emisorLineas  Opcional: líneas de datos del emisor cuando no hay modelo (NIT, teléfono...).
      $logoArchivo   Opcional: ruta local de un logo cuando no hay modelo.
      $tipoDocumento Ej. "Factura", "Recibo de venta".
      $numero        Número del documento.
      $meta          [['Fecha', '28/10/2026'], ...] datos a la derecha (los vacíos se omiten).
      $estado        Opcional: ['texto' => 'Pagado', 'clase' => 'pagado'|'pendiente'].
--}}
@php
    $emisorNombre = $company->razonSocial ?? ($emisorNombre ?? '');

    // El logo se lee del disco: dompdf tiene enable_remote = false y no admite data:// en allowed_protocols.
    $logoData = null;
    $rutaLogo = $company->rutaLogo ?? null;
    $candidatos = [];
    if ($rutaLogo) {
        $relativa = ltrim($rutaLogo, '/');
        $candidatos = [
            public_path($relativa),
            storage_path('app/public/' . preg_replace('#^storage/#', '', $relativa)),
        ];
    } elseif (!empty($logoArchivo)) {
        $candidatos = [$logoArchivo];
    }
    if ($candidatos) {
        foreach ($candidatos as $archivo) {
            if (is_file($archivo) && ($mime = @mime_content_type($archivo)) && str_starts_with($mime, 'image/')) {
                $logoData = realpath($archivo);
                break;
            }
        }
    }

    $nit = $company->nit ?? null;
    $dv = $company->digitoVerificacion ?? null;
@endphp
<table class="doc-encabezado">
    <tr>
        <td style="width: 55%;">
            @if($logoData)
                <img src="{{ $logoData }}" class="emisor-logo" alt="">
            @endif
            <p class="emisor-nombre">{{ $emisorNombre }}</p>
            @if($nit)
                <p class="emisor-dato">NIT {{ $nit }}{{ $dv !== null && $dv !== '' ? '-' . $dv : '' }}</p>
            @endif
            @if(!empty($company->direccion))
                <p class="emisor-dato">{{ $company->direccion }}</p>
            @endif
            @if(!empty($company->telefono) || !empty($company->email))
                <p class="emisor-dato">
                    {{ !empty($company->telefono) ? 'Tel. ' . $company->telefono : '' }}{{ !empty($company->telefono) && !empty($company->email) ? ' · ' : '' }}{{ $company->email ?? '' }}
                </p>
            @endif
            @foreach($emisorLineas ?? [] as $linea)
                <p class="emisor-dato">{{ $linea }}</p>
            @endforeach
        </td>
        <td style="width: 45%;">
            <p class="doc-tipo">{{ $tipoDocumento }}</p>
            <p class="doc-numero">N.º {{ $numero ?: '—' }}</p>
            <table class="doc-meta">
                @foreach($meta ?? [] as [$etiqueta, $valor])
                    @continue($valor === null || $valor === '')
                    <tr>
                        <td class="etq">{{ $etiqueta }}</td>
                        <td class="val">{{ $valor }}</td>
                    </tr>
                @endforeach
                @if(!empty($estado))
                    <tr>
                        <td class="etq">Estado</td>
                        <td><span class="estado estado-{{ $estado['clase'] ?? '' }}">{{ $estado['texto'] }}</span></td>
                    </tr>
                @endif
            </table>
        </td>
    </tr>
</table>
<hr class="doc-regla">
