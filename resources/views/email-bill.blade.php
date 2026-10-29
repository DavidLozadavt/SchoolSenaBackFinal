{{-- Confirmación de plan contratado (App\Mail\BillMailService). --}}
@extends('mails.layouts.corporate')

@section('titulo', $subject)
@section('etiqueta', 'Suscripción')
@section('preheader', 'Detalle del plan ' . $plan->nombrePlan . ' activado en su cuenta.')

@section('contenido')
    <p style="margin:0;">Le confirmamos la activación del siguiente plan. A continuación encontrará el detalle de su suscripción.</p>

    @include('mails.partials.datos', [
        'filas' => [
            ['Código del plan', $plan->id],
            ['Plan', $plan->nombrePlan],
            ['Valor', '$ ' . number_format((float) $plan->valor, 0, ',', '.') . ' COP'],
            ['Usuarios incluidos', $plan->numeroUsuarios],
            ['Fecha de inicio', $vinculacion->fechaEstadoInicial],
            ['Fecha de finalización', $vinculacion->fechaEstadoFinal],
            ['Duración', $plan->periodoMeses . ' ' . ((int) $plan->periodoMeses === 1 ? 'mes' : 'meses')],
        ],
        'destacar' => 'Plan',
    ])

    @include('mails.partials.aviso', [
        'texto' => 'Le enviaremos un aviso cuando su plan esté próximo a finalizar.',
    ])

    <p style="margin:24px 0 0 0;">
        Si tiene alguna pregunta, escríbanos a
        <a href="mailto:virtualtsoftware@virtualt.org" style="color:#1d4ed8; text-decoration:none;">virtualtsoftware@virtualt.org</a>
        o comuníquese al +57 315 661 4275.
    </p>
@endsection
