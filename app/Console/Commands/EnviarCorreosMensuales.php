<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use App\Mail\MailService;
use App\Models\Pago;
use Illuminate\Support\Facades\Log;

class EnviarCorreosMensuales extends Command
{
    protected $signature = 'correo:enviar';

    protected $description = 'Envía correos para pagos con idEstado 4 del mes actual';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        $mesActual = now()->format('m');
        $correoEnviado = false;

        $pagos = Pago::whereYear('fechaPago', now()->year)
            ->whereMonth('fechaPago', $mesActual)
            ->where('idEstado', 4)
            ->get();

        if ($pagos->isEmpty()) {
            $this->info('No hay pagos pendientes para enviar correos este mes.');
            return;
        }

        foreach ($pagos as $pago) {
            if (!$correoEnviado) {

                $subject = "Procesos de Pagos Abiertos";

                $message = "Estimado/a Gerente de Virtual Technology,\n\n";
                $message .= "Quisiera recordarle que los procesos de pagos ya se encuentran abiertos en la aplicación para su revisión y aprobación. La fecha programada para los pagos es el " . $pago->fechaPago . ".\n";
                $message .= "Por favor, dedique un momento para revisar y aprobar los pagos pendientes en la aplicación lo antes posible.\n\n";
           
                $mailService = new MailService($subject, $message);
                Mail::to('gerente@virtualt.org')->send($mailService);

                $correoEnviado = true;

                Log::info('Se envió un correo informativo para el pago con idEstado 4 en el mes actual.');
                $this->info('Correo informativo enviado para el pago con idEstado 4 en el mes actual.');
            }
        }

        if (!$correoEnviado) {
            $this->info('No se envió correo porque ya se ha enviado este mes.');
        }

        $this->info('Se ha completado el envío de correos informativos para los pagos con idEstado 4 del mes actual.');
    }
}
