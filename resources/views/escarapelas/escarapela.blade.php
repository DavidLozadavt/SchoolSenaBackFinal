<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Escarapela - {{ $escarapelas[0]['evento']['nombre'] ?? '' }}</title>
    <style>
        @page { margin: 0; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: DejaVu Sans, sans-serif; color: #18181b; }
        /* Una escarapela por página: lo que va anclado al pie se ubica dentro de su propia hoja. */
        .hoja { position: relative; width: {{ $pagina['ancho'] }}pt; height: {{ $pagina['alto'] }}pt; overflow: hidden; page-break-inside: avoid; }
        .salto { page-break-before: always; }
        .cabecera { color: #ffffff; text-align: center; padding: 16pt 14pt 14pt; }
        .empresa { font-size: 7.5pt; letter-spacing: 1.5pt; text-transform: uppercase; opacity: .9; }
        .evento { font-size: 15pt; font-weight: bold; line-height: 1.15; margin-top: 5pt; }
        .fecha { font-size: 8pt; margin-top: 5pt; }
        .cuerpo { text-align: center; padding: 16pt 16pt 0; }
        .etiqueta { font-size: 6.5pt; letter-spacing: 1.5pt; text-transform: uppercase; color: #71717a; }
        .nombre { font-size: 17pt; font-weight: bold; line-height: 1.15; margin: 3pt 0 9pt; }
        .identificacion { font-size: 11pt; font-weight: bold; margin-top: 3pt; }
        .campo { font-size: 8.5pt; font-weight: bold; margin-top: 5pt; }
        /* Solo el marco: el QR lo dibuja EscarapelaService sobre el lienzo, en estas mismas coordenadas. */
        .qr { position: absolute; left: {{ ($pagina['ancho'] - $cajaQr['lado']) / 2 }}pt; bottom: {{ $cajaQr['margenInferior'] }}pt; width: {{ $cajaQr['lado'] }}pt; height: {{ $cajaQr['lado'] }}pt; border-width: 1.5pt; border-style: solid; border-radius: 8pt; background: #ffffff; }
        .pie { position: absolute; bottom: 0; left: 0; right: 0; text-align: center; font-size: 6.5pt; color: #71717a; padding: 0 14pt 9pt; }
        .franja { position: absolute; bottom: 0; left: 0; right: 0; height: 4pt; }
    </style>
</head>
<body>
    @foreach ($escarapelas as $e)
        <div class="hoja {{ $loop->first ? '' : 'salto' }}" style="background: {{ $e['colorFondo'] ?? '#ffffff' }}; color: {{ $e['colorTexto'] ?? '#18181b' }};">
            <div class="cabecera" style="background: {{ $e['color'] }};">
                @if (!empty($e['empresa']['razonSocial']))
                    <div class="empresa">{{ $e['empresa']['razonSocial'] }}</div>
                @endif
                <div class="evento">{{ $e['evento']['nombre'] }}</div>
                @if (!empty($e['evento']['fechaInicial']))
                    <div class="fecha">
                        {{ \Carbon\Carbon::parse($e['evento']['fechaInicial'])->locale('es')->translatedFormat('j \d\e F \d\e Y') }}@if (!empty($e['evento']['hora'])) · {{ $e['evento']['hora'] }}@endif
                    </div>
                @endif
                @if (!empty($e['ubicacion']))
                    <div class="fecha">{{ $e['ubicacion'] }}</div>
                @endif
            </div>

            <div class="cuerpo">
                <div class="etiqueta">Asistente</div>
                <div class="nombre">{{ $e['nombre'] ?: 'Asistente' }}</div>

                @if (!empty($e['identificacion']))
                    <div class="etiqueta">Identificación</div>
                    <div class="identificacion">{{ $e['identificacion'] }}</div>
                @endif

                @foreach ($e['campos'] ?? [] as $campo)
                    <div class="campo">
                        @if (!empty($campo['etiqueta']))<span class="etiqueta">{{ $campo['etiqueta'] }}</span> @endif{{ $campo['valor'] }}
                    </div>
                @endforeach
            </div>

            <div class="qr" style="border-color: {{ $e['color'] }};"></div>

            <div class="pie">{{ $e['mensaje'] ?? '' }}</div>
            <div class="franja" style="background: {{ $e['color'] }};"></div>
        </div>
    @endforeach
</body>
</html>
