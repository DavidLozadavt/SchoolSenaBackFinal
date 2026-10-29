@extends('mails.layouts.corporate')

@section('titulo', 'Su solicitud fue revisada')
@section('etiqueta', 'Solicitud #' . $solicitud->id)
@section('preheader', 'Estado de su solicitud: ' . $solicitud->estado)

@section('contenido')
    <p style="margin:0 0 16px 0;">
        Estimado(a) {{ $solicitud->configuracionServicio->user->persona->nombre1 . ' ' . $solicitud->configuracionServicio->user->persona->apellido1 }}:
    </p>
    <p style="margin:0;">Su solicitud con código <strong>#{{ $solicitud->id }}</strong> fue revisada.</p>

    @include('mails.partials.datos', [
        'filas' => [
            ['Solicitud', '#' . $solicitud->id],
            ['Estado', $solicitud->estado],
            ['Observaciones', $solicitud->descripcion],
        ],
        'destacar' => 'Estado',
    ])
@endsection
