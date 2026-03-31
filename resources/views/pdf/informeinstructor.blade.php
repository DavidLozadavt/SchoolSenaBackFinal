<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <style>
        @page {
            margin-left: 2cm;
            margin-right: 2cm;
            margin-top: 1cm;
            margin-bottom: 1.5cm;
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

        .spacer-higth {
            height: 100px;
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
            text-align: center;
            vertical-align: middle;
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
            , identificado con la cédula de ciudadanía No. {{ $contrato->persona->identificacion }} de
            {{ $contrato->persona->ciudadExpedicionRel->descripcion ?? 'No asignada' }}
            {{ $contrato->persona->ciudadExpedicionRel->departamento->descripcion ?? 'No asignada' }},
            en mi calidad de Contratista del SENA, en el {{ $contrato->centroFormacion->nombre }}, en cumplimiento del
            Contrato de Prestación de Servicios de la referencia, a continuación,
            presento el Informe de actividades realizadas en el mes objeto de cobro.
        @endif
    </div>

    <div class="spacer"></div>

    <div style="text-align: justify">
        <strong>Valor y forma de Pago: </strong> {{ $plazo }}
    </div>

    <div class="spacer"></div>

    <div>
        <strong>Plazo: </strong> Será hasta el {{ \Carbon\Carbon::parse($contrato->fechaFinalContrato)->day }} de
        {{ \Carbon\Carbon::parse($contrato->fechaFinalContrato)->translatedFormat('F') }} de
        {{ \Carbon\Carbon::parse($contrato->fechaFinalContrato)->year }}
    </div>

    <div class="spacer2"></div>

    <div style="text-align: justify">
        <strong>Objeto: </strong> {{ $contrato->objetoContrato }}{{ '.' }}
    </div>





    <div class="page-break"></div>

    <h3>Ejecución mensual de actividades</h3>

    <table>
        <tr>
            <th style="width: 40px;">No</th>
            <th>Obligaciones</th>
            <th>Acciones realizadas</th>
            <th>Evidencias</th>
        </tr>

        <tr>
            <td style="width: 40px; text-align:center;">
                1
            </td>

            <td>
                Impartir formación
                profesional integral
                que no solo abarque
                los conocimientos
                técnicos
                y
                académicos,
                sino
                también
                las
                habilidades
                y
                actitudes necesarias
                para su desempeño
                en
                el
                sector
                productivo,
                promoviendo
                su
                crecimiento personal
                y profesional. Este
                acompañamiento se
                realizará conforme a
                las directrices y
                objetivos
                del
                programa
                de
                formación,
                garantizando que se
                desarrollen tanto las
                competencias
                específicas como las
                habilidades blandas
                requeridas para su
                éxito en el ámbito
                laboral.
            </td>
            <td>
                Impartir formación profesional en los programas de formación
                Titulada o Complementaria de acuerdo con la programación
                asignada en las siguientes fichas de caracterización:
                <table>
                    <thead>
                        <tr>
                            <th>No. Ficha</th>
                            <th>Nombre Programa</th>
                            <th>Horario</th>
                            <th>Horas Mes</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php $totalGeneral = 0; @endphp

                        @foreach ($horariosPorFicha as $fichaData)
                            @php $totalGeneral += $fichaData['totalHorasFicha']; @endphp
                            <tr>
                                <td>{{ $fichaData['codigoFicha'] }}</td>
                                <td>{{ $fichaData['programaFormacion'] }}</td>
                                <td style="text-align: left;">
                                    @foreach ($fichaData['filas'] as $fila)
                                        <div>
                                            <strong>{{ $fila['dia'] }}:</strong>
                                            {{ \Carbon\Carbon::parse($fila['horaInicial'])->format('h:i A') }} -
                                            {{ \Carbon\Carbon::parse($fila['horaFinal'])->format('h:i A') }}
                                        </div>
                                    @endforeach
                                </td>
                                <td>{{ $fichaData['totalHorasFicha'] }}</td>
                            </tr>
                        @endforeach

                        <tr>
                            <td colspan="3" style="text-align: right;"><strong>TOTAL HORAS MES</strong></td>
                            <td><strong>{{ $totalGeneral }}</strong></td>
                        </tr>
                    </tbody>
                </table>
            </td>
            <td>
                Reporte Mensual de Instructor (RMI)
            </td>
        </tr>


        @foreach ($actividades as $actividad)
            <tr>
                <td style="width: 40px; text-align:center;">
                    {{ $loop->iteration + 1 }}
                </td>
                <td>{{ $actividad->descripcion }}</td>
                <td>{{ $actividad->fechaInicial }}</td>
                <td>{{ $actividad->fechaFinal }}</td>
            </tr>
        @endforeach
    </table>

    <div class="page-break"></div>
    <h3>Horario mensual de ejecución</h3>

    <table>
        <thead>
            <tr>
                <th>No. Ficha</th>
                <th>Nombre Programa</th>
                <th>Horario</th>
                <th>Horas Mes</th>
            </tr>
        </thead>
        <tbody>
            @php $totalGeneral = 0; @endphp

            @foreach ($horariosPorFicha as $fichaData)
                @php $totalGeneral += $fichaData['totalHorasFicha']; @endphp
                <tr>
                    <td>{{ $fichaData['codigoFicha'] }}</td>
                    <td>{{ $fichaData['programaFormacion'] }}</td>
                    <td style="text-align: left;">
                        @foreach ($fichaData['filas'] as $fila)
                            <div>
                                <strong>{{ $fila['dia'] }}:</strong>
                                {{ \Carbon\Carbon::parse($fila['horaInicial'])->format('h:i A') }} -
                                {{ \Carbon\Carbon::parse($fila['horaFinal'])->format('h:i A') }}
                            </div>
                        @endforeach
                    </td>
                    <td>{{ $fichaData['totalHorasFicha'] }}</td>
                </tr>
            @endforeach

            <tr>
                <td colspan="3" style="text-align: right;"><strong>TOTAL HORAS MES</strong></td>
                <td><strong>{{ $totalGeneral }}</strong></td>
            </tr>
        </tbody>
    </table>
    <div class="page-break"></div>

    <div style="text-align: justify">
        A continuación, relaciono los desplazamientos que realicé previo a la presentación de este informe. Una
        vez finalizado cada desplazamiento presenté al ordenador del gasto el informe en el Formato para
        legalización del desplazamiento, en el que se describieron las actividades desarrolladas y los resultados.
        Cada informe de legalización cuenta con el visto bueno del supervisor.
    </div>

    <div class="spacer2"></div>

    <div style="text-align: justify">
        Se lista a continuación el soporte de la legalización de los desplazamientos realizados, los cuales forman
        parte integral del presente informe de ejecución contractual.
    </div>
    <div class="spacer2"></div>

    <table>
        <tr>
            <th>ÍTEM</th>
            <th>NRO. DE LA ORDEN DE VIAJE</th>
            <th>LUGAR DE DESPLAZAMIENTO</th>
            <th>FECHA DE DESPLAZAMIENTO INICIAL</th>
            <th>FECHA DE DESPLAZAMIENTO FINAL</th>
        </tr>

        @foreach ($comisiones as $comision)
            <tr>
                <td>{{ $comision->item }}</td>
                <td>{{ $comision->numeroViaje }}</td>
                <td>{{ $comision->lugarDesplazamiento }}</td>
                <td>{{ \Carbon\Carbon::parse($comision->fechaInicialDesplazamiento)->format('Y-m-d') }}</td>
                <td>{{ \Carbon\Carbon::parse($comision->fechaFinalDesplazamiento)->format('Y-m-d') }}</td>
            </tr>
        @endforeach
    </table>
    <div class="spacer2"></div>
    <div style="text-align: justify">
        Para el trámite de la cuenta me permito adjuntar: (i) Documentos electrónicos enunciados como
        evidencias del cumplimiento de las obligaciones contractuales, (ii) los desplazamientos realizados y (iii) el
        pago de la planilla de seguridad social y parafiscal nro. {{ $nPlanilla }} de la planilla, aportes en línea
        {{ \Carbon\Carbon::parse($rmi->periodo ?? now())->translatedFormat('F \\d\\e\\l Y') }}. (Decreto Ley 2106 de
        2019 – “Decreto Ley Anti trámites”)
    </div>
    <div class="spacer2"></div>
    <div>
        Coordialmente
    </div>
    <div class="spacer-higth"></div>
    <div>

        <strong>
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
        </strong>

    </div>
    <div class="spacer3"></div>
    <div>
        Contratista
    </div>
    <div class="spacer3"></div>
    <strong>
        C.C {{ $contrato->persona->identificacion }} DE
        {{ $contrato->persona->ciudadExpedicionRel->descripcion ?? 'No asignada' }}
        {{ $contrato->persona->ciudadExpedicionRel->departamento->descripcion ?? 'No asignada' }},
    </strong>

    <div class="spacer2"></div>
    <div>Firma</div>

    <div class="spacer-higth"></div>

    <div>
        <strong>
            {{ $contrato->supervisorContrato }}
        </strong>

        <div>SUPERVISOR(A) CONTRATO No. {{ $contrato->numeroContrato }} de
            {{ ucfirst(\Carbon\Carbon::parse($rmi->periodo ?? now())->translatedFormat('Y')) }}</div>
        <div>{{ $contrato->cargoSupervisor }}</div>

    </div>




    <div class="page-break"></div>

    <table border="1">
        <thead>
            <tr>
                <th>VERSIÓN</th>
                <th>FECHA DE ENTRADA EN VIGENCIA</th>
                <th>NATURALEZA DEL CAMBIO</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>1</td>
                <td>Pública Reservada</td>
                <td style="text-align: justify;">
                    <div style="text-align: justify;">Creación del formato.</div>
                    <div style="text-align: justify;">
                        El presente formato sustituye el formato GTH-F-062, en virtud de su
                        migración del proceso de Gestión del Talento Humano al proceso de
                        Gestión Contractual, conforme a la actualización documental
                        correspondiente.
                    </div>
                </td>
            </tr>
        </tbody>
    </table>



    <script type="text/php">
    if (isset($pdf)) {
        $font       = $fontMetrics->getFont("Arial", "normal");
        $anchoPagina = $pdf->get_width();  // 612 para letter

        $textoFooter = "GCCON-F-087 V1";
        $anchoTexto  = $fontMetrics->getTextWidth($textoFooter, $font, 8);
        $xCentrado   = ($anchoPagina - $anchoTexto) / 2;

        $pdf->page_text($xCentrado, 770, $textoFooter, $font, 8, [0,0,0]);
        $pdf->page_text(470, 770, "{PAGE_NUM}", $font, 8, [0,0,0]);
    }
    </script>

</body>

</html>
