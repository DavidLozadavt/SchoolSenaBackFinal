<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <style>
        @page {
            margin-left: 2cm;
            margin-right: 2cm;
            margin-top: 1cm;
            margin-bottom: 1cm;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 13px;
        }

        .center {
            text-align: center;
        }

        .left {
            text-align: left;
        }

        .logo {
            width: 80px;
            margin-bottom: 5px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        .header-table td {
            border: 1px solid #000;
            padding: 6px;
            text-align: center;
        }

        .black {
            background-color: #000;
            color: #fff;
            font-weight: bold;
        }

        .gray {
            background-color: #e6e6e6;
            font-weight: bold;
        }

        .classification td {
            border: 1px solid #000;
            padding: 5px;
            text-align: center;
        }

        .spacer {
            height: 40px;
        }

        .spacer2 {
            height: 20px;
        }

        .spacer3 {
            height: 10px;
        }

        .footer {
            position: absolute;
            bottom: 30px;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 10px;
        }

        .page-number {
            position: absolute;
            bottom: 30px;
            right: 30px;
            font-size: 10px;
        }

        .page-break {
            page-break-after: always;
        }

        table th,
        table td {
            border: 1px solid #000;
            padding: 5px;
        }
    </style>
</head>

<body>

    <div class="center">
        <img src="{{ public_path('media/images/sena/logo-sena.png') }}" class="logo">
    </div>

    <table class="header-table">
        <tr>
            <td class="black">PROCESO</td>
        </tr>
        <tr>
            <td class="gray">GESTIÓN CONTRACTUAL</td>
        </tr>
        <tr>
            <td class="black">NOMBRE DEL FORMATO</td>
        </tr>
        <tr>
            <td class="gray">INFORME MENSUAL DE EJECUCIÓN CONTRACTUAL</td>
        </tr>
        <tr>
            <td class="black">CLASIFICACIÓN DE LA INFORMACIÓN</td>
        </tr>
    </table>

    <table class="classification">
        <tr>
            <td>Pública</td>
            <td width="30">X</td>
            <td>Pública Clasificada</td>
            <td width="30"></td>
            <td>Pública Reservada</td>
        </tr>
    </table>

    <div class="spacer"></div>

    <div class="center">
        <p>
            <strong>
                {{ ucfirst(\Carbon\Carbon::parse($rmi->periodo ?? now())->translatedFormat('F \\d\\e Y')) }}
            </strong>
        </p>
    </div>

    <div class="spacer"></div>

    <div class="center">
        <p><strong>Sistema Integrado de Gestión y Autocontrol</strong></p>
    </div>

    <div class="page-number">
        1
    </div>

    <div class="footer">
        GCCON-F-087 V1
    </div>


    <div class="page-break"></div>

    <div class="center">
        <img src="{{ public_path('media/images/sena/logo-sena.png') }}" class="logo">
    </div>

    <table class="header-table">
        <tr>
            <td class="black">CLASIFICACIÓN DE LA INFORMACIÓN</td>
        </tr>
    </table>

    <table class="classification">
        <tr>
            <td>Pública</td>
            <td width="30">X</td>
            <td>Pública Clasificada</td>
            <td width="30"></td>
            <td>Pública Reservada</td>
        </tr>
    </table>

    <div class="center" style="height: 40px;">
        <p><strong>INFORME MENSUAL EJECUCIÓN CONTRACTUAL</strong></p>
    </div>

    <div class="center">
        <p>Popayán Cauca {{ \Carbon\Carbon::parse($rmi->periodo ?? now())->translatedFormat('F \\d\\e Y') }}</p>
    </div>

    <div>Señor(a)</div>
    <div>
        <strong>
            {{ $contrato->supervisorContrato }}
        </strong>
    </div>
    <!--
        //Preguntar por el número de contrato que maneja el SENA
    -->
    <div>SUPERVISOR(A) CONTRATO No. {{ $contrato->numeroContrato }}</div>
    <div>Cargo del supervisor {{ $contrato->cargoSupervisor }}</div>
    <div>Dependencia {{ $contrato->centroFormacion->nombre }}</div>
    <div>Ciudad Popayán Cauca</div>
    <div class="spacer"></div>

    <div style="width: 50%; margin-left: 50%; text-align: justify;">
        <strong>Asunto: </strong>
        Informe mensual de ejecución contractual Mes
        {{ ucfirst(\Carbon\Carbon::parse($rmi->periodo ?? now())->translatedFormat('F')) }} del año
        {{ \Carbon\Carbon::parse($rmi->periodo ?? now())->year }}
    </div>

    <div>
        <!--
        //Preguntar por el número de contrato que maneja el SENA
        -->
        <strong>
            Referencia:
        </strong>
        No. {{ $contrato->numeroContrato }}
    </div>
    <div class="spacer3"></div>

    <div style="text-align: justify">
        {{ Str::upper(
        implode(
        ' ',
        array_filter([
        $contrato->persona->nombre1,
        $contrato->persona->nombre2,
        $contrato->persona->apellido1,
        $contrato->persona->apellido2,
        ]),
        ),
        ) }}
        @if ($contrato->persona->identificacion)
        , identificado con la cédula de ciudadanía No. {{ $contrato->persona->identificacion }} de {{
        $contrato->persona->ciudadExpedicionRel->descripcion ?? 'No asignada' }} {{
        $contrato->persona->ciudadExpedicionRel->departamento->descripcion ?? 'No asignada' }},
        en mi calidad de Contratista del SENA, en el {{ $contrato->centroFormacion->nombre }}, en cumplimiento del
        Contrato de Prestación de Servicios de la referencia, a continuación,
        presento el Informe de actividades realizadas en el mes objeto de cobro.
        @endif
    </div>

    <div class="spacer"></div>

    <div style="text-align: justify">
        <strong>Valor y forma de Pago: </strong> Aquí puede ir lo que metemos por el input.
    </div>

    <div class="spacer"></div>

    <div>
        <strong>Plazo: </strong> Será hasta el {{ \Carbon\Carbon::parse($contrato->fechaFinalContrato)->day }} de {{
        \Carbon\Carbon::parse($contrato->fechaFinalContrato)->translatedFormat('F') }} de {{
        \Carbon\Carbon::parse($contrato->fechaFinalContrato)->year }}
    </div>


    <div class="footer">
        GCCON-F-087 V1
    </div>

    <div class="page-number">
        2
    </div>

    <div class="page-break"></div>

    <h3>Actividades</h3>

    <table>
        <tr>
            <th>Descripción</th>
            <th>Fecha Inicial</th>
            <th>Fecha Final</th>
            <th>Horas</th>
        </tr>

        @foreach ($actividades as $actividad)
        <tr>
            <td>{{ $actividad->descripcion }}</td>
            <td>{{ $actividad->fechaInicial }}</td>
            <td>{{ $actividad->fechaFinal }}</td>
            <td>{{ $actividad->numeroHoras }}</td>
        </tr>
        @endforeach
    </table>

    <div class="page-break"></div>

    <h3>Comisiones</h3>

    <table>
        <tr>
            <th>Descripción</th>
            <th>Horas</th>
        </tr>

        @foreach ($comisiones as $comision)
        <tr>
            <td>{{ $comision->descripcion }}</td>
            <td>{{ $comision->numeroHoras }}</td>
        </tr>
        @endforeach
    </table>


</body>

</html>