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


class GenerateAndSendPdf implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $company;
    protected $prestacionServicio;
    protected $factura;
    protected $pago;
    protected $transaccion;
    protected $tercero;
    protected $caja;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($company, $prestacionServicio, $factura, $pago, $transaccion, $tercero, $caja)
    {
        $this->company = $company;
        $this->prestacionServicio = $prestacionServicio;
        $this->factura = $factura;
        $this->pago = $pago;
        $this->transaccion = $transaccion;
        $this->tercero = $tercero;
        $this->caja = $caja;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $pdf = FacadePdf::loadView('email-bill-service', [
            'company' => $this->company,
            'prestacionServicio' => $this->prestacionServicio,
            'detalles' => $this->prestacionServicio->detalles,
            'factura' => $this->factura,
            'pago' => $this->pago,
            'transaccion' => $this->transaccion,
            'tercero' => $this->tercero,
            'caja' => $this->caja
        ])->setPaper('legal', 'portrait');

        $pdfName = 'factura_' . time() . '.pdf';
        $pdfPath = 'storage/facturas/' . $pdfName;
        $fullPdfPath = storage_path('app/public/facturas/' . $pdfName);


        $pdf->save($fullPdfPath);

        $this->factura->fotoFactura = $pdfPath;
        $this->factura->save();

        $subject = 'Confirmación de Pago Exitoso';
        $mensaje = 'Estimado/a ' . $this->tercero->nombre . ",\n\n" .
            "Nos complace informarle que su pago ha sido procesado con éxito. A continuación, encontrará los detalles de su transacción:\n\n" .
            "Número de Transacción: " . $this->transaccion->numFacturaInicial . "\n" .
            "Fecha y Hora: " .  $this->pago->fechaReg . "\n" .
            "Adjunto a este correo encontrará su factura en formato PDF. Por favor, guarde este documento para sus registros.\n\n" .
            "Gracias por su preferencia.\n\n" .
            "Saludos cordiales\n";
            
        $mailService = new MailBillService($subject, $mensaje, $this->company, $fullPdfPath);
        Mail::to($this->tercero->email)->send($mailService);
    }
}