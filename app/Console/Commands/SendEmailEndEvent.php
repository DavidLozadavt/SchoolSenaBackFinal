<?php

namespace App\Console\Commands;

use App\Jobs\SendBasicEmail;
use App\Mail\MailService;
use App\Models\CardDetail;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendEmailEndEvent extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'end-card:email';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Envia un email cuando se cumple el limite de la tarjeta' ;

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $now = now(); // Obtiene la fecha y hora actuales
    
        $cardDetails = CardDetail::where('completado', 0)->get();
    
        foreach ($cardDetails as $cardDetail) {
            $fechaFinal = $cardDetail->fechaFinal;
            $horaFinal = $cardDetail->hora;
    
        
            $fechaHoraFinal = \Carbon\Carbon::parse("{$fechaFinal} {$horaFinal}");
    
       
            if ($now->isSameMinute($fechaHoraFinal)) {
                $card = $cardDetail->card;
                $members = $card->members;
    
                foreach ($members as $member) {
                    $user = $member->user;
    
                    if ($user) {
                        $email = $user->email;
                        $subject = 'Recordatorio: Fecha Ha Vencido';
                        $message = "Estimado usuario {$user->email},\n\n"
                                 . "Le recordamos que la fecha asignada al espacio de trabajo '{$card->titulo}' ha vencido.\n"
                                 . "La fecha final era: {$fechaFinal} a las {$horaFinal}.\n"
                                 . "\nPor favor, revise la situación y tome las medidas necesarias.\n\n"
                                 . "Atentamente,\n"
                                 . "El equipo de Virtual Technology";
    
                        SendBasicEmail::dispatch($email, $subject, $message);
                    }
                }
            }
        }
    }
    
}
