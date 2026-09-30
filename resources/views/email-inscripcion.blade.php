<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional //EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Solicitud de Inscripción Recibida</title>
    <style type="text/css">
        body { margin: 0; padding: 0; background-color: #f0f4f8; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; }
        a { text-decoration: none; }
        img { border: 0; display: block; }
        .wrapper { width: 100%; background-color: #f0f4f8; padding: 30px 0; }
        .container { max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 24px rgba(0,0,0,0.08); }
        .header { background: linear-gradient(135deg, #1a237e 0%, #283593 60%, #3949ab 100%); padding: 36px 40px; text-align: center; }
        .header img { width: 180px; max-width: 180px; height: auto; margin: 0 auto 20px auto; }
        .header h1 { color: #ffffff; font-size: 22px; font-weight: 700; margin: 0 0 6px 0; letter-spacing: -0.3px; }
        .header p { color: rgba(255,255,255,0.75); font-size: 13px; margin: 0; }
        .badge { display: inline-block; background-color: rgba(255,255,255,0.15); color: #ffffff; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 1.5px; padding: 4px 12px; border-radius: 20px; margin-top: 12px; border: 1px solid rgba(255,255,255,0.25); }
        .body { padding: 36px 40px; }
        .greeting { font-size: 16px; color: #1a1a2e; font-weight: 600; margin: 0 0 8px 0; }
        .intro { font-size: 14px; color: #555; line-height: 22px; margin: 0 0 28px 0; }
        .section-title { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 1.5px; color: #3949ab; margin: 0 0 12px 0; }
        .info-table { width: 100%; border-collapse: collapse; background-color: #f8f9ff; border-radius: 12px; overflow: hidden; margin-bottom: 28px; }
        .info-table tr { border-bottom: 1px solid #e8eaf0; }
        .info-table tr:last-child { border-bottom: none; }
        .info-table td { padding: 12px 16px; font-size: 13px; }
        .info-table td.label { color: #888; font-weight: 600; width: 40%; }
        .info-table td.value { color: #1a1a2e; font-weight: 700; }
        .value-box { background: linear-gradient(135deg, #e8f5e9 0%, #f1f8e9 100%); border: 1.5px solid #a5d6a7; border-radius: 12px; padding: 20px 24px; margin-bottom: 28px; text-align: center; }
        .value-box .label-sm { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 1.5px; color: #388e3c; margin: 0 0 6px 0; }
        .value-box .amount { font-size: 30px; font-weight: 800; color: #1b5e20; margin: 0; letter-spacing: -1px; }
        .value-box .sub { font-size: 11px; color: #666; margin: 4px 0 0 0; }
        .cta-wrapper { text-align: center; margin: 32px 0; }
        .cta-btn { display: inline-block; background: linear-gradient(135deg, #1a237e 0%, #3949ab 100%); color: #ffffff !important; font-size: 13px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; padding: 16px 36px; border-radius: 50px; text-decoration: none !important; box-shadow: 0 4px 16px rgba(57,73,171,0.4); }
        .cta-note { font-size: 11px; color: #999; margin: 12px 0 0 0; text-align: center; }
        .divider { border: none; border-top: 1px solid #eee; margin: 28px 0; }
        .steps { background-color: #fafafa; border: 1px solid #eee; border-radius: 12px; padding: 20px 24px; margin-bottom: 28px; }
        .steps p { font-size: 13px; color: #444; margin: 0 0 10px 0; line-height: 20px; }
        .steps p:last-child { margin-bottom: 0; }
        .steps .step-num { display: inline-block; background-color: #3949ab; color: #fff; width: 20px; height: 20px; border-radius: 50%; font-size: 10px; font-weight: 700; text-align: center; line-height: 20px; margin-right: 8px; vertical-align: middle; }
        .footer { background-color: #f8f9ff; padding: 24px 40px; text-align: center; border-top: 1px solid #e8eaf0; }
        .footer p { font-size: 11px; color: #aaa; margin: 4px 0; }
    </style>
</head>
<body>
<div class="wrapper">
    <div class="container">

        <!-- Header -->
        <div class="header">
            <img src="https://sena-school.virtualt.org/media/app/logoweb.png" alt="{{ $nombreInstitucion }}" />
            <h1>¡Solicitud Recibida!</h1>
            <p>Su proceso de inscripción está en marcha</p>
            <span class="badge">{{ $estadoInscripcion }}</span>
        </div>

        <!-- Body -->
        <div class="body">

            <p class="greeting">Estimado/a {{ $nombreAspirante }},</p>
            <p class="intro">
                Su solicitud de inscripción ha sido revisada por el equipo administrativo de <strong>{{ $nombreInstitucion }}</strong>.
                A continuación encontrará el resumen de su proceso y el enlace para consultar el estado y continuar con el pago.
            </p>

            <!-- Info tabla -->
            <p class="section-title">📋 Detalle de su solicitud</p>
            <table class="info-table">
                <tr>
                    <td class="label">Nombre</td>
                    <td class="value">{{ $nombreAspirante }}</td>
                </tr>
                @if(!empty($documento))
                <tr>
                    <td class="label">Documento</td>
                    <td class="value">{{ $documento }}</td>
                </tr>
                @endif
                <tr>
                    <td class="label">Programa</td>
                    <td class="value">{{ $programa }}</td>
                </tr>
                <tr>
                    <td class="label">N° Factura</td>
                    <td class="value">{{ $numeroFactura }}</td>
                </tr>
                <tr>
                    <td class="label">Estado</td>
                    <td class="value" style="color:#1a237e;">{{ $estadoInscripcion }}</td>
                </tr>
                @if(!empty($fechaLimite))
                <tr>
                    <td class="label">Fecha límite de pago</td>
                    <td class="value" style="color:#c62828;">{{ $fechaLimite }}</td>
                </tr>
                @endif
            </table>

            <!-- Valor a pagar -->
            @if($valorTotal > 0)
            <div class="value-box">
                <p class="label-sm">💰 Valor total a pagar</p>
                <p class="amount">${{ number_format($valorTotal, 0, ',', '.') }} COP</p>
                <p class="sub">Este valor corresponde al proceso de inscripción al programa seleccionado.</p>
            </div>
            @endif

            <!-- Botón CTA -->
            <div class="cta-wrapper">
                <a href="{{ $portalUrl }}" class="cta-btn" target="_blank">
                    CONSULTAR ESTADO Y CONTINUAR PROCESO
                </a>
                <p class="cta-note">Si el botón no funciona, copie y pegue este enlace en su navegador:<br>
                    <span style="color:#3949ab; word-break: break-all; font-size:10px;">{{ $portalUrl }}</span>
                </p>
            </div>

            <hr class="divider">

            <!-- Pasos siguientes -->
            <p class="section-title">📌 Próximos pasos</p>
            <div class="steps">
                <p><span class="step-num">1</span> Haga clic en el botón para acceder a su portal personal de inscripción.</p>
                <p><span class="step-num">2</span> Verifique el valor a pagar y los datos de su factura.</p>
                <p><span class="step-num">3</span> Realice el pago según las instrucciones indicadas en el portal.</p>
                <p><span class="step-num">4</span> Una vez validado el pago, recibirá confirmación de matrícula.</p>
            </div>

            <p style="font-size:13px; color:#777; line-height:20px;">
                Si tiene alguna duda o inconveniente, comuníquese con el equipo administrativo de <strong>{{ $nombreInstitucion }}</strong>.
                Este correo ha sido generado automáticamente — por favor no responda directamente a este mensaje.
            </p>
        </div>

        <!-- Footer -->
        <div class="footer">
            <p><strong style="color:#3949ab;">{{ $nombreInstitucion }}</strong></p>
            <p>Sistema de Gestión Académica · VirtualT</p>
            <p style="margin-top:8px;">© {{ date('Y') }} Todos los derechos reservados.</p>
        </div>

    </div>
</div>
</body>
</html>
