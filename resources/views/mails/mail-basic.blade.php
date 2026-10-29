{{-- Correo con comprobante adjunto (App\Mail\MailBillService): asunto + mensaje en texto plano de la empresa emisora. --}}
@extends('mails.layouts.corporate', ['remitente' => $company->razonSocial ?? null])

@section('titulo', $subject)
@section('etiqueta', 'Comprobante de pago')
@section('preheader', \Illuminate\Support\Str::limit(preg_replace('/\s+/', ' ', strip_tags($messageContent)), 110))

@section('contenido')
    <div style="margin:0; white-space:pre-line;">{{ trim($messageContent) }}</div>

    @include('mails.partials.aviso', [
        'titulo' => 'Documento adjunto',
        'texto' => 'Encontrará el comprobante en formato PDF adjunto a este correo. Le recomendamos conservarlo para sus registros.',
    ])
@endsection
