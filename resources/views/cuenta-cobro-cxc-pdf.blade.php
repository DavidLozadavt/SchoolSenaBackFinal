<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cuenta de Cobro</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: #333;
            line-height: 1.6;
            padding: 40px;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
            background: #fff;
        }

        /* Header Section */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            padding-bottom: 30px;
            border-bottom: 3px solid #2563eb;
            margin-bottom: 30px;
        }

        .company-info {
            flex: 1;
        }

        .company-name {
            font-size: 28px;
            font-weight: 700;
            color: #1e40af;
            margin-bottom: 8px;
        }

        .company-details {
            font-size: 12px;
            color: #666;
            line-height: 1.8;
        }

        .document-info {
            text-align: right;
            background: #f8fafc;
            padding: 20px;
            border-radius: 8px;
            border-left: 4px solid #2563eb;
        }

        .document-title {
            font-size: 20px;
            font-weight: 700;
            color: #1e40af;
            margin-bottom: 10px;
        }

        .document-number {
            font-size: 14px;
            color: #666;
            margin-bottom: 5px;
        }

        .document-date {
            font-size: 13px;
            color: #888;
        }

        /* Client Section */
        .client-section {
            background: #f8fafc;
            padding: 25px;
            border-radius: 8px;
            margin-bottom: 30px;
        }

        .section-title {
            font-size: 14px;
            font-weight: 700;
            color: #1e40af;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 15px;
            padding-bottom: 8px;
            border-bottom: 2px solid #e2e8f0;
        }

        .client-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }

        .client-item {
            font-size: 13px;
        }

        .client-label {
            font-weight: 600;
            color: #475569;
            margin-bottom: 3px;
        }

        .client-value {
            color: #64748b;
        }

        /* Items Table */
        .items-section {
            margin-bottom: 30px;
        }

        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }

        .items-table thead {
            background: #1e40af;
            color: white;
        }

        .items-table th {
            padding: 12px;
            text-align: left;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .items-table tbody tr {
            border-bottom: 1px solid #e2e8f0;
        }

        .items-table tbody tr:hover {
            background: #f8fafc;
        }

        .items-table td {
            padding: 15px 12px;
            font-size: 13px;
            color: #475569;
        }

        .items-table td:first-child {
            text-align: center;
            font-weight: 600;
            color: #1e40af;
        }

        .items-table td:nth-child(3),
        .items-table td:nth-child(4) {
            text-align: right;
        }

        /* Summary Section */
        .summary-section {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 40px;
        }

        .summary-box {
            width: 350px;
            background: #f8fafc;
            border-radius: 8px;
            overflow: hidden;
            border: 1px solid #e2e8f0;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 20px;
            font-size: 13px;
        }

        .summary-row:not(:last-child) {
            border-bottom: 1px solid #e2e8f0;
        }

        .summary-label {
            color: #475569;
            font-weight: 600;
        }

        .summary-value {
            color: #64748b;
            font-weight: 600;
        }

        .summary-total {
            background: #1e40af;
            color: white;
            font-size: 16px;
            font-weight: 700;
        }

        .summary-total .summary-label,
        .summary-total .summary-value {
            color: white;
        }

        /* Payment Info */
        .payment-info {
            background: #fef3c7;
            border-left: 4px solid #f59e0b;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 40px;
        }

        .payment-title {
            font-size: 14px;
            font-weight: 700;
            color: #92400e;
            margin-bottom: 12px;
        }

        .payment-details {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
            font-size: 12px;
        }

        .payment-item {
            color: #78350f;
        }

        .payment-label {
            font-weight: 600;
        }

        /* Signature Section */
        .signature-section {
            margin-top: 60px;
            display: flex;
            justify-content: space-between;
            gap: 40px;
        }

        .signature-box {
            flex: 1;
            text-align: center;
        }

        .signature-line {
            border-top: 2px solid #333;
            margin-bottom: 8px;
            padding-top: 60px;
        }

        .signature-name {
            font-size: 13px;
            font-weight: 700;
            color: #1e40af;
            margin-bottom: 3px;
        }

        .signature-id {
            font-size: 11px;
            color: #666;
            margin-bottom: 2px;
        }

        .signature-role {
            font-size: 11px;
            color: #888;
        }

        /* Footer */
        .footer {
            margin-top: 50px;
            padding-top: 20px;
            border-top: 2px solid #e2e8f0;
            text-align: center;
            font-size: 11px;
            color: #94a3b8;
        }

        /* Notes */
        .notes-section {
            background: #f1f5f9;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
            border-left: 4px solid #64748b;
        }

        .notes-title {
            font-size: 13px;
            font-weight: 700;
            color: #475569;
            margin-bottom: 10px;
        }

        .notes-text {
            font-size: 12px;
            color: #64748b;
            line-height: 1.6;
        }
    </style>
</head>

<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <div class="company-info">
                <div class="company-name">VIRTUAL TECHNOLOGY</div>
                <div class="company-details">
                    Prestación de Servicios de TI<br>
                    NIT: 10296037-9<br>
                    Cel: 3156614275<br>
                    Popayán, Cauca - Colombia
                </div>
            </div>
            <div class="document-info">
                <div class="document-title">CUENTA DE COBRO</div>
                <div class="document-number">No. CXC-{{ str_pad($transaccion->id ?? '001', 6, '0', STR_PAD_LEFT) }}</div>
                <div class="document-date">Fecha: {{ date('d/m/Y') }}</div>
            </div>
        </div>

        <!-- Client Information -->
        <div class="client-section">
            <div class="section-title">Información del Cliente</div>
            <div class="client-grid">
                <div class="client-item">
                    <div class="client-label">Cliente:</div>
                    <div class="client-value">{{ $tercero->nombre ?? 'N/A' }}</div>
                </div>
                <div class="client-item">
                    <div class="client-label">NIT/CC:</div>
                    <div class="client-value">{{ $tercero->identificacion ?? 'N/A' }}</div>
                </div>
                <div class="client-item">
                    <div class="client-label">Teléfono:</div>
                    <div class="client-value">{{ $tercero->telefono ?? 'N/A' }}</div>
                </div>
                <div class="client-item">
                    <div class="client-label">Email:</div>
                    <div class="client-value">{{ $tercero->email ?? 'N/A' }}</div>
                </div>
            </div>
        </div>

        <!-- Items Table -->
        <div class="items-section">
            <div class="section-title">Detalle de Servicios</div>
            <table class="items-table">
                <thead>
                    <tr>
                        <th style="width: 80px;">CANT.</th>
                        <th>DESCRIPCIÓN</th>
                        <th style="width: 120px;">VALOR UNIT.</th>
                        <th style="width: 120px;">VALOR TOTAL</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>1</td>
                        <td>
                            Servicios de Tecnología según factura No. {{ $transaccion->numFacturaInicial ?? 'N/A' }}<br>
                            <small style="color: #94a3b8;">Período: {{ date('d/m/Y', strtotime($transaccion->fechaTransaccion ?? 'now')) }}</small>
                        </td>
                        <td>${{ number_format($transaccion->valor ?? 0, 0, ',', '.') }}</td>
                        <td>${{ number_format($transaccion->valor ?? 0, 0, ',', '.') }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Summary -->
        <div class="summary-section">
            <div class="summary-box">
                <div class="summary-row">
                    <span class="summary-label">Subtotal:</span>
                    <span class="summary-value">${{ number_format($transaccion->valor ?? 0, 0, ',', '.') }}</span>
                </div>
                <div class="summary-row">
                    <span class="summary-label">IVA (0%):</span>
                    <span class="summary-value">$0</span>
                </div>
                <div class="summary-row summary-total">
                    <span class="summary-label">TOTAL A PAGAR:</span>
                    <span class="summary-value">${{ number_format($transaccion->valor ?? 0, 0, ',', '.') }} COP</span>
                </div>
            </div>
        </div>

        <!-- Payment Information -->
        <div class="payment-info">
            <div class="payment-title">📌 Información para Pago</div>
            <div class="payment-details">
                <div class="payment-item">
                    <span class="payment-label">Banco:</span> Bancolombia
                </div>
                <div class="payment-item">
                    <span class="payment-label">Tipo de Cuenta:</span> Ahorros
                </div>
                <div class="payment-item">
                    <span class="payment-label">Número de Cuenta:</span> XXXX-XXXX-XXXX
                </div>
                <div class="payment-item">
                    <span class="payment-label">Titular:</span> David Eduardo Lozada Cerón
                </div>
            </div>
        </div>

        <!-- Notes -->
        <div class="notes-section">
            <div class="notes-title">Notas Importantes:</div>
            <div class="notes-text">
                • Esta cuenta de cobro debe ser cancelada dentro de los próximos 30 días calendario.<br>
                • Por favor enviar comprobante de pago al correo: pagos@virtualt.org<br>
                • Para cualquier aclaración, contactar al teléfono 3156614275
            </div>
        </div>

        <!-- Signature -->
        <div class="signature-section">
            <div class="signature-box">
                <div class="signature-line"></div>
                <div class="signature-name">DAVID EDUARDO LOZADA CERÓN</div>
                <div class="signature-id">C.C. 10296037 de Popayán</div>
                <div class="signature-role">Gerente - Virtual Technology</div>
            </div>
            <div class="signature-box">
                <div class="signature-line"></div>
                <div class="signature-name">RECIBÍ CONFORME</div>
                <div class="signature-id">{{ $tercero->nombre ?? 'Cliente' }}</div>
                <div class="signature-role">Firma y Sello</div>
            </div>
        </div>

        <!-- Footer -->
        <div class="footer">
            Virtual Technology - Soluciones Tecnológicas Integrales<br>
            www.virtualt.org | contacto@virtualt.org
        </div>
    </div>
</body>

</html>