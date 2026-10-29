@extends('mails.layouts.corporate')

@section('titulo', 'Nueva planeación pedagógica para revisión')
@section('etiqueta', 'Planeación pedagógica')
@section('preheader', $docente . ' envió la planeación de la semana del ' . $plan->semanaInicio->format('d/m/Y') . '.')

@section('contenido')
    <p style="margin:0 0 16px 0;">Cordial saludo:</p>
    <p style="margin:0;">
        El docente <strong>{{ $docente }}</strong> envió su planeación pedagógica para revisión.
    </p>

    @include('mails.partials.datos', [
        'filas' => [
            ['Docente', $docente],
            ['Semana', $plan->semanaInicio->format('d/m/Y') . ' – ' . $plan->semanaFin->format('d/m/Y')],
            ['Nivel', $plan->nivel],
            ['Grado', $plan->grado ?: null],
        ],
    ])

    @include('mails.partials.aviso', [
        'titulo' => 'Documento adjunto',
        'texto' => 'La planeación en formato PDF se encuentra adjunta a este correo.',
    ])
@endsection
