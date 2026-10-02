<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Factura Académica N° {{ $factura['numeroFactura'] ?? 'S/N' }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: Arial, sans-serif; font-size: 12px; color: #1e293b; background: #fff; }

        .page { width: 100%; max-width: 740px; margin: 0 auto; padding: 30px 35px; }

        /* ── HEADER ── */
        .header { display: table; width: 100%; margin-bottom: 24px; border-bottom: 3px solid #4f46e5; padding-bottom: 16px; }
        .header-logo { display: table-cell; vertical-align: middle; width: 50%; }
        .header-logo .company-name { font-size: 17px; font-weight: bold; color: #4f46e5; }
        .header-logo .company-sub  { font-size: 10px; color: #64748b; margin-top: 2px; }
        .header-badge { display: table-cell; vertical-align: middle; text-align: right; width: 50%; }
        .badge-box { display: inline-block; background: #4f46e5; color: #fff; padding: 8px 18px; border-radius: 6px; text-align: center; }
        .badge-box .badge-title { font-size: 13px; font-weight: bold; letter-spacing: 1px; }
        .badge-box .badge-num   { font-size: 11px; margin-top: 2px; opacity: 0.85; }

        /* ── META INFO ── */
        .meta { display: table; width: 100%; margin-bottom: 20px; }
        .meta-left, .meta-right { display: table-cell; vertical-align: top; width: 50%; }
        .meta-right { text-align: right; }
        .meta .label { font-size: 9px; font-weight: bold; color: #6366f1; text-transform: uppercase; letter-spacing: 0.5px; }
        .meta .value { font-size: 11px; color: #1e293b; margin-bottom: 6px; }

        /* ── ALUMNO ── */
        .section-title { font-size: 9px; font-weight: bold; color: #6366f1; text-transform: uppercase; letter-spacing: 0.7px; border-bottom: 1px solid #e2e8f0; padding-bottom: 4px; margin-bottom: 8px; }
        .info-box { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 12px 14px; margin-bottom: 16px; }
        .info-row { display: table; width: 100%; margin-bottom: 5px; }
        .info-row .il { display: table-cell; width: 45%; }
        .info-row .ir { display: table-cell; width: 55%; }
        .info-row .lbl { font-size: 9px; color: #94a3b8; font-weight: bold; text-transform: uppercase; }
        .info-row .val { font-size: 11px; color: #1e293b; }

        /* ── TABLE ── */
        .items-table { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        .items-table th { background: #4f46e5; color: #fff; font-size: 9px; text-transform: uppercase; letter-spacing: 0.5px; padding: 7px 10px; text-align: left; }
        .items-table th.right { text-align: right; }
        .items-table td { padding: 7px 10px; font-size: 11px; border-bottom: 1px solid #f1f5f9; color: #334155; }
        .items-table td.right { text-align: right; }
        .items-table tbody tr:nth-child(even) td { background: #f8fafc; }

        /* ── TOTALS ── */
        .totals { text-align: right; margin-bottom: 20px; }
        .totals table { margin-left: auto; min-width: 240px; }
        .totals td { padding: 4px 10px; font-size: 11px; }
        .totals td.lbl { color: #64748b; }
        .totals td.val { font-weight: bold; color: #1e293b; }
        .totals .total-row td { border-top: 2px solid #4f46e5; font-size: 13px; color: #4f46e5; padding-top: 8px; }

        /* ── STATUS ── */
        .status-bar { border-radius: 6px; padding: 10px 14px; margin-bottom: 20px; }
        .status-bar.pending { background: #fef9c3; border: 1px solid #fbbf24; }
        .status-bar.pending .st { font-size: 11px; font-weight: bold; color: #92400e; }
        .status-bar.paid    { background: #dcfce7; border: 1px solid #4ade80; }
        .status-bar.paid    .st { font-size: 11px; font-weight: bold; color: #166534; }

        /* ── FOOTER ── */
        .footer { border-top: 1px solid #e2e8f0; padding-top: 12px; text-align: center; color: #94a3b8; font-size: 9px; }
        .footer b { color: #4f46e5; }
    </style>
</head>
<body>
<div class="page">

    {{-- HEADER --}}
    <div class="header">
        <div class="header-logo">
            <div class="company-name">{{ $nombreInstitucion }}</div>
            <div class="company-sub">Sistema de Gestión Académica</div>
        </div>
        <div class="header-badge">
            <div class="badge-box">
                <div class="badge-title">FACTURA ACADÉMICA</div>
                <div class="badge-num">N° {{ $factura['numeroFactura'] ?? '—' }}</div>
            </div>
        </div>
    </div>

    {{-- META --}}
    <div class="meta">
        <div class="meta-left">
            <div class="label">Fecha de emisión</div>
            <div class="value">{{ $factura['fecha'] ? \Carbon\Carbon::parse($factura['fecha'])->format('d/m/Y') : date('d/m/Y') }}</div>
            <div class="label">Estado</div>
            <div class="value">
                @if(strtoupper($factura['estado'] ?? '') === 'PAGADO')
                    ✅ PAGADO
                @else
                    ⏳ PENDIENTE DE PAGO
                @endif
            </div>
        </div>
        <div class="meta-right">
            @if($proceso)
            <div class="label">Programa</div>
            <div class="value">{{ $proceso['nombreProceso'] ?? '' }}</div>
            @endif
        </div>
    </div>

    {{-- ALUMNO --}}
    <div class="section-title">Datos del Aspirante</div>
    <div class="info-box">
        <div class="info-row">
            <div class="il">
                <div class="lbl">Nombre completo</div>
                <div class="val">{{ $estudiante['nombreCompleto'] ?? ($factura['tercero']['nombre'] ?? '—') }}</div>
            </div>
            <div class="ir">
                <div class="lbl">Documento de identidad</div>
                <div class="val">{{ $estudiante['documento'] ?? ($factura['tercero']['identificacion'] ?? '—') }}</div>
            </div>
        </div>
        <div class="info-row">
            <div class="il">
                <div class="lbl">Correo electrónico</div>
                <div class="val">{{ $estudiante['email'] ?? ($factura['tercero']['email'] ?? '—') }}</div>
            </div>
            <div class="ir">
                <div class="lbl">Teléfono</div>
                <div class="val">{{ $estudiante['celular'] ?? ($factura['tercero']['telefono'] ?? '—') }}</div>
            </div>
        </div>
    </div>

    {{-- ITEMS --}}
    <div class="section-title">Conceptos a Pagar</div>
    <table class="items-table">
        <thead>
            <tr>
                <th style="width:50%">Concepto</th>
                <th style="width:30%">Detalle</th>
                <th class="right" style="width:20%">Valor</th>
            </tr>
        </thead>
        <tbody>
            @php $total = 0; @endphp
            @forelse($factura['detalles'] ?? [] as $d)
                @php
                    $val = (float)($d['valor'] ?? 0) * max(1, (int)($d['cantidad'] ?? 1));
                    $total += $val;
                @endphp
                <tr>
                    <td>{{ $d['concepto'] ?? $d['detalle'] ?? 'Concepto académico' }}</td>
                    <td>{{ $d['detalle'] ?? '—' }}</td>
                    <td class="right">$ {{ number_format($val, 0, ',', '.') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="3" style="text-align:center; color:#94a3b8;">Sin conceptos registrados</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    {{-- TOTALS --}}
    @php
        $valorFinal = (float)($factura['valor'] ?? 0) > 0 ? (float)($factura['valor'] ?? 0) : $total;
        $saldo = (float)($factura['saldoPendiente'] ?? $valorFinal);
    @endphp
    <div class="totals">
        <table>
            <tr>
                <td class="lbl">Subtotal:</td>
                <td class="val">$ {{ number_format($valorFinal, 0, ',', '.') }} COP</td>
            </tr>
            @if((float)($factura['valorIva'] ?? 0) > 0)
            <tr>
                <td class="lbl">IVA:</td>
                <td class="val">$ {{ number_format((float)$factura['valorIva'], 0, ',', '.') }} COP</td>
            </tr>
            @endif
            <tr class="total-row">
                <td class="lbl"><b>TOTAL A PAGAR:</b></td>
                <td class="val"><b>$ {{ number_format($saldo, 0, ',', '.') }} COP</b></td>
            </tr>
        </table>
    </div>

    {{-- STATUS --}}
    @if(strtoupper($factura['estado'] ?? '') !== 'PAGADO')
    <div class="status-bar pending">
        <div class="st">⚠ Para completar su proceso de inscripción, realice el pago del valor indicado y cargue el comprobante en el portal del aspirante.</div>
    </div>
    @else
    <div class="status-bar paid">
        <div class="st">✅ Pago registrado. Su inscripción se encuentra en proceso de aprobación final.</div>
    </div>
    @endif

    {{-- FOOTER --}}
    <div class="footer">
        <p>Documento generado automáticamente por <b>{{ $nombreInstitucion }}</b> · {{ date('d/m/Y H:i') }}</p>
        <p style="margin-top:4px">Este documento es de carácter informativo. Conserve una copia para sus registros.</p>
    </div>

</div>
</body>
</html>
