<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <style>
        @page {
            margin-left: 2cm;
            margin-right: 2cm;
            margin-top: 2.5cm;
            margin-bottom: 1.5cm;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 10px;
        }

        .center {
            text-align: center;
        }

        .left {
            text-align: left;
        }

        .header {
            position: fixed;
            top: -2.2cm;
            left: 0;
            right: 0;
            text-align: center;
        }

        .header img {
            width: 80px;
        }

        /* ── Tabla principal del acta ── */
        table.header-table {
            width: 100%;
            border-collapse: collapse;
        }

        table.header-table td {
            border: 1px solid #000;
            padding: 6px;
            vertical-align: top;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }

        .black {
            background-color: #000;
            color: #fff;
            font-weight: bold;
            text-align: center;
        }

        .acta {
            font-weight: bold;
            text-align: center;
        }

        .gray {
            background-color: #e6e6e6;
            font-weight: bold;
        }

        .label {
            font-weight: bold;
        }

        .value {
            font-weight: normal;
        }

        .spacer {
            height: 40px;
        }

        .page-break {
            page-break-after: always;
        }

        /* Tablas internas (horarios, actividades) */
        .tabla-horarios,
        .tabla-actividades {
            table-layout: fixed;
            width: 100%;
            border-collapse: collapse;
            font-size: 10px;
        }

        .tabla-actividades td,
        .tabla-actividades th {
            border: 1px solid #000;
            padding: 5px;
            word-wrap: break-word;
            overflow-wrap: break-word;
            white-space: normal;
        }
    </style>
</head>

<body>
    @php
        $actividadesContrato = $actividadesContrato ?? collect();
    @endphp

    <div class="header">
        <img src="{{ public_path('media/images/sena/logo-sena.png') }}">
    </div>

    <table class="header-table">

        {{-- Fila 1: Número de acta --}}
        <tr>
            <td colspan="3" class="acta">ACTA No. {{ $acta->id }}</td>
        </tr>

        {{-- Fila 2: Nombre del comité (label + valor en misma celda) --}}
        <tr>
            <td colspan="3">
                <span class="label">NOMBRE DEL COMITÉ O DE LA REUNIÓN:</span><br>
                <span class="value">{{ $acta->nombre }}</span>
            </td>
        </tr>

        {{-- Fila 3: Labels Ciudad / Hora inicio / Hora fin --}}
        <tr>
            <td style="width:50%;" class="label">CIUDAD Y FECHA:</td>
            <td style="width:25%;" class="label">HORA INICIO:</td>
            <td style="width:25%;" class="label">HORA FIN:</td>
        </tr>

        {{-- Fila 4: Valores Ciudad / Hora inicio / Hora fin --}}
        <tr>
            <td>{{ $acta->ciudad->descripcion }}, {{ $acta->created_at->format('d \d\e F \d\e Y') }}</td>
            <td>{{ $acta->horaInicio }}</td>
            <td>{{ $acta->horaFin }}</td>
        </tr>

        {{-- Fila 5: Labels Lugar / Dirección --}}
        <tr>
            <td class="label">LUGAR Y/O ENLACE:</td>
            <td colspan="2" class="label">DIRECCIÓN / REGIONAL / CENTRO:</td>
        </tr>

        {{-- Fila 6: Valores Lugar / Dirección --}}
        <tr>
            <td>{{ $acta->lugar ?? '' }}</td>
            <td colspan="2">{{ $acta->direccion ?? '' }}</td>
        </tr>

        {{-- Fila 7: Agenda --}}
        <tr>
            <td colspan="3">
                <span class="label">AGENDA O PUNTOS PARA DESARROLLAR:</span>
                @if ($acta->agenda->has(0))
                    @foreach ($acta->agenda as $agenda)
                        <p class="left" style="margin: 2px 0 2px 15px;">
                            {{ $loop->iteration }}. {{ $agenda->punto }}
                        </p>
                    @endforeach
                @endif
            </td>
        </tr>

        {{-- Fila 8: Objetivos --}}
        <tr>
            <td colspan="3">
                <span class="label">OBJETIVO(S) DE LA REUNIÓN:</span>
                @if ($acta->objetivos->has(0))
                    @foreach ($acta->objetivos as $objetivo)
                        <p class="left" style="margin: 2px 0 2px 15px;">
                            {{ $loop->iteration }}. {{ $objetivo->objetivo }}
                        </p>
                    @endforeach
                @endif
            </td>
        </tr>

    </table>

    <div class="spacer"></div>

    <script type="text/php">
    if (isset($pdf)) {
        $font        = $fontMetrics->getFont("Arial", "normal");
        $anchoPagina = $pdf->get_width();

        $textoFooter = "GOR-F-084 V02";
        $anchoTexto  = $fontMetrics->getTextWidth($textoFooter, $font, 8);
        $xCentrado   = ($anchoPagina - $anchoTexto) / 2;

        $pdf->page_text($xCentrado, 770, $textoFooter, $font, 8, [0,0,0]);
        $pdf->page_text(470, 770, "{PAGE_NUM}", $font, 8, [0,0,0]);
    }
    </script>

</body>

</html>