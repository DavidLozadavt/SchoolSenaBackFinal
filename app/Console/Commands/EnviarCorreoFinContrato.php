<?php

namespace App\Console\Commands;

use App\Mail\MailService;
use App\Models\Contract;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class EnviarCorreoFinContrato extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'correo:finalizacion';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Envia un correo 15 dias antes de terminar un contrato';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $contratos = Contract::with('persona')->where('idtipoContrato', '!=', 6)->get();
        $nadieParaNotificar = true;
    
        foreach ($contratos as $contrato) {
            $personaRelacionada = $contrato->persona;
            $fechaFinalContrato = Carbon::parse($contrato->fechaFinalContrato);
            $fechaActual = Carbon::now();
            $fechaLimiteNotificacion = $fechaActual->copy()->addDays(15);
    
            if ($fechaFinalContrato->diffInDays($fechaLimiteNotificacion) === 0) {
                $nadieParaNotificar = false;
    
                $this->info("Enviando notificación a: {$personaRelacionada->email}");
    
                $subject = "Finalización de Contrato.";
    
                $message = "Estimado(a) {$personaRelacionada->nombre1},\n\n";
                $message .= "Queremos expresar nuestro agradecimiento por tu compromiso y tu tiempo en nuestra empresa. ";
                $message .= "Lamentablemente, te informamos que tu contrato finalizará en 15 días, ";
                $message .= "El día: {$fechaFinalContrato->format('Y-m-d')}\n\n"; // Corrección aquí
                $message .= "Apreciamos tu contribución y estamos disponibles para cualquier consulta que puedas tener. ";
                $message .= "Te agradecemos por tu dedicación durante tu estadía en nuestra empresa.\n\n";
                $message .= "Atentamente,\n";
                $message .= "El equipo de Virtual Technology.\n\n";
    
            
                $mailService = new MailService($subject, $message);
                Mail::to($personaRelacionada->email)->send($mailService);
            }
        }
        Log::info('Se envió un correo informativo para el pago con idEstado 4 en el mes actual.');
    
        if ($nadieParaNotificar) {
            $this->info("No hay nadie cuyo contrato esté a punto de finalizar en 15 días.");
        }
    
        return 0;
    }
    
     
}
