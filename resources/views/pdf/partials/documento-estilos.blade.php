{{--
    Estilos compartidos de facturas y recibos en PDF (dompdf 3).
    Sin flexbox ni recursos remotos: dompdf trabaja con enable_remote = false.
--}}
<style>
    @page { margin: 22mm 16mm 20mm 16mm; }
    * { box-sizing: border-box; }
    body { margin: 0; font-family: Helvetica, Arial, sans-serif; font-size: 10pt; line-height: 1.4; color: #1e293b; }

    /* Pie fijo con paginación */
    .doc-pie { position: fixed; bottom: -12mm; left: 0; right: 0; height: 10mm; border-top: 0.5pt solid #cbd5e1; padding-top: 2mm; font-size: 7.5pt; color: #64748b; }
    .doc-pie table { width: 100%; border-collapse: collapse; }
    .doc-pie .paginas:after { content: "Página " counter(page); }

    /* Encabezado: emisor a la izquierda, documento a la derecha */
    .doc-encabezado { width: 100%; border-collapse: collapse; margin-bottom: 6mm; }
    .doc-encabezado td { vertical-align: top; padding: 0; }
    .emisor-logo { max-height: 16mm; max-width: 45mm; margin-bottom: 2mm; }
    .emisor-nombre { font-size: 13pt; font-weight: bold; color: #0f172a; margin: 0 0 1mm 0; }
    .emisor-dato { font-size: 8.5pt; color: #475569; margin: 0; }
    .doc-tipo { font-size: 15pt; font-weight: bold; letter-spacing: 0.5pt; color: #0f172a; text-transform: uppercase; margin: 0; text-align: right; }
    .doc-numero { font-size: 11pt; color: #0f172a; margin: 1mm 0 3mm 0; text-align: right; }
    .doc-meta { border-collapse: collapse; margin-left: auto; }
    .doc-meta td { padding: 0.6mm 0 0.6mm 4mm; font-size: 8.5pt; text-align: right; }
    .doc-meta .etq { color: #64748b; }
    .doc-meta .val { color: #0f172a; font-weight: bold; }
    .doc-regla { border: 0; border-top: 1.5pt solid #0f172a; margin: 0 0 6mm 0; }

    /* Estado */
    .estado { display: inline-block; padding: 0.8mm 2.5mm; border: 0.75pt solid #64748b; font-size: 7.5pt; font-weight: bold; letter-spacing: 0.4pt; text-transform: uppercase; color: #334155; }
    .estado-pagado { border-color: #15803d; color: #15803d; }
    .estado-pendiente { border-color: #b45309; color: #b45309; }

    /* Bloques de partes (cliente / detalle) */
    .partes { width: 100%; border-collapse: collapse; margin-bottom: 6mm; }
    .partes > tbody > tr > td { width: 50%; vertical-align: top; padding: 0; }
    .partes > tbody > tr > td + td { padding-left: 6mm; }
    .bloque-titulo { font-size: 7.5pt; font-weight: bold; letter-spacing: 0.6pt; text-transform: uppercase; color: #64748b; border-bottom: 0.75pt solid #cbd5e1; padding-bottom: 1mm; margin: 0 0 2mm 0; }
    .campos { width: 100%; border-collapse: collapse; }
    .campos td { padding: 0.7mm 0; font-size: 9pt; vertical-align: top; }
    .campos .etq { width: 34%; color: #64748b; }
    .campos .val { color: #0f172a; }

    /* Tabla de conceptos */
    .conceptos { width: 100%; border-collapse: collapse; margin-bottom: 4mm; }
    .conceptos thead { display: table-header-group; }
    .conceptos th { background-color: #f1f5f9; border-top: 0.75pt solid #94a3b8; border-bottom: 0.75pt solid #94a3b8; padding: 2mm 2.5mm; font-size: 7.5pt; font-weight: bold; letter-spacing: 0.4pt; text-transform: uppercase; color: #334155; text-align: left; }
    .conceptos td { border-bottom: 0.5pt solid #e2e8f0; padding: 2.2mm 2.5mm; font-size: 9pt; vertical-align: top; color: #1e293b; }
    .conceptos tr { page-break-inside: avoid; }
    .conceptos .num { text-align: right; white-space: nowrap; }
    .conceptos .centro { text-align: center; }
    .conceptos .vacio { text-align: center; color: #94a3b8; padding: 5mm; }
    .conceptos ul { margin: 0; padding-left: 4mm; }

    /* Totales */
    .resumen { width: 100%; border-collapse: collapse; margin-bottom: 6mm; page-break-inside: avoid; }
    .resumen > tbody > tr > td { vertical-align: top; padding: 0; }
    .totales { width: 100%; border-collapse: collapse; }
    .totales td { padding: 1.3mm 2.5mm; font-size: 9pt; }
    .totales .etq { color: #475569; }
    .totales .val { text-align: right; color: #0f172a; white-space: nowrap; }
    .totales .total td { border-top: 1.2pt solid #0f172a; padding-top: 2.2mm; font-size: 11pt; font-weight: bold; color: #0f172a; }

    /* Notas */
    .nota { border-left: 2pt solid #94a3b8; background-color: #f8fafc; padding: 2.5mm 3.5mm; font-size: 8.5pt; color: #334155; margin-bottom: 5mm; page-break-inside: avoid; }
    .nota strong { color: #0f172a; }
    .nota-alerta { border-left-color: #b45309; }
    .nota-ok { border-left-color: #15803d; }
    .enlace { color: #1d4ed8; word-wrap: break-word; }

    /* Firmas */
    .firmas { width: 100%; border-collapse: collapse; margin-top: 14mm; page-break-inside: avoid; }
    .firmas td { width: 50%; vertical-align: bottom; padding: 0 6mm 0 0; font-size: 8.5pt; color: #334155; }
    .firmas td + td { padding: 0 0 0 6mm; }
    .firma-imagen { max-height: 16mm; max-width: 50mm; display: block; margin-bottom: 1mm; }
    .firma-linea { border-top: 0.75pt solid #0f172a; padding-top: 1.5mm; }
    .firma-nombre { font-weight: bold; color: #0f172a; }
</style>
