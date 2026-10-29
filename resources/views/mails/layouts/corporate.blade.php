{{--
    Layout de los correos: encabezado azul con logo centrado, tarjeta blanca y pie claro.
    Tablas + estilos inline para Gmail, Outlook, Apple Mail y móviles.

    Secciones:
      titulo       Título principal (va en el encabezado).
      subtitulo    Línea bajo el título (opcional).
      insignia     Estado corto bajo el subtítulo (opcional).
      preheader    Texto de vista previa del cliente de correo.
      contenido    Cuerpo del mensaje.
      pie          Texto adicional del pie (opcional).

    El logo se incrusta en el correo (CID) cuando la vista se envía como correo ($message),
    así se ve aunque no haya una URL pública; fuera de un envío se usa config('mail.brand.logo').
--}}
@php
    $marca = config('mail.brand');
    $archivoLogo = public_path('default/logoweb-white.png');
    $logo = (isset($message) && $message instanceof \Illuminate\Mail\Message && is_file($archivoLogo))
        ? $message->embed($archivoLogo)
        : $marca['logo'];
@endphp
<!DOCTYPE html>
<html lang="es" xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="x-apple-disable-message-reformatting">
    <title>@yield('titulo')</title>
    <style type="text/css">
        body { margin: 0; padding: 0; width: 100% !important; -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
        table { border-collapse: collapse; }
        img { border: 0; outline: none; text-decoration: none; }
        @media only screen and (max-width: 620px) {
            .contenedor { width: 100% !important; }
            .px { padding-left: 22px !important; padding-right: 22px !important; }
            .titulo { font-size: 20px !important; line-height: 28px !important; }
        }
    </style>
</head>
<body style="margin:0; padding:0; background-color:#f0f4f8;">
    <div style="display:none; max-height:0; overflow:hidden; font-size:1px; line-height:1px; color:#f0f4f8;">
        @yield('preheader')
    </div>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f0f4f8;">
        <tr>
            <td align="center" style="padding:30px 12px;">
                <table role="presentation" class="contenedor" width="600" cellpadding="0" cellspacing="0" border="0" style="width:600px; max-width:600px; background-color:#ffffff; border-radius:16px; overflow:hidden; box-shadow:0 4px 24px rgba(0,0,0,0.08);">

                    {{-- Encabezado --}}
                    <tr>
                        <td class="px" align="center" bgcolor="#283593" style="background-color:#283593; background-image:linear-gradient(135deg, #1a237e 0%, #283593 60%, #3949ab 100%); padding:36px 40px; text-align:center; font-family:'Helvetica Neue', Helvetica, Arial, sans-serif;">
                            <img src="{{ $logo }}" width="180" alt="School · Virtual Technology" style="display:block; width:180px; max-width:180px; height:auto; margin:0 auto 22px auto;">
                            <h1 class="titulo" style="margin:0 0 6px 0; font-size:22px; line-height:30px; font-weight:bold; color:#ffffff;">@yield('titulo')</h1>
                            @hasSection('subtitulo')
                                <p style="margin:0; font-size:13px; line-height:20px; color:#c5cae9;">@yield('subtitulo')</p>
                            @elseif($__env->hasSection('etiqueta'))
                                <p style="margin:0; font-size:13px; line-height:20px; color:#c5cae9;">@yield('etiqueta')</p>
                            @endif
                            @hasSection('insignia')
                                <table role="presentation" cellpadding="0" cellspacing="0" border="0" align="center" style="margin:14px auto 0 auto;">
                                    <tr>
                                        <td style="padding:5px 14px; border:1px solid #7986cb; border-radius:20px; font-size:10px; line-height:14px; font-weight:bold; letter-spacing:1.5px; text-transform:uppercase; color:#ffffff;">@yield('insignia')</td>
                                    </tr>
                                </table>
                            @endif
                        </td>
                    </tr>

                    {{-- Contenido --}}
                    <tr>
                        <td class="px" style="padding:36px 40px; font-family:'Helvetica Neue', Helvetica, Arial, sans-serif; font-size:14px; line-height:22px; color:#555555;">
                            @yield('contenido')
                        </td>
                    </tr>

                    {{-- Pie --}}
                    <tr>
                        <td class="px" align="center" style="padding:24px 40px; background-color:#f8f9ff; border-top:1px solid #e8eaf0; text-align:center; font-family:'Helvetica Neue', Helvetica, Arial, sans-serif; font-size:11px; line-height:18px; color:#9e9e9e;">
                            @hasSection('pie')
                                <p style="margin:0 0 8px 0;">@yield('pie')</p>
                            @endif
                            <p style="margin:0 0 8px 0;">Este correo fue generado automáticamente. Por favor no responda a este mensaje.</p>
                            <p style="margin:0;"><strong style="color:#3949ab;">School · Virtual Technology</strong> &nbsp;·&nbsp; &copy; {{ date('Y') }} Todos los derechos reservados.</p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>
