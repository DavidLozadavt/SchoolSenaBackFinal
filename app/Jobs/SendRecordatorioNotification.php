<?php

namespace App\Jobs;

use App\Mail\MailService;
use App\Models\CardDetail;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;


class SendRecordatorioNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $user;
    protected $cardDetail;

    /**
     * Create a new job instance.
     *
     * @param User $user
     * @param CardDetail $cardDetail
     * @return void
     */
    public function __construct(User $user, CardDetail $cardDetail)
    {
        $this->user = $user;
        $this->cardDetail = $cardDetail;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $configuracion = $this->cardDetail->configuracion;
    
        if (!$configuracion) {
            Log::error("Configuración no encontrada para el CardDetail ID: {$this->cardDetail->id}");
            return;
        }
    
        $valorMinutos = $configuracion->valorMinutos;
    
        $fechaHora = Carbon::parse($this->cardDetail->fechaFinal . ' ' . $this->cardDetail->hora);
        $horaEnvio = $fechaHora->copy()->subMinutes($valorMinutos);
    
        $toleranciaSegundos = 60;
        $ahora = Carbon::now();
    
        if ($ahora->greaterThanOrEqualTo($horaEnvio) && $ahora->lessThan($horaEnvio->copy()->addSeconds($toleranciaSegundos))) {
            $fechaFinal = $this->cardDetail->fechaFinal ? $this->cardDetail->fechaFinal : 'no especificada';
            $horaFinal = $this->cardDetail->hora ? $this->cardDetail->hora : 'no especificada';
            
            $subject = 'Recordatorio: Fecha Próxima a Vencer';
            $message = "Estimado usuario {$this->user->email},\n\n"
                     . "Le recordamos que la fecha asignada al espacio de trabajo '{$this->cardDetail->card->titulo}' está próxima a vencer.\n";
    
            if ($this->cardDetail->fechaFinal || $this->cardDetail->hora) {
                $message .= "La fecha final es: {$fechaFinal} a las {$horaFinal}.\n";
            }
    
            $message .= "\nPor favor, revise la situación y tome las medidas necesarias.\n\n"
                      . "Atentamente,\n"
                      . "El equipo de Virtual Technology";
    
            $mailService = new MailService($subject, $message);
            Mail::to($this->user->email)->send($mailService);
    
            $this->cardDetail->estado = 'POR VENCER';
            $this->cardDetail->save();
    
            Log::info("Correo enviado a {$this->user->email} y estado de CardDetail actualizado a 'POR VENCER'");
        } else {
            Log::info("No es tiempo de enviar el correo para el CardDetail ID: {$this->cardDetail->id} o ya ha sido enviado.");
        }
    }
    
}
