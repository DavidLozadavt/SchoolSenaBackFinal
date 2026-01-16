<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Control de Observaciones</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            font-size: 8px;
            padding: 10px;
        }

        h1 {
            text-align: center;
            font-size: 14px;
            margin-bottom: 10px;
            color: #333;
        }

        .table-container {
            width: 100%;
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }

        th {
            background-color: #4472C4;
            color: white;
            padding: 6px 4px;
            text-align: center;
            font-size: 7px;
            border: 1px solid #2f5597;
            font-weight: bold;
        }

        td {
            padding: 5px 4px;
            border: 1px solid #ddd;
            text-align: left;
            font-size: 7px;
            vertical-align: top;
        }

        td.center {
            text-align: center;
        }

        tr:nth-child(even) {
            background-color: #f9f9f9;
        }

        .section-title {
            font-size: 11px;
            font-weight: bold;
            margin-top: 20px;
            margin-bottom: 8px;
            color: #333;
        }

        .footer {
            margin-top: 15px;
            font-size: 9px;
            text-align: center;
            color: #666;
        }

        .observacion-detalle {
            font-size: 6.5px;
            line-height: 1.3;
        }

        .vencimiento-urgente {
            background-color: #ffebee !important;
        }

        .vencimiento-proximo {
            background-color: #fff8e1 !important;
        }

        @page {
            margin: 15px;
        }
    </style>
</head>

<body>
 <h1>
    CONTROL DE OBSERVACIONES -
    <strong style="font-size: 20px; color: #222;">
        {{ strtoupper($company->razonSocial) }}
    </strong>
</h1>

@if(isset($logoPath) && $logoPath && file_exists($logoPath))
<div style="text-align: center; margin-bottom: 15px; margin-top: 10px;">
    <img src="{{ $logoPath }}" alt="Logo {{ $company->razonSocial }}" style="max-height: 80px; max-width: 250px; object-fit: contain;">
</div>
@endif

<div style="text-align: center; margin-bottom: 10px;">
</div>


    <div style="text-align: center; margin-bottom: 10px;">
    </div>


    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th style="width: 3%;">ITEM</th>
                    <th style="width: 7%;">FECHA</th>
                    <th style="width: 6%;">PLACA</th>
                    <th style="width: 4%;">N. INTERNO</th>
                    <th style="width: 12%;">CONDUCTOR</th>
                    <th style="width: 8%;">CC.</th>
                    <th style="width: 5%;">DÍAS DESDE LA OBSERVACIÓN</th>
                    <th style="width: 10%;">REVISIÓN EFECTUADA POR</th>
                    <th style="width: 22%;">OBSERVACIÓN 1</th>
                    <th style="width: 23%;">OBSERVACIÓN 2</th>
                </tr>
            </thead>
            <tbody>
                @foreach($observaciones as $index => $obs)
                <tr>
                    <td class="center">{{ $index + 1 }}</td>
                    <td class="center">{{ Carbon\Carbon::parse($obs->fecha)->format('d/m/Y') }}</td>
                    <td class="center">{{ $obs->vehiculo->placa ?? 'N/A' }}</td>
                    <td class="center">{{ $obs->vehiculo->numeroInterno ?? 'N/A' }}</td>
                    <td>{{ $obs->conductor->nombre ?? '#N/A' }}</td>
                    <td class="center">{{ $obs->conductor ? number_format($obs->conductor->identificacion, 0, ',', '.') : '#N/A' }}</td>
                    <td class="center">{{ $obs->diasDesde }}</td>
                    <td>{{ $obs->revisor->nombre ?? 'N/A' }}</td>
                    <td class="observacion-detalle">{{ $obs->observacion1 ?? '' }}</td>
                    <td class="observacion-detalle">{{ $obs->observacion2 ?? '' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <!-- Sección de Vencimientos -->
    @if($vencimientos->count() > 0)
    <div class="section-title">VENCIMIENTOS PROGRAMADOS</div>
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th style="width: 10%;">FECHA INICIO</th>
                    <th style="width: 10%;">VENCIMIENTO</th>
                    <th style="width: 8%;">VIGENCIA (DÍAS)</th>
                    <th style="width: 50%;">DETALLE</th>
                    <th style="width: 10%;">N° INTERNO</th>
                    <th style="width: 12%;">USUARIO</th>
                </tr>
            </thead>
            <tbody>
                @foreach($vencimientos as $venc)
                @php
                $diasRestantes = Carbon\Carbon::parse($venc->vencimiento)->diffInDays(Carbon\Carbon::now());
                $clase = $diasRestantes <= 1 ? 'vencimiento-urgente' : ($diasRestantes <=7 ? 'vencimiento-proximo' : '' );
                    @endphp
                    <tr class="{{ $clase }}">
                    <td class="center">{{ Carbon\Carbon::parse($venc->fechaInicio)->locale('es')->isoFormat('dddd D [de] MMMM [de] YYYY') }}</td>
                    <td class="center">{{ Carbon\Carbon::parse($venc->vencimiento)->locale('es')->isoFormat('dddd D [de] MMMM [de] YYYY') }}</td>
                    <td class="center">{{ $venc->vigenciaDias }}</td>
                    <td>{{ $venc->detalle }}</td>
                    <td class="center">{{ $venc->vehiculo->numeroInterno ?? 'N/A' }}</td>
                    <td>{{ $venc->usuario->nombre ?? 'N/A' }}</td>
                    </tr>
                    @endforeach
            </tbody>
        </table>
    </div>
    @endif

    <!-- Sección de Extintores -->
    @if($extintores->count() > 0)
    <div class="section-title">EXTINTORES QUE VENCEN EN SEPTIEMBRE 2025</div>
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th style="width: 15%;">N° INTERNO</th>
                    <th style="width: 15%;">PLACA</th>
                    <th style="width: 20%;">FECHA VENCIMIENTO</th>
                    <th style="width: 50%;">OBSERVACIONES</th>
                </tr>
            </thead>
            <tbody>
                @foreach($extintores as $ext)
                <tr>
                    <td class="center">{{ $ext->vehiculo->numeroInterno ?? 'N/A' }}</td>
                    <td class="center">{{ $ext->vehiculo->placa ?? 'N/A' }}</td>
                    <td class="center">{{ Carbon\Carbon::parse($ext->fechaVencimiento)->format('d/m/Y') }}</td>
                    <td>{{ $ext->observaciones ?? '' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    <div class="footer">
        <strong>Total registros en el sistema:</strong> {{ $totalRegistros }} |
        <strong>Generado:</strong> {{ $fechaGeneracion }}
    </div>
</body>

</html>