<?php

namespace App\Jobs;

use App\Mail\MailBillService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Barryvdh\DomPDF\Facade\Pdf as FacadePdf;

class GeneratePdfAndSendEmail implements ShouldQueue
{
    use Dispatchable, Queueable, SerializesModels;

    protected $company;
    protected $items;
    protected $factura;
    protected $transaccion;
    protected $tercero;
    protected $caja;
    protected $pagosEfectivo;
    protected $pagosTransferencia;

    public function __construct($company, $items, $factura, $transaccion, $tercero, $caja, $pagosEfectivo, $pagosTransferencia)
    {
        $this->company = $company;
        $this->items = $items;
        $this->factura = $factura;
        $this->transaccion = $transaccion;
        $this->tercero = $tercero;
        $this->caja = $caja;
        $this->pagosEfectivo = $pagosEfectivo;
        $this->pagosTransferencia = $pagosTransferencia;
    }

    public function handle()
    {
        $pdf = FacadePdf::loadView('email-bill-product', [
            'company' => $this->company,
            'items' => $this->items,
            'factura' => $this->factura,
            'transaccion' => $this->transaccion,
            'pagosEfectivo' => $this->pagosEfectivo,
            'pagosTransferencia' => $this->pagosTransferencia,
            'tercero' => $this->tercero,
            'caja' => $this->caja
        ])->setPaper('legal', 'portrait');

        $pdfName = 'factura_' . time() . '.pdf';
        $relativePdfPath = 'storage/facturas/' . $pdfName;
        $fullPdfPath = storage_path('app/public/facturas/' . $pdfName);

        $directory = storage_path('app/public/facturas');
        if (!file_exists($directory)) {
            mkdir($directory, 0775, true);
        }

        $pdf->save($fullPdfPath);

        $this->factura->fotoFactura = $relativePdfPath;
        $this->factura->save();

        $subject = 'Confirmación de Pago Exitoso';
        $mensaje = 'Estimado/a ' . $this->tercero->nombre . ",\n\n" .
            "Nos complace informarle que su pago ha sido procesado con éxito. A continuación, encontrará los detalles de su transacción:\n\n" .
            "Número de Transacción: " . $this->transaccion->numFacturaInicial . "\n" .
            "Fecha y Hora: " . $this->transaccion->fechaTransaccion . " " . $this->transaccion->hora . "\n" .
            "Adjunto a este correo encontrará su recibo en formato PDF. Por favor, guarde este documento para sus registros.\n\n" .
            "Gracias por su preferencia.\n\n" .
            "Saludos cordiales\n";

        $mailService = new MailBillService($subject, $mensaje, $this->company, $fullPdfPath);
        Mail::to($this->tercero->email)->send($mailService);
    }
}
