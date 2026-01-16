<?php

namespace App\Jobs;

use App\Mail\MailBillService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Barryvdh\DomPDF\Facade\Pdf as FacadePdf;
use Illuminate\Support\Facades\Mail;

class SendEmailToResponsable implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $company;
    protected $pago;
    protected $transaccion;
    protected $persona;
    protected $personaAuth;
    protected $prestaciones;
    /**
     * Create a new job instance.
     */
    public function __construct($company, $pago, $transaccion, $persona, $personaAuth, $prestaciones)
    {
        $this->company = $company;
        $this->pago = $pago;
        $this->transaccion = $transaccion;
        $this->persona = $persona;
        $this->personaAuth = $personaAuth;
        $this->prestaciones = $prestaciones;
    }



    /**
     * Execute the job.
     */
    public function handle()
    {
        $pdf = FacadePdf::loadView('email-bill-pago-responsable', [
            'company' => $this->company,
            'transaccion' => $this->transaccion,
            'pago' => $this->pago,
            'persona' => $this->persona,
            'personaAuth' => $this->personaAuth,
            'prestaciones' => $this->prestaciones,

        ])->setPaper('legal', 'portrait');

        $pdfName = 'pago' . time() . '.pdf';
        $pdfPath = 'storage/pagos/' . $pdfName;
        $fullPdfPath = storage_path('app/public/pagos/' . $pdfName);


        $pdf->save($fullPdfPath);

        $this->pago->rutaComprobante = $pdfPath;
        $this->pago->save();



        $subject = 'Confirmación de Pago Exitoso';
        $mensaje = 'Estimado/a ' . $this->persona->nombre1 . ",\n\n" .
            "Nos complace informarle que su pago ha sido procesado con éxito. A continuación, encontrará los detalles de su transacción:\n\n" .
            "Número de Transacción: " . $this->transaccion->numFacturaInicial . "\n" .
            "Fecha y Hora: " .  $this->pago->fechaReg . "\n" .
            "Adjunto a este correo encontrará su factura en formato PDF. Por favor, guarde este documento para sus registros.\n\n" .
            "Gracias por su preferencia.\n\n" .
            "Saludos cordiales\n";

        $mailService = new MailBillService($subject, $mensaje, $this->company, $fullPdfPath);
        Mail::to($this->persona->email)->send($mailService);
    }
}
