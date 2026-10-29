{{-- Correo genérico de notificaciones (App\Mail\MailService): asunto + mensaje en texto plano. --}}
@extends('mails.layouts.corporate', ['remitente' => $remitente ?? null])

@section('titulo', $subject)
@section('preheader', \Illuminate\Support\Str::limit(preg_replace('/\s+/', ' ', strip_tags($messageContent)), 110))

@section('contenido')
    <div style="margin:0; white-space:pre-line;">{{ trim($messageContent) }}</div>
@endsection
