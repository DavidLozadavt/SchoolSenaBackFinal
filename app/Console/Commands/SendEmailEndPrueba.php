<?php

namespace App\Console\Commands;

use App\Mail\MailService;
use App\Models\Vinculacion;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendEmailEndPrueba extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'end:plan';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     *
     * @return int
     */



    public function handle()
    {
  
        $vinculaciones = Vinculacion::with('tercero')->get();

        // Fecha actual
        $fechaActual = Carbon::now();

        foreach ($vinculaciones as $vinculacion) {
         
            $diferenciaDias = $fechaActual->diffInDays($vinculacion->fechaEstadoFinal, false);

            if ($diferenciaDias <= 15) {
                $subject = 'Fin de suscripción';
                $message = "Estimado/a Gerente,\n\n";
                $message .= "Queremos informarte que la fecha de finalización del plan contratado por {$vinculacion->tercero->nombreContacto} está próxima.\n";
                $message .= "El plan inició el {$vinculacion->fechaEstadoInicial} y está programado para finalizar el {$vinculacion->fechaEstadoFinal}.\n";
                $message .= "Por favor, te solicitamos estar atento a esta fecha y tomar las medidas necesarias.\n\n";
                $message .= "En caso de requerir su información personal, puedes contactar a {$vinculacion->tercero->nombreContacto} a través de su correo electrónico: {$vinculacion->tercero->emailContacto} o utilizando su teléfono de contacto: {$vinculacion->tercero->telefonoContacto}.\n\n";
                $message .= "Atentamente,\n";
                $message .= "El equipo de Virtual Technology";
                
                $mailService = new MailService($subject, $message);
                Mail::to('gerente@virtualt.org')->send($mailService);
                

                $message2 = "Estimado/a {$vinculacion->tercero->nombreContacto},\n\n";
                $message2 .= "Nos dirigimos a ti para informarte que la fecha de finalización de tu plan contratado con nosotros está próxima. Tu plan vence el {$vinculacion->fechaEstadoFinal}.\n";
                $message2 .= "Para recibir más información o resolver cualquier inquietud, por favor, no dudes en ponerte en contacto con nosotros.\n\n";
                $message2 .= "Puedes contactarnos vía correo electrónico a gerente@virtualt.org o llamando al teléfono +57 315 6614275.\n";
                $message2 .= "Estaremos encantados de ayudarte en lo que necesites.\n\n";
                $message2 .= "Atentamente,\n";
                $message2 .= "El equipo de Virtual Technology.";
                
                $mailService = new MailService($subject, $message2);
                Mail::to($vinculacion->tercero->emailContacto)->send($mailService);
                
                $mailService = new MailService($subject, $message2);
                Mail::to($vinculacion->tercero->email)->send($mailService);
                
            }
        }

        $this->info('¡Correos enviados correctamente!');
    }
    
}
