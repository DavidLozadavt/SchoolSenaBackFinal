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
        {{-- Fila para instructores con sus competencias --}}
        <tr>
            <td colspan="3">
                <p style="margin: 0 0 6px 0;">
                    Los resultados que se orientarán en el mes de
                    <strong>{{ \Carbon\Carbon::parse($acta->fecha)->translatedFormat('F') }}</strong> son:
                </p>

                <table class="tabla-actividades" style="width:100%; font-size:10px;">
                    <tr>
                        <th class="gray" style="width:18%; text-align:center;">Instructor</th>
                        <th class="gray" style="width:32%; text-align:center;">Competencia</th>
                        <th class="gray" style="width:35%; text-align:center;">Resultado</th>
                        <th class="gray" style="width:15%; text-align:center;">Horas dedicadas</th>
                    </tr>

                    @foreach ($instructores as $instructor)
                        @foreach ($instructor['materias'] as $index => $materia)
                            <tr>
                                {{-- Nombre solo en la primera fila del instructor --}}
                                @if ($index === 0)
                                    <td rowspan="{{ count($instructor['materias']) }}" style="vertical-align:top;">
                                        {{ $instructor['nombre'] }} {{ $instructor['apellido'] }}
                                    </td>
                                @endif
                                <td>{{ $materia['competencia'] ?? '—' }}</td>
                                <td>{{ $materia['resultadoAprendizaje'] ?? '—' }}</td>
                                <td style="text-align:center;">{{ $materia['totalHoras'] }}</td>
                            </tr>
                        @endforeach
                    @endforeach
                </table>
            </td>
        </tr>
    </table>
    <table class="header-table">
        <tr>
            <td colspan="5" class="acta" style="text-align:center; font-size:11px;">
                CONCLUSIONES
            </td>
        </tr>
        <tr>
            <td colspan="5">
                {{-- Filas de las conclusiones --}}
                @forelse ($acta->conclusiones as $conclusion)
                    @if ($loop->first)
                        <ul style="margin: 2px 0 2px 15px; padding-left: 15px;">
                    @endif

                    <li class="left">
                        {{ $conclusion->conclusion }}
                    </li>

                    @if ($loop->last)
                        </ul>
                    @endif
                @empty
                    <p class="left" style="margin: 2px 0 2px 15px;">
                        Sin conclusiones registradas
                    </p>
                @endforelse
            </td>
        </tr>
    </table>
    <table class="header-table" style="width: 100%; table-layout: fixed; word-wrap: break-word;">
        <tr>
            <td colspan="4" class="acta" style="text-align:center; font-size:11px;">
                ESTABLECIMIENTO Y ACEPTACIÓN DE COMPROMISOS
            </td>
        </tr>
        <tr>
            <td class="gray" style="width:30%; text-align:center;">ACTIVIDAD / DECISIÓN</td>
            <td class="gray" style="width:20%; text-align:center;">FECHA</td>
            <td class="gray" style="width:30%; text-align:center;">RESPONSABLE</td>
            <td class="gray" style="width:20%; text-align:center;">FIRMA O PARTICIPACIÓN VIRTUAL</td>
        </tr>
        @forelse ($acta->compromisos as $compromiso)
            <tr>
                <td style="height:35px; padding: 5px;">{{ $compromiso->actividad }}</td>
                <td style="text-align:center; padding: 5px;">
                    {{ $compromiso->fecha ? \Carbon\Carbon::parse($compromiso->fecha)->format('d/m/Y') : '' }}
                </td>
                <td style="text-align:center; padding: 5px;">{{ $compromiso->responsable ?? '' }}</td>
                <td style="text-align: center; padding: 5px;">
                    @if ($compromiso?->firma)
                        <img src="{{ storage_path('app/public/firmas/' . basename($compromiso->firma)) }}"
                            style="height: 70px; max-width: 250px; object-fit: contain;" />
                    @endif
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="4" style="text-align:center; color:#999;">Sin compromisos registrados</td>
            </tr>
        @endforelse
    </table>
    <table class="header-table">

        <table class="header-table">
            {{-- Título sección asistentes --}}
            <tr>
                <td colspan="5" class="acta" style="text-align:center; font-size:11px;">
                    DE: ASISTENTES Y APROBACIÓN DECISIONES
                </td>
            </tr>

            {{-- Encabezados --}}
            <tr>
                <td class="gray" style="width:25%; text-align:center;">NOMBRE</td>
                <td class="gray" style="width:20%; text-align:center;">DEPENDENCIA/<br>EMPRESA</td>
                <td class="gray" style="width:12%; text-align:center;">APRUEBA<br>(SI/NO)</td>
                <td class="gray" style="width:28%; text-align:center;">OBSERVACIÓN</td>
                <td class="gray" style="width:15%; text-align:center;">FIRMA O<br>PARTICIPACIÓN<br>VIRTUAL</td>
            </tr>

            {{-- Filas de asistentes --}}
            @forelse ($acta->asistencias as $asistencia)
                @php
                    $persona = $asistencia->contrato?->persona;
                    $nombre = $persona
                        ? trim(
                            collect([$persona->nombre1, $persona->nombre2, $persona->apellido1, $persona->apellido2])
                                ->filter()
                                ->implode(' '),
                        )
                        : '—';
                @endphp
                <tr>
                    <td style="height:35px;">{{ $nombre }}</td>
                    <td style="text-align:center;">{{ $asistencia->dependencia ?? '' }}</td>
                    <td style="text-align:center;">{{ $asistencia->aprueba ?? '' }}</td>
                    <td>{{ $asistencia->observacion ?? '' }}</td>
                    <td style="text-align: center;">
                        @if ($persona?->firmaDigital && $asistencia->aprueba === 'SI')
                            <img src="{{ storage_path('app/public/firmas/' . basename($persona->firmaDigital)) }}"
                                style="height: 70px; max-width: 250px; object-fit: contain;" />
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" style="text-align:center; color:#999;">Sin asistentes registrados</td>
                </tr>
            @endforelse
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
