{{-- Botón de acción centrado. Variables: $url, $texto. Opcional: $enlaceAlterno (muestra el enlace en texto). --}}
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:32px 0;">
    <tr>
        <td align="center">
            <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                <tr>
                    <td align="center" bgcolor="#283593" style="background-color:#283593; background-image:linear-gradient(135deg, #1a237e 0%, #3949ab 100%); border-radius:50px;">
                        <a href="{{ $url }}" target="_blank" style="display:inline-block; padding:16px 36px; font-family:'Helvetica Neue', Helvetica, Arial, sans-serif; font-size:13px; line-height:16px; font-weight:bold; letter-spacing:1px; text-transform:uppercase; color:#ffffff; text-decoration:none; border-radius:50px;">{{ $texto }}</a>
                    </td>
                </tr>
            </table>
            @if(!empty($enlaceAlterno))
                <p style="margin:12px 0 0 0; font-family:'Helvetica Neue', Helvetica, Arial, sans-serif; font-size:11px; line-height:17px; color:#999999; text-align:center;">
                    Si el botón no funciona, copie y pegue este enlace en su navegador:<br>
                    <a href="{{ $url }}" target="_blank" style="color:#3949ab; font-size:10px; word-break:break-all; text-decoration:none;">{{ $url }}</a>
                </p>
            @endif
        </td>
    </tr>
</table>
