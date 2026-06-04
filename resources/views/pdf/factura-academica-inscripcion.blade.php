<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Factura {{ $numeroFactura }}</title>
  <style>
    body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111; }
    h1 { font-size: 18px; margin-bottom: 4px; }
    table { width: 100%; border-collapse: collapse; margin-top: 16px; }
    th, td { border: 1px solid #ccc; padding: 8px; text-align: left; }
    th { background: #f3f4f6; }
    .total { font-weight: bold; font-size: 14px; }
  </style>
</head>
<body>
  <h1>Factura académica</h1>
  <p><strong>Número:</strong> {{ $numeroFactura }}</p>
  <p><strong>Fecha:</strong> {{ $fecha }}</p>
  <p><strong>Estudiante:</strong> {{ $nombreEstudiante }}</p>
  <p><strong>Documento:</strong> {{ $documento }}</p>
  <p><strong>Programa:</strong> {{ $programa }}</p>

  <table>
    <thead>
      <tr>
        <th>Concepto</th>
        <th>Valor</th>
      </tr>
    </thead>
    <tbody>
      @foreach($detalles as $d)
      <tr>
        <td>{{ $d['concepto'] }}</td>
        <td>${{ number_format($d['valor'], 0, ',', '.') }}</td>
      </tr>
      @endforeach
      <tr>
        <td class="total">Total</td>
        <td class="total">${{ number_format($total, 0, ',', '.') }}</td>
      </tr>
    </tbody>
  </table>

  @if($referenciaPago)
  <p style="margin-top:20px;"><strong>Referencia de pago:</strong> {{ $referenciaPago }}</p>
  @endif
</body>
</html>
