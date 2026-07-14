<!DOCTYPE html>
<html>

<head>
    <style>
        body {
            font-family: Arial;
            font-size: 10px;
        }

        table {
            border-collapse: collapse;
            width: 100%;
        }

        td,
        th {
            border: 1px solid #000;
            padding: 3px;
            text-align: center;
        }

        .mes-title {
            font-weight: bold;
            text-align: center;
            margin: 10px 0;
        }
    </style>
</head>

<body>
    <h3 style="text-align: center;">Horario Mensual - Ficha {{ $ficha->codigo }} (Trimestre {{ $trimestre }})</h3>

    @foreach ($calendario as $mesKey => $diasMes)
        @php
            $fechaMes = \Carbon\Carbon::createFromFormat('Y-m', $mesKey);
            $nombreMes = $fechaMes->translatedFormat('F Y');
        @endphp
        <div class="mes-title">Días de formación - {{ ucfirst($nombreMes) }}</div>
        <table>
            <tr>
                @foreach (['L', 'M', 'M', 'J', 'V', 'S', 'D'] as $dia)
                    <th style="background: #f0f0f0;">{{ $dia }}</th>
                @endforeach
            </tr>
            @php
                $inicioMes = $fechaMes->copy()->startOfMonth();
                $finMes = $fechaMes->copy()->endOfMonth();
                $primerDia = $inicioMes->copy();
                $offsetInicio = $primerDia->dayOfWeek === 0 ? 6 : $primerDia->dayOfWeek - 1;
                $totalDias = $finMes->day;
                $totalCeldas = $offsetInicio + $totalDias;
                $filas = ceil($totalCeldas / 7);
            @endphp
            @for ($fila = 0; $fila < $filas; $fila++)
                <tr>
                    @for ($col = 0; $col < 7; $col++)
                        @php
                            $numeroCelda = $fila * 7 + $col;
                            $dia = $numeroCelda - $offsetInicio + 1;
                            $esValido = $dia >= 1 && $dia <= $totalDias;
                            $coloresDia = $esValido ? ($diasMes[(string) $dia]['colores'] ?? []) : [];
                            $hayClase = count($coloresDia) > 0;
                            $bgStyle = $hayClase ? 'background-color: ' . implode(',', $coloresDia) . ';' : '';
                        @endphp
                        <td style="{{ $bgStyle }}">
                            {{ $esValido ? $dia : '' }}
                        </td>
                    @endfor
                </tr>
            @endfor
        </table>
        <br>
    @endforeach

    <div style="margin-top: 20px;">
        <strong>Leyenda:</strong>
        @foreach ($instructoresConColor as $instructor)
            <div style="margin: 5px 0;">
                <span
                    style="display: inline-block; width: 12px; height: 12px; background-color: {{ $instructor['color'] }};"></span>
                {{ $instructor['nombre'] }} {{ $instructor['apellido'] }}
            </div>
        @endforeach
    </div>
</body>

</html>