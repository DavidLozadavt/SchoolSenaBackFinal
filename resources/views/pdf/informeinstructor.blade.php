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
            word-wrap: break-word;
            /* ← rompe texto largo */
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
            word-wrap: break-word;
            overflow-wrap: break-word;
        }

        .tabla-horarios {
            table-layout: fixed;
            width: 100%;
            font-size: 10px;
        }

        .tabla-actividades {
            table-layout: fixed;
            width: 100%;
            border-collapse: collapse;
        }

        .tabla-actividades td,
        .tabla-actividades th {
            word-wrap: break-word;
            overflow-wrap: break-word;
            white-space: normal;
            /* Evita que el texto se mantenga en una sola línea */
        }

        .tabla-actividades .tabla-horarios {
            table-layout: fixed;
            width: 100%;
            font-size: 10px;
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

    <table class="tabla-actividades">
        <colgroup>
            <col style="width: 5%;">
            <col style="width: 20%;">
            <col style="width: 50%;">
            <col style="width: 20%;">
        </colgroup>
        <thead>
            <tr>
                <th style="width: 5%;">No</th>
                <th style="width: 20%;">Obligaciones</th>
                <th style="width: 55%;">Acciones realizadas</th>
                <th style="width: 20%;">Evidencias</th>
            </tr>
        </thead>
        <tbody>
            {{-- ── Fila 1: índice [0] ── --}}
            <tr>
                <td style="text-align:center;">1</td>
                <td style="vertical-align: top;">{{ $actividadesContrato[0]->obligaciones }}</td>
                <td style="vertical-align: top; padding: 5px;">
                    <div style="margin-bottom: 8px;">{{ $actividadesContrato[0]->accionesRealizadas }}</div>

                    <table class="tabla-horarios" style="margin: 8px 0; border: 1px solid #000;">
                        <colgroup>
                            <col style="width: 12%;">
                            <col style="width: 30%;">
                            <col style="width: 40%;">
                            <col style="width: 18%;">
                        </colgroup>
                        <thead>
                            <tr>
                                <th style="font-size: 9px; padding: 3px;">No. Ficha</th>
                                <th style="font-size: 9px; padding: 3px;">Nombre Programa</th>
                                <th style="font-size: 9px; padding: 3px;">Horario</th>
                                <th style="font-size: 9px; padding: 3px;">Horas Mes</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php $totalGeneral = 0; @endphp

                            @foreach ($horariosPorFicha as $fichaData)
                                @php $totalGeneral += $fichaData['totalHorasFicha']; @endphp
                                <tr>
                                    <td style="font-size: 9px; padding: 3px;">{{ $fichaData['codigoFicha'] }}</td>
                                    <td style="font-size: 9px; padding: 3px; text-align: left;">
                                        {{ $fichaData['programaFormacion'] }}</td>
                                    <td style="font-size: 9px; padding: 3px; text-align: left;">
                                        @foreach ($fichaData['filas'] as $fila)
                                            <div style="margin: 2px 0;">
                                                <strong>{{ $fila['dia'] }}:</strong>
                                                {{ \Carbon\Carbon::parse($fila['horaInicial'])->format('h:i A') }} -
                                                {{ \Carbon\Carbon::parse($fila['horaFinal'])->format('h:i A') }}
                                            </div>
                                        @endforeach
                                    </td>
                                    <td style="font-size: 9px; padding: 3px;">{{ $fichaData['totalHorasFicha'] }}</td>
                                </tr>
                            @endforeach

                            <tr>
                                <td colspan="3" style="text-align: right; font-size: 9px; padding: 3px;">
                                    <strong>TOTAL HORAS MES</strong>
                                </td>
                                <td style="font-size: 9px; padding: 3px;"><strong>{{ $totalGeneral }}</strong></td>
                            </tr>
                        </tbody>
                    </table>

                    <div style="margin-top: 8px; font-size: 10px;">
                        Realizar y entregar los seguimientos de etapa productiva de las fichas,
                        asignadas Revisión de bitácoras Evaluación de etapa productiva.
                    </div>
                </td>
                <td style="vertical-align: top;">{{ $actividadesContrato[0]->evidencias }}</td>
            </tr>

            {{-- ── Fila 2: índice [1] ── --}}
            <tr>
                <td style="text-align:center;">2</td>
                <td style="vertical-align: top;">{{ $actividadesContrato[1]->obligaciones }}</td>
                <td style="vertical-align: top;">{{ $actividadesContrato[1]->accionesRealizadas }}</td>
                <td style="vertical-align: top;">{{ $actividadesContrato[1]->evidencias }}</td>
            </tr>

            {{-- ── Filas dinámicas desde [2] en adelante ── --}}
            @foreach ($actividadesContrato->slice(2) as $actividad)
                <tr>
                    <td style="text-align:center;">{{ $loop->iteration + 2 }}</td>
                    <td style="vertical-align: top;">{{ $actividad->obligaciones }}</td>
                    <td style="vertical-align: top;">{{ $actividad->accionesRealizadas }}</td>
                    <td style="vertical-align: top;">{{ $actividad->evidencias }}</td>
                </tr>
            @endforeach
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
        {{ \Carbon\Carbon::parse($rmi->periodo ?? now())->subMonth()->translatedFormat('F') }} del
        {{ \Carbon\Carbon::parse($rmi->periodo ?? now())->translatedFormat('Y') }}. (Decreto Ley 2106 de
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
