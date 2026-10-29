{{-- Pie fijo de factura/recibo con paginación. Debe incluirse al inicio del <body> para que se repita en cada página. Variables: $emisorNombre, $tipoDocumento, $numero. --}}
<div class="doc-pie">
    <table>
        <tr>
            <td>{{ $emisorNombre }} · {{ $tipoDocumento }} N.º {{ $numero ?: '—' }} · Generado el {{ now()->format('d/m/Y H:i') }}</td>
            <td style="text-align: right;" class="paginas"></td>
        </tr>
    </table>
</div>
