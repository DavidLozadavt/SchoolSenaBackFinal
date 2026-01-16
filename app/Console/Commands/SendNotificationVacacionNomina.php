<?php

namespace App\Console\Commands;

use App\Jobs\SendBasicEmail;
use App\Mail\MailBillService;
use App\Mail\MailService;
use App\Models\AsignacionFacturaTransaccion;
use App\Models\Company;
use App\Models\Contract;
use App\Models\Factura;
use App\Models\Nomina\SolicitudVacacion;
use App\Models\Nomina\Vacacion;
use App\Models\Person;
use App\Models\Transaccion;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Barryvdh\DomPDF\Facade\Pdf as FacadePdf;

class SendNotificationVacacionNomina extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notification-vacations:nomina';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Envia notificaciones sobre vacaciones aceptadas';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        // $company = Company::findOrFail(1);


        $solicitudes = SolicitudVacacion::where('estado', 'ACEPTADO')
            ->whereDate('fechaEjecucion', Carbon::tomorrow()->toDateString())
            ->get();

        if ($solicitudes->isEmpty()) {
            $this->info('No hay solicitudes de vacaciones aceptadas para mañana.');
            return Command::SUCCESS;
        }

        foreach ($solicitudes as $solicitud) {
            $this->info("Procesando solicitud #{$solicitud->id}");

            $vacacion = Vacacion::where('idSolicitud', $solicitud->id)->first();
            if (!$vacacion) {
                $this->warn("No existe vacacion para la solicitud #{$solicitud->id}");
                continue;
            }

            $contrato = Contract::find($vacacion->idContrato);
            if (!$contrato) {
                $this->warn("No existe contrato con ID {$vacacion->idContrato}");
                continue;
            }

            $persona = Person::find($contrato->idpersona);
            if (!$persona) {
                $this->warn("No existe persona con ID {$contrato->idpersona}");
                continue;
            }

            $numDias = $solicitud->numDias;
            $fechaInicial = Carbon::parse($solicitud->fechaInicial)->format('d/m/Y');
            $valorFactura = $solicitud->valor;

            // $factura = new Factura();
            // $factura->valor = $valorFactura;
            // $factura->valorIva = 0;
            // $factura->valorMasIva = $valorFactura;
            // $factura->save();

            // $transaccion = new Transaccion();
            // $transaccion->valor = $valorFactura;
            // $transaccion->fechaTransaccion = Carbon::now();
            // $transaccion->save();

            // $asignacionFacturatransaccion = new AsignacionFacturaTransaccion();
            // $asignacionFacturatransaccion->idFactura = $factura->id;
            // $asignacionFacturatransaccion->idTransaccion = $transaccion->id;
            // $asignacionFacturatransaccion->save();

            // $pdf = FacadePdf::loadView('email-bill-notification', [
            //     'company'      => $company,
            //     'factura'      => $factura,
            //     'transaccion'  => $transaccion,
            //     'solicitud'    => $solicitud,
            //     'persona'      => $persona
            // ])->setPaper('legal', 'portrait');

            // $pdfName = 'factura_' . time() . '.pdf';
            // $relativePdfPath = 'storage/facturas/' . $pdfName;
            // $fullPdfPath = storage_path('app/public/facturas/' . $pdfName);

            // $directory = storage_path('app/public/facturas');
            // if (!file_exists($directory)) {
            //     mkdir($directory, 0775, true);
            // }

            // $pdf->save($fullPdfPath);

            // $factura->fotoFactura = $relativePdfPath;
            // $factura->save();

            $email = $persona->email;
            $subject = 'Vacaciones Aprobadas';
            $message = "Estimado/a {$persona->nombre1} {$persona->apellido1},\n\n"
                . "Le informamos que su solicitud de vacaciones ha sido aceptada.\n"
                . "Fecha de inicio: {$fechaInicial}\n"
                . "Número de días: {$numDias}\n"
                . "Valor de la factura: $ " . number_format($valorFactura, 0, ',', '.') . "\n\n"
                . "Por favor, coordine con su área de trabajo y tome las medidas necesarias.\n\n";

            $mailService = new MailService($subject, $message);
            Mail::to($email)->send($mailService);

            $this->info("✅ Correo enviado a {$persona->email} con factura y transacción creada para la solicitud #{$solicitud->id}");
        }

        return Command::SUCCESS;
    }
}
