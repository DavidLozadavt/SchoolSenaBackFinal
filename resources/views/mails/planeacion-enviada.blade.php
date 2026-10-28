<p>Hola,</p>
<p>El docente <strong>{{ $docente }}</strong> ha enviado la planeación pedagógica de la semana
<strong>{{ $plan->semanaInicio->format('d/m/Y') }} – {{ $plan->semanaFin->format('d/m/Y') }}</strong>
(nivel {{ $plan->nivel }}@if($plan->grado), grado {{ $plan->grado }}@endif).</p>
<p>Adjunto encontrará el PDF institucional para revisión.</p>
<p>School by VirtualTechnology</p>
