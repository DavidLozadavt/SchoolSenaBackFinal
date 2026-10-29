@extends('mails.layouts.corporate')

@section('titulo', 'Solicitud de inscripción recibida')
@section('subtitulo', 'Su proceso de inscripción está en marcha')
@section('insignia', $estadoInscripcion)
@section('preheader')
    {{ $valorTotal > 0 ? 'Consulte el valor a pagar y complete el pago desde su portal de inscripción.' : 'Consulte el estado de su solicitud desde su portal de inscripción.' }}
@endsection

@section('contenido')
    <p style="margin:0 0 8px 0; font-size:16px; line-height:24px; font-weight:600; color:#1a1a2e;">Estimado(a) {{ $nombreAspirante }},</p>
    <p style="margin:0 0 28px 0; font-size:14px; line-height:22px; color:#555555;">
        Recibimos su solicitud de inscripción al programa <strong style="color:#1a1a2e;">{{ $programa }}</strong>.
        @if($valorTotal > 0)
            A continuación encontrará el resumen de su proceso y el enlace para consultar el estado y realizar el pago.
        @else
            A continuación encontrará el resumen de su proceso y el enlace para consultar su estado.
        @endif
    </p>

    @include('mails.partials.datos', [
        'titulo' => 'Detalle de su solicitud',
        'filas' => [
            ['Nombre', $nombreAspirante],
            ['Documento', $documento ?? null],
            ['Programa', $programa],
            ['N.º de factura', $numeroFactura],
            ['Estado', $estadoInscripcion],
            ['Fecha límite de pago', $fechaLimite ?? null],
        ],
        'colores' => ['Estado' => '#1a237e', 'Fecha límite de pago' => '#c62828'],
    ])

    @if($valorTotal > 0)
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 28px 0;">
            <tr>
                <td align="center" bgcolor="#e8f5e9" style="padding:20px 24px; background-color:#e8f5e9; border:1.5px solid #a5d6a7; border-radius:12px; text-align:center;">
                    <p style="margin:0 0 6px 0; font-size:10px; line-height:14px; font-weight:bold; letter-spacing:1.5px; text-transform:uppercase; color:#388e3c;">Valor total a pagar</p>
                    <p style="margin:0; font-size:30px; line-height:36px; font-weight:800; color:#1b5e20;">${{ number_format($valorTotal, 0, ',', '.') }} COP</p>
                    <p style="margin:4px 0 0 0; font-size:11px; line-height:16px; color:#666666;">Corresponde al proceso de inscripción al programa seleccionado.</p>
                </td>
            </tr>
        </table>
    @endif

    @if($valorTotal > 0 && !empty($metodosPago))
        @include('mails.partials.seccion', ['texto' => 'Medios de pago'])
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 4px 0; background-color:#fafafa; border:1px solid #eeeeee; border-radius:12px;">
            <tr>
                <td style="padding:18px 22px;">
                    @foreach($metodosPago as $metodo)
                        <p style="margin:0 {{ $loop->last ? '' : '0 10px 0' }}; font-size:13px; line-height:20px; color:#444444;">
                            <strong style="color:#1a1a2e;">{{ $metodo['nombre'] }}:</strong> {{ $metodo['detalle'] }}
                        </p>
                    @endforeach
                </td>
            </tr>
        </table>
    @endif

    @include('mails.partials.boton', [
        'url' => $portalUrl,
        'texto' => $valorTotal > 0 ? 'Pagar y consultar estado' : 'Consultar estado de mi solicitud',
        'enlaceAlterno' => true,
    ])

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 28px 0;">
        <tr><td style="border-top:1px solid #eeeeee; font-size:0; line-height:0;">&nbsp;</td></tr>
    </table>

    @include('mails.partials.seccion', ['texto' => 'Próximos pasos'])
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 28px 0; background-color:#fafafa; border:1px solid #eeeeee; border-radius:12px;">
        <tr>
            <td style="padding:18px 22px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                    @foreach([
                        'Haga clic en el botón para acceder a su portal personal de inscripción.',
                        'Verifique el valor a pagar y los datos de su factura.',
                        'Realice el pago por el medio de su preferencia.',
                        'Una vez validado el pago, recibirá la confirmación de su matrícula.',
                    ] as $i => $paso)
                        <tr>
                            <td width="30" valign="top" style="padding:{{ $loop->first ? '0' : '10px' }} 0 0 0;">
                                <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                                    <tr>
                                        <td align="center" valign="middle" width="20" height="20" bgcolor="#3949ab" style="width:20px; height:20px; background-color:#3949ab; border-radius:10px; font-family:'Helvetica Neue', Helvetica, Arial, sans-serif; font-size:10px; line-height:20px; font-weight:bold; color:#ffffff; text-align:center;">{{ $i + 1 }}</td>
                                    </tr>
                                </table>
                            </td>
                            <td valign="top" style="padding:{{ $loop->first ? '0' : '10px' }} 0 0 0; font-family:'Helvetica Neue', Helvetica, Arial, sans-serif; font-size:13px; line-height:20px; color:#444444;">{{ $paso }}</td>
                        </tr>
                    @endforeach
                </table>
            </td>
        </tr>
    </table>

    <p style="margin:0; font-size:13px; line-height:20px; color:#777777;">
        Si tiene alguna duda o inconveniente, comuníquese con el área de admisiones de la institución.
    </p>
@endsection
