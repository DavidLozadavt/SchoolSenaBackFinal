<!DOCTYPE html>
<html lang="es">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <meta charset="utf-8">
    <title>Planeación pedagógica — Primaria</title>
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 9px; color: #252F4A; margin: 16px 18px; }
        h1 { font-size: 15px; color: #FF6F1E; margin: 0 0 2px; }
        h2 { font-size: 11px; color: #071437; margin: 14px 0 6px; border-bottom: 1.5px solid #FF6F1E; padding-bottom: 3px; }
        .sub { color: #78829D; margin: 0 0 10px; font-size: 8px; }
        .meta { background: #FFF5EF; border: 1px solid #FFD8BF; padding: 8px 10px; margin-bottom: 10px; }
        .meta td { padding: 2px 6px 2px 0; vertical-align: top; }
        .label { color: #78829D; width: 95px; }
        .box { border: 1px solid #DBDFE9; padding: 8px 10px; margin-bottom: 10px; }
        table.grid { width: 100%; border-collapse: collapse; margin: 0 0 10px; table-layout: fixed; }
        table.grid th, table.grid td { border: 1px solid #C4CADA; padding: 5px 6px; text-align: left; vertical-align: top; word-wrap: break-word; }
        table.grid th { background: #F1F1F4; color: #4B5675; font-size: 8px; font-weight: bold; }
        table.grid td { font-size: 8px; line-height: 1.35; }
        .muted { color: #78829D; }
        .footer { margin-top: 14px; font-size: 8px; color: #99A1B7; border-top: 1px solid #DBDFE9; padding-top: 6px; }
        .pill { display: inline-block; background: #EFF6FF; color: #056EE9; padding: 1px 6px; border-radius: 8px; font-size: 8px; }
        .hint { font-size: 8px; color: #78829D; margin: 0 0 6px; }
        ul.seq { margin: 0; padding-left: 12px; }
        ul.seq li { margin: 0 0 2px; }
    </style>
</head>
<body>
    <h1>Planeación pedagógica semanal — Primaria</h1>
    <p class="sub">School by VirtualTechnology · Articulada con PEI, plan de área, estándares, DBA y SIEE</p>

    <div class="meta">
        <table width="100%">
            <tr>
                <td class="label">Institución</td><td><strong>{{ $plan->institucion }}</strong></td>
                <td class="label">Docente</td><td><strong>{{ $plan->docenteNombre }}</strong></td>
            </tr>
            <tr>
                <td class="label">Grado</td><td>{{ $plan->grado ?? '—' }}</td>
                <td class="label">Periodo</td><td>{{ $plan->periodo ?? '—' }}</td>
            </tr>
            <tr>
                <td class="label">Semana</td><td>{{ $plan->semanaInicio->format('d/m/Y') }} – {{ $plan->semanaFin->format('d/m/Y') }}</td>
                <td class="label">Estado</td><td><span class="pill">{{ $plan->estado }}</span></td>
            </tr>
        </table>
    </div>

    <div class="box">
        <strong>Propósito de la semana:</strong>
        <p style="margin:4px 0 8px;">{{ $plan->proposito ?: 'Por definir' }}</p>
        <strong>Tema integrador:</strong> {{ $plan->temaIntegrador ?: '—' }}<br>
        <strong>Metodología:</strong> {{ $plan->metodologia ?: '—' }}
    </div>

    <h2>1. Tabla de planeación de clases</h2>
    <p class="hint">Cada fila es una clase. La información aparece una sola vez (sin bloques repetidos debajo).</p>
    <table class="grid">
        <thead>
            <tr>
                <th style="width:9%">Día / hora</th>
                <th style="width:10%">Asignatura</th>
                <th style="width:12%">Tema y pregunta</th>
                <th style="width:14%">Aprendizaje, DBA y competencia</th>
                <th style="width:18%">Secuencia didáctica</th>
                <th style="width:12%">Evaluación</th>
                <th style="width:12%">Recursos</th>
                <th style="width:13%">Refuerzo / profundización</th>
            </tr>
        </thead>
        <tbody>
        @forelse($plan->clases as $c)
            @php
                $secuencia = is_array($c->secuencia) ? $c->secuencia : [];
            @endphp
            <tr>
                <td>
                    <strong>{{ $c->diaNombre ?: '—' }}</strong><br>
                    <span class="muted">{{ $c->horaInicial }}–{{ $c->horaFinal }}</span>
                </td>
                <td>{{ $c->asignatura ?: '—' }}</td>
                <td>
                    <strong>{{ $c->tema ?: '—' }}</strong>
                    @if($c->preguntaProblematizadora)
                        <br><span class="muted">Pregunta:</span> {{ $c->preguntaProblematizadora }}
                    @endif
                    @if($c->saberesPrevios)
                        <br><span class="muted">Saberes previos:</span> {{ $c->saberesPrevios }}
                    @endif
                </td>
                <td>
                    @if($c->aprendizajeEsperado)
                        <strong>Aprendizaje:</strong> {{ $c->aprendizajeEsperado }}<br>
                    @endif
                    @if($c->estandar)
                        <span class="muted">Estándar:</span> {{ $c->estandar }}<br>
                    @endif
                    @if($c->dba)
                        <span class="muted">DBA:</span> {{ $c->dba }}<br>
                    @endif
                    @if($c->competencia)
                        <span class="muted">Competencia:</span> {{ $c->competencia }}
                    @endif
                    @if(!$c->aprendizajeEsperado && !$c->estandar && !$c->dba && !$c->competencia)
                        —
                    @endif
                </td>
                <td>
                    @if(count($secuencia))
                        <ul class="seq">
                            @foreach($secuencia as $s)
                                <li>
                                    <strong>{{ $s['momento'] ?? 'Momento' }}</strong>
                                    @if(!empty($s['tiempo'])) ({{ $s['tiempo'] }})@endif:
                                    {{ $s['actividad'] ?? '' }}
                                </li>
                            @endforeach
                        </ul>
                    @endif
                    @if($c->actividadPractica)
                        <br><span class="muted">Práctica:</span> {{ $c->actividadPractica }}
                    @endif
                    @if(!count($secuencia) && !$c->actividadPractica)
                        —
                    @endif
                </td>
                <td>
                    @if($c->evidencia)<span class="muted">Evidencia:</span> {{ $c->evidencia }}<br>@endif
                    @if($c->criterios)<span class="muted">Criterios:</span> {{ $c->criterios }}<br>@endif
                    @if($c->instrumento)<span class="muted">Instrumento:</span> {{ $c->instrumento }}@endif
                    @if(!$c->evidencia && !$c->criterios && !$c->instrumento)—@endif
                </td>
                <td>{{ $c->recursos ?: '—' }}</td>
                <td>
                    @if($c->refuerzo)<span class="muted">Refuerzo:</span> {{ $c->refuerzo }}<br>@endif
                    @if($c->profundizacion)<span class="muted">Profundización:</span> {{ $c->profundizacion }}@endif
                    @if(!$c->refuerzo && !$c->profundizacion)—@endif
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="8" class="muted">Sin clases registradas en esta planeación.</td>
            </tr>
        @endforelse
        </tbody>
    </table>

    @php
        $talleres = $plan->clases->filter(function ($c) {
            return filled($c->tallerTitulo)
                || filled($c->tallerContenido)
                || filled($c->tallerEstrategia)
                || filled($c->tallerEntregables)
                || $c->idActividad;
        });
    @endphp

    @if($talleres->isNotEmpty())
        <h2>2. Tabla de talleres (aula virtual)</h2>
        <p class="hint">
            Solo talleres vinculados a las clases. No se repiten tema, DBA ni evaluación de la tabla anterior.
        </p>
        <table class="grid">
            <thead>
                <tr>
                    <th style="width:10%">Día / asignatura</th>
                    <th style="width:16%">Título del taller</th>
                    <th style="width:16%">Estrategia</th>
                    <th style="width:16%">Entregables</th>
                    <th style="width:24%">Descripción / instrucciones</th>
                    <th style="width:10%">Plazo</th>
                    <th style="width:8%">Actividad</th>
                </tr>
            </thead>
            <tbody>
            @foreach($talleres as $c)
                <tr>
                    <td>
                        <strong>{{ $c->diaNombre ?: '—' }}</strong><br>
                        {{ $c->asignatura ?: '—' }}
                    </td>
                    <td>{{ $c->tallerTitulo ?: '—' }}</td>
                    <td>{{ $c->tallerEstrategia ?: '—' }}</td>
                    <td>{{ $c->tallerEntregables ?: '—' }}</td>
                    <td style="white-space: pre-wrap;">{{ $c->tallerContenido ?: '—' }}</td>
                    <td>
                        @if($c->tallerInicio || $c->tallerFin)
                            {{ $c->tallerInicio ? \Carbon\Carbon::parse($c->tallerInicio)->format('d/m/Y H:i') : '—' }}
                            <br>→<br>
                            {{ $c->tallerFin ? \Carbon\Carbon::parse($c->tallerFin)->format('d/m/Y H:i') : '—' }}
                        @else
                            —
                        @endif
                    </td>
                    <td>
                        @if($c->idActividad)
                            #{{ $c->idActividad }}
                        @else
                            —
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif

    @if($plan->reflexionDocente)
        <h2>3. Reflexión del docente</h2>
        <div class="box">{{ $plan->reflexionDocente }}</div>
    @endif

    <div class="footer">
        Documento para coordinación académica · {{ now()->format('d/m/Y H:i') }}
    </div>
</body>
</html>
