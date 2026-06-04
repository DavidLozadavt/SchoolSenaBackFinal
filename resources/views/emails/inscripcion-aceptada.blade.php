<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Inscripción aceptada</title>
</head>
<body style="font-family: Arial, sans-serif; color: #333; line-height: 1.5;">
  <h2 style="color: #2563eb;">¡Tu inscripción fue aceptada!</h2>
  <p>Hola <strong>{{ $nombre }}</strong>,</p>
  <p>Tu solicitud de inscripción al programa <strong>{{ $programa }}</strong> ha sido revisada y aceptada.</p>
  <p><strong>Estado actual:</strong> {{ $estadoActual ?? 'Factura generada' }}</p>
  <p><strong>Número de identificación:</strong> {{ $documento }}</p>
  @if($jornada)
  <p><strong>Jornada:</strong> {{ $jornada }}</p>
  @endif
  <p><strong>Valor inscripción:</strong> ${{ $valorInscripcion ?? $total }} COP</p>
  <p><strong>Valor total a pagar:</strong> ${{ $total }} COP</p>
  @if($fechaLimite)
  <p><strong>Fecha límite de pago:</strong> {{ $fechaLimite }}</p>
  @endif
  <p>Puedes consultar el estado de tu inscripción, descargar la factura y completar el pago en el siguiente enlace:</p>
  <p><a href="{{ $urlPortal }}" style="background:#2563eb;color:#fff;padding:12px 20px;text-decoration:none;border-radius:6px;display:inline-block;">Consultar estado de inscripción</a></p>
  <p style="font-size:12px;color:#666;">Si el botón no funciona, copia y pega esta URL en tu navegador:<br>{{ $urlPortal }}</p>
  <p style="font-size:12px;color:#666;">También puede consultar con su tipo y número de documento en: {{ rtrim((string) env('FRONTEND_URL', 'http://localhost:5173'), '/') }}/seguimiento-inscripcion</p>
</body>
</html>
