@extends('mails.layouts.corporate')

@section('titulo', 'Actualización de documentos personales')
@section('etiqueta', 'Recordatorio')
@section('preheader', 'Es momento de actualizar sus documentos personales.')

@section('contenido')
    <p style="margin:0 0 16px 0;">Estimado(a) {{ $person->nombre1 . ' ' . $person->apellido1 }}:</p>
    <p style="margin:0 0 16px 0;">
        Le solicitamos actualizar nuevamente sus documentos personales. Este proceso se realiza cada semestre para mantener vigente la verificación de antecedentes.
    </p>
    <p style="margin:0;">Su información es confidencial y no se comparte con terceros. Gracias por su colaboración.</p>
@endsection
