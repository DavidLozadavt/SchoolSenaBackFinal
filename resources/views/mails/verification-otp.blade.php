<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Código de Verificación - Virtual Technology</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            background-color: #f8fafc;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            color: #334155;
            -webkit-font-smoothing: antialiased;
        }
        .container {
            max-width: 550px;
            margin: 40px auto;
            background-color: #ffffff;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.05);
            border: 1px solid #e2e8f0;
        }
        .header {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            padding: 35px 30px;
            text-align: center;
            border-bottom: 4px solid #f97316; /* Naranja ERP */
        }
        .logo {
            max-height: 48px;
            width: auto;
        }
        .content {
            padding: 40px 35px;
            text-align: center;
        }
        h1 {
            font-size: 22px;
            color: #0f172a;
            margin-top: 0;
            margin-bottom: 12px;
            font-weight: 700;
            letter-spacing: -0.025em;
        }
        .subtitle {
            font-size: 15px;
            color: #64748b;
            margin-top: 0;
            margin-bottom: 25px;
        }
        p {
            font-size: 15px;
            line-height: 1.625;
            color: #475569;
            margin: 0 0 20px 0;
            text-align: left;
        }
        .otp-box {
            background-color: #fff7ed;
            border: 2px dashed #ffedd5;
            border-radius: 12px;
            padding: 24px 30px;
            margin: 30px auto;
            display: inline-block;
            text-align: center;
            cursor: pointer;
        }
        .otp-code {
            font-family: 'Consolas', 'Courier New', Courier, monospace;
            font-size: 42px;
            font-weight: 700;
            color: #ea580c; /* Naranja corporativo */
            letter-spacing: 5px;
            margin: 0;
            user-select: all;
            -webkit-user-select: all;
            -moz-user-select: all;
            -ms-user-select: all;
        }
        .otp-hint {
            font-size: 12px;
            color: #94a3b8;
            margin-top: 8px;
            margin-bottom: 0;
        }
        .timer-badge {
            display: inline-flex;
            align-items: center;
            background-color: #fee2e2;
            color: #991b1b;
            padding: 6px 12px;
            border-radius: 9999px;
            font-size: 13px;
            font-weight: 600;
            margin-top: 10px;
        }
        .timer-icon {
            margin-right: 4px;
        }
        .divider {
            height: 1px;
            background-color: #e2e8f0;
            margin: 35px 0;
        }
        .support-text {
            font-size: 13px;
            color: #94a3b8;
            line-height: 1.5;
            margin: 0;
            text-align: left;
        }
        .footer {
            background-color: #f8fafc;
            padding: 30px;
            text-align: center;
            border-top: 1px solid #e2e8f0;
        }
        .footer-text {
            font-size: 12px;
            color: #64748b;
            margin: 4px 0;
            line-height: 1.5;
        }
        .footer-links {
            margin-top: 12px;
        }
        .footer-links a {
            color: #ea580c;
            text-decoration: none;
            font-weight: 600;
            font-size: 13px;
            margin: 0 8px;
        }
        .footer-links a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <img src="https://admin.virtualt.org/default/logoweb.png" alt="Virtual Technology Logo" class="logo">
        </div>
        <div class="content">
            <h1>Código de Verificación</h1>
            <div class="subtitle">Restablecimiento de contraseña</div>
            
            <p>Hola,</p>
            <p>Has solicitado un código de verificación para restablecer la contraseña de tu cuenta en la plataforma de <strong>Virtual Technology</strong>.</p>
            <p>Por favor, copia el código que aparece a continuación e introdúcelo en la pantalla de verificación para continuar:</p>
            
            <div class="otp-box" title="Haz doble clic para seleccionar y copiar">
                <div class="otp-code">{{ $otp }}</div>
                <div class="otp-hint">Doble clic para seleccionar y copiar</div>
            </div>
            
            <div>
                <span class="timer-badge">
                    <span class="timer-icon">⏱</span>
                    Válido por 10 minutos
                </span>
            </div>
            
            <div class="divider"></div>
            
            <p class="support-text">
                <strong>¿No solicitaste este código?</strong><br>
                Si no has iniciado este proceso, puedes ignorar este correo de forma segura. Tu contraseña actual no sufrirá ningún cambio.
            </p>
        </div>
        <div class="footer">
            <p class="footer-text" style="font-weight: 600; color: #334155;">Virtual Technology ERP & School</p>
            <p class="footer-text">Este es un correo electrónico generado automáticamente. Por favor, no respondas a este mensaje.</p>
            <div class="footer-links">
                <a href="https://virtualt.org" target="_blank">Página Web</a>
                <span style="color: #cbd5e1;">|</span>
                <a href="mailto:soporte@virtualt.org">Soporte Técnico</a>
            </div>
            <p class="footer-text" style="margin-top: 15px; font-size: 11px; color: #94a3b8;">&copy; {{ date('Y') }} Virtual Technology. Todos los derechos reservados.</p>
        </div>
    </div>
</body>
</html>
