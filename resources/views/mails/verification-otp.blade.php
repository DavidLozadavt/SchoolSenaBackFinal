@extends('mails.layouts.corporate')

@section('titulo', 'Código de verificación')
@section('etiqueta', 'Seguridad de la cuenta')
@section('preheader', 'Use este código para restablecer su contraseña. Es válido por 10 minutos.')

@section('contenido')
    <p style="margin:0;">
        Recibimos una solicitud para restablecer la contraseña de su cuenta. Ingrese el siguiente código en la pantalla de verificación para continuar.
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:28px 0 0 0;">
        <tr>
            <td align="center" style="padding:24px 16px; background-color:#f8fafc; border:1px solid #e2e8f0;">
                <span style="font-family:'Courier New', Courier, monospace; font-size:32px; line-height:40px; font-weight:bold; letter-spacing:8px; color:#0f172a;">{{ $otp }}</span>
                <p style="margin:8px 0 0 0; font-family:Arial, Helvetica, sans-serif; font-size:12px; line-height:18px; color:#64748b;">Válido por 10 minutos</p>
            </td>
        </tr>
    </table>

    @include('mails.partials.aviso', [
        'titulo' => '¿No solicitó este código?',
        'texto' => 'Puede ignorar este mensaje. Su contraseña actual no se modificará. Nunca comparta este código con otras personas.',
        'tono' => 'alerta',
    ])
@endsection
