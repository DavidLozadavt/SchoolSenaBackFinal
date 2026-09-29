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
    @php
        $actividadesContrato = $actividadesContrato ?? collect();
    @endphp
    <div class="header">
        <img src="{{ public_path('media/images/sena/logo-sena.png') }}">
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
        <strong>Valor y forma de Pago: </strong>
        {{ $contrato->descripcionFormaPago ?? 'Falta agregarla en el paso 1 contrato' }}
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
                <th style="width: 20%; text-align: justify;">Obligaciones</th>
                <th style="width: 55%; text-align: justify;">Acciones realizadas</th>
                <th style="width: 20%; text-align: justify;">Evidencias</th>
            </tr>
        </thead>
        <tbody>
            {{-- ── Fila 1: índice [0] ── --}}
            @if ($actividadesContrato->has(0))
                <tr>
                    <td style="text-align:center;">1</td>
                    <td style="vertical-align: middle; text-align: justify;">
                        {{ $actividadesContrato[0]->obligaciones }}
                    </td>
                    <td style="vertical-align: middle; padding: 5px; text-align: justify;">
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
                                    <th style="font-size: 9px; padding: 3px; text-align: justify;">No. Ficha</th>
                                    <th style="font-size: 9px; padding: 3px; text-align: justify;">Nombre Programa</th>
                                    <th style="font-size: 9px; padding: 3px; text-align: justify;">Horario</th>
                                    <th style="font-size: 9px; padding: 3px; text-align: justify;">Horas Mes</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php $totalGeneral = 0; @endphp

                                @foreach ($horariosPorFicha as $fichaData)
                                    @php $totalGeneral += $fichaData['totalHorasFicha']; @endphp
                                    <tr>
                                        <td style="font-size: 9px; padding: 3px; text-align: justify;">
                                            {{ $fichaData['codigoFicha'] }}</td>
                                        <td style="font-size: 9px; padding: 3px; text-align: justify;">
                                            {{ $fichaData['programaFormacion'] }}</td>
                                        <td style="font-size: 9px; padding: 3px; text-align: justify;">
                                            @foreach ($fichaData['filas'] as $fila)
                                                <div style="margin: 2px 0;">
                                                    <strong>{{ $fila['dia'] }}:</strong>
                                                    {{ \Carbon\Carbon::parse($fila['horaInicial'])->format('h:i A') }}
                                                    -
                                                    {{ \Carbon\Carbon::parse($fila['horaFinal'])->format('h:i A') }}
                                                </div>
                                            @endforeach
                                        </td>
                                        <td style="font-size: 9px; padding: 3px; text-align: justify;">
                                            {{ $fichaData['totalHorasFicha'] }}</td>
                                    </tr>
                                @endforeach

                                <tr>
                                    <td colspan="3" style="text-align: right; font-size: 9px; padding: 3px;">
                                        <strong>TOTAL HORAS MES</strong>
                                    </td>
                                    <td style="font-size: 9px; padding: 3px; text-align: justify;">
                                        <strong>{{ $totalGeneral }}</strong>
                                    </td>
                                </tr>
                            </tbody>
                        </table>

                        <div style="margin-top: 8px; font-size: 10px; text-align: justify;">
                            Realizar y entregar los seguimientos de etapa productiva de las fichas,
                            asignadas Revisión de bitácoras Evaluación de etapa productiva.
                        </div>
                    </td>
                    <td style="vertical-align: middle; text-align: justify;">{{ $actividadesContrato[0]->evidencias }}
                    </td>
                </tr>
            @endif

            {{-- ── Fila 2: índice [1] ── --}}
            @if ($actividadesContrato->has(1))
                <tr>
                    <td style="text-align:center;">2</td>
                    <td style="vertical-align: middle; text-align: justify;">
                        {{ $actividadesContrato[1]->obligaciones }}
                    </td>
                    <td style="vertical-align: middle; text-align: justify;">
                        {{ $actividadesContrato[1]->accionesRealizadas }}

                        @foreach ($fichasConProyecto as $ficha)
                            @foreach ($ficha['materias'] as $materia)
                                <table style="margin-top: 20px; width:100%">
                                    <tr>
                                        <td style="text-align: justify; width: 30%">Ficha</td>
                                        <td style="text-align: justify">{{ $ficha['codigoFicha'] }}</td>
                                    </tr>
                                    <tr>
                                        <td style="text-align: justify">Programa</td>
                                        <td style="text-align: justify">{{ $ficha['programaFormacion'] }}</td>
                                    </tr>
                                    <tr>
                                        <td style="text-align: justify">Proyecto</td>
                                        <td style="text-align: justify">{{ $materia['proyectoFormativo'] }}</td>
                                    </tr>
                                    <tr>
                                        <td style="text-align: justify">Actividad</td>
                                        <td style="text-align: justify">
                                            @foreach ($materia['actividades'] as $actividad)
                                                {{ $actividad['descripcionActividad'] }}<br>
                                            @endforeach
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="text-align: justify">Fase del Proyecto</td>
                                        <td style="text-align: justify">{{ $materia['faseProyecto'] }}</td>
                                    </tr>
                                </table>
                            @endforeach
                        @endforeach
                    </td>
                    <td style="vertical-align: middle; text-align: justify;">{{ $actividadesContrato[1]->evidencias }}
                    </td>
                </tr>
            @endif
            {{-- ── Fila 3: índice [2] ── --}}
            @if ($actividadesContrato->has(2))
                <tr>
                    <td style="text-align:center;">3</td>
                    <td style="vertical-align: middle; text-align: justify;">
                        {{ $actividadesContrato[2]->obligaciones }}
                    </td>
                    <td style="vertical-align: middle; text-align: justify;">
                        {{ $actividadesContrato[2]->accionesRealizadas }}
                        @foreach ($aprendicesPorFicha as $idFicha => $aprendices)
                            @php
                                $fichaInfo = $horariosPorFicha->firstWhere('idFicha', $idFicha);
                                $porEstado = collect($aprendices)->groupBy('estado');
                            @endphp
                            <table style="margin-top: 20px; margin-bottom: 10px;">
                                <tr>
                                    <td style="text-align: justify; width: 40%;"><strong>No. Ficha</strong></td>
                                    <td style="text-align: justify">{{ $fichaInfo['codigoFicha'] ?? $idFicha }}</td>
                                </tr>
                                <tr>
                                    <td style="text-align: justify"><strong>Nombre Programa</strong></td>
                                    <td style="text-align: justify">{{ $fichaInfo['programaFormacion'] ?? '—' }}</td>
                                </tr>
                                <tr>
                                    <td style="text-align: justify"><strong>No. Aprendices con novedades</strong></td>
                                    <td style="text-align: justify">{{ count($aprendices) }}</td>
                                </tr>
                                <tr>
                                    <td style="text-align: justify"><strong>Novedad reportada</strong></td>
                                    <td style="text-align: justify">
                                        @foreach ($porEstado as $estado => $grupo)
                                            {{ $estado }} ({{ count($grupo) }})<br>
                                        @endforeach
                                    </td>
                                </tr>
                                <tr>
                                    <td style="text-align: justify"><strong>Nombre de Aprendices</strong></td>
                                    <td style="text-align: justify">
                                        @foreach ($aprendices as $aprendiz)
                                            {{ $aprendiz['nombreCompleto'] }}<br>
                                        @endforeach
                                    </td>
                                </tr>
                            </table>
                        @endforeach
                    </td>
                    <td style="vertical-align: middle; text-align: justify;">{{ $actividadesContrato[2]->evidencias }}
                    </td>
                </tr>
            @endif
            {{-- ── Fila 4: índice [3] ── --}}
            @if ($actividadesContrato->has(3))
                <tr>
                    <td style="text-align:center;">4</td>
                    <td style="vertical-align: middle; text-align: justify;">
                        {{ $actividadesContrato[3]->obligaciones }}
                    </td>
                    <td style="vertical-align: middle; text-align: justify;">
                        {{ $actividadesContrato[3]->accionesRealizadas }}
                        <table style="margin-top: 20px; margin-bottom: 10px;">
                            <tr>
                                <td style="text-align: justify; width: 40%;"><strong>No. Ficha</strong></td>
                                <td style="text-align: justify"></td>
                            </tr>
                            <tr>
                                <td style="text-align: justify"><strong>Nombre Programa</strong></td>
                                <td style="text-align: justify"></td>
                            </tr>
                            <tr>
                                <td style="text-align: justify"><strong>Actividad realizada</strong></td>
                                <td style="text-align: justify"></td>
                            </tr>
                            <tr>
                                <td style="text-align: justify"><strong>Horas asignadas</strong></td>
                                <td style="text-align: justify">

                                </td>
                            </tr>
                            <tr>
                                <td style="text-align: justify"><strong>Observación</strong></td>
                                <td style="text-align: justify">

                                </td>
                            </tr>
                        </table>

                    </td>
                    <td style="vertical-align: middle; text-align: justify;">{{ $actividadesContrato[3]->evidencias }}
                    </td>
                </tr>
            @endif
            {{-- ── Filas dinámicas desde [3] en adelante ── --}}
            @foreach ($actividadesContrato->slice(4) as $actividad)
                <tr>
                    <td style="text-align:center;">{{ $loop->iteration + 4 }}</td>
                    <td style="vertical-align: middle; text-align: justify;">{{ $actividad->obligaciones }}</td>
                    <td style="vertical-align: middle; text-align: justify;">{{ $actividad->accionesRealizadas }}</td>
                    <td style="vertical-align: middle; text-align: justify;">{{ $actividad->evidencias }}</td>
                </tr>
            @endforeach
            {{-- ── Fila extra: Actividades del instructor ── --}}
            @if (isset($actividades) && $actividades->count() > 0)
                <tr>
                    <td style="text-align:center;">{{ $actividadesContrato->count() + 1 }}</td>
                    <td style="vertical-align: middle; text-align: justify;">
                        LAS DEMÁS QUE SE REQUIERAN PARA EL CUMPLIMIENTO DEL OBJETO CONTRACTUAL ESPECIFICO Y QUE EL
                        CENTRO DE FORMACIÓN DEMANDE.
                    </td>
                    <td style="vertical-align: middle; text-align: justify;">
                        REPORTAR DETALLE DE ACCIONES REALIZADAS
                        <table style="width: 100%; border-collapse: collapse; border: 1px solid #000;">
                            <thead>
                                <tr>
                                    <th
                                        style="font-size: 9px; padding: 3px; border: 1px solid #000; text-align: justify; width: 55%;">
                                        ACTIVIDAD REALIZADA
                                    </th>
                                    <th
                                        style="font-size: 9px; padding: 3px; border: 1px solid #000; text-align: center; width: 20%;">
                                        HORAS EJECUTADAS
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                @php $totalHoras = 0; @endphp
                                @foreach ($actividades as $act)
                                    @php $totalHoras += $act->numeroHoras; @endphp
                                    <tr>
                                        <td
                                            style="font-size: 9px; padding: 3px; border: 1px solid #000; text-align: justify;">
                                            {{ $act->descripcion }}
                                        </td>
                                        <td
                                            style="font-size: 9px; padding: 3px; border: 1px solid #000; text-align: center;">
                                            {{ $act->numeroHoras }}
                                        </td>
                                    </tr>
                                @endforeach
                                <tr>
                                    <td colspan="1"
                                        style="font-size: 9px; padding: 3px; border: 1px solid #000; text-align: right;">
                                        <strong>TOTAL HORAS EJECUTADAS</strong>
                                    </td>
                                    <td
                                        style="font-size: 9px; padding: 3px; border: 1px solid #000; text-align: center;">
                                        <strong>{{ $totalHoras }}</strong>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </td>
                    <td style="vertical-align: middle; text-align: justify;"></td>
                </tr>
            @endif
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

        @forelse ($comisiones as $comision)
            <tr>
                <td>{{ $comision->item }}</td>
                <td>{{ $comision->numeroViaje }}</td>
                <td>{{ $comision->lugarDesplazamiento }}</td>
                <td>{{ \Carbon\Carbon::parse($comision->fechaInicialDesplazamiento)->format('Y-m-d') }}</td>
                <td>{{ \Carbon\Carbon::parse($comision->fechaFinalDesplazamiento)->format('Y-m-d') }}</td>
            </tr>
        @empty
            <tr>
                <td></td>
                <td></td>
                <td></td>
                <td></td>
                <td></td>
            </tr>
        @endforelse
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
    @if ($contrato->persona->firmaDigital)
        <img src="{{ storage_path('app/public/firmas/' . basename($contrato->persona->firmaDigital)) }}"
            style="height: 70px; max-width: 250px; object-fit: contain;" />
    @else
        <div class="spacer-higth"></div>
    @endif
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

    </div>
    @if ($coordinador->firmaDigital)
        <img src="{{ storage_path('app/public/firmas/' . basename($coordinador->firmaDigital)) }}"
            style="height: 70px; max-width: 250px; object-fit: contain;" />
    @else
        <div class="spacer-higth"></div>
    @endif

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

        $textoFooter = "GCCON-F-087 V2";
        $anchoTexto  = $fontMetrics->getTextWidth($textoFooter, $font, 8);
        $xCentrado   = ($anchoPagina - $anchoTexto) / 2;

        $pdf->page_text($xCentrado, 770, $textoFooter, $font, 8, [0,0,0]);
        $pdf->page_text(470, 770, "{PAGE_NUM}", $font, 8, [0,0,0]);
    }
    </script>

</body>

</html>
