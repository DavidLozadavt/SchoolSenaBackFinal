<?php

namespace App\Jobs;

use App\Mail\MailService;
use App\Models\Board;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendAssignmentBoardNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $email;
    protected $userName;
    protected $nombreBoard;

    /**
     * Create a new job instance.
     *
     * @param string $email
     * @param string $userName
     * @param string $nombreBoard
     * @return void
     */
    public function __construct($email, $userName, $nombreBoard)
    {
        $this->email = $email;
        $this->userName = $userName;
        $this->nombreBoard = $nombreBoard;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
    
        $subject = "Asignación de Tablero";
        $message = "Querido/a {$this->userName},\n\n"
                  . "Has sido asignado al tablero: {$this->nombreBoard}.\n"
                  . "Por favor, revisa tus asignaciones.\n\n"
                  . "Atentamente,\n"
                  . "El equipo de Virtual Technology";

        
        $mailService = new MailService($subject, $message);
        Mail::to($this->email)->send($mailService);
    }
}
