<?php

namespace App\Jobs;

use App\Mail\MailService;
use App\Models\Card;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendAssignmentCardNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $idUser;
    protected $idCard;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($idUser, $idCard)
    {
        $this->idUser = $idUser;
        $this->idCard = $idCard;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
      
        $user = User::with('persona')->find($this->idUser);
        if (!$user || !$user->persona) {
            return;
        }
        $email = $user->email;
        $userName = $user->persona->nombre1;
    
     
        $card = Card::find($this->idCard);
      
        if (!$card) {

            return;
        }
        $cardName = $card->titulo;
    
     
        $subject = "Asignación de un espacio de trabajo";
        $message = "Querido/a {$userName},\n\n"
                  . "Has sido asignado al area de trabajo: {$cardName}.\n"
                  . "Por favor, revisa tus asignaciones.\n\n"
                  . "Atentamente,\n"
                  . "El equipo de Virtual Technology";
    
        // Enviar el correo
        $mailService = new MailService($subject, $message);
        Mail::to($email)->send($mailService);
    }
}
