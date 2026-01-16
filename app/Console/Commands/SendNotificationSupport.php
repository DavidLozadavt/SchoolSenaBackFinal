<?php

namespace App\Console\Commands;

use App\Jobs\SendBasicEmail;
use App\Models\Company;
use App\Models\Contract;
use App\Models\Notificacion;
use App\Models\Status;
use App\Models\Support;
use App\Services\FCMService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SendNotificationSupport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notification:support';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Notifica cuando haya un nuevo soporte';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
       
        $contratos = Contract::with(['persona.usuario']) 
            ->where('idEstado', 1) 
            ->get();
    
       
        $now = now();
        $currentDate = $now->toDateString();
        $currentTime = $now->format('H:i');
    
        $supports = Support::whereDate('fechaSolicitud', '=', $currentDate)
            ->whereRaw("DATE_FORMAT(horaSolicitud, '%H:%i') = ?", [$currentTime])
            ->get();
    
        foreach ($supports as $support) {
            $this->info('Notificaciones enviadas para el soporte: ' . $support->numeroTicket);
    
        
            foreach ($contratos as $contrato) {
                $usuario = $contrato->persona->usuario;
                if ($usuario) {
                    $token = $usuario->device_token;
    
                  
                    SendBasicEmail::dispatch(
                        $usuario->email,
                        'Nuevo Soporte',
                        'Por favor revisar el número de soporte: #' . $support->numeroTicket
                    );
    


                    $notification = new Notificacion();
                    $notification->estado_id = Status::ID_ACTIVE;
                    $notification->asunto =  'Nuevo Soporte';
                    $notification->mensaje =  'Por favor revisar el número de soporte: #' . $support->numeroTicket;
                    $notification->route =  'gestion-board-list';
                    $notification->idUsuarioReceptor = $usuario->id;
                    $notification->idUsuarioRemitente =  1;
                    $notification->idEmpresa = Company::VIRTUALT;
                    $notification->idTipoNotificacion = 1;
                    $notification->fecha = Carbon::now()->toDateTimeString();
                    $notification->hora = Carbon::now()->format('H:i:s');
                    $notification->save();

                 
                    if ($token) {
                        FCMService::send(
                            'Nuevo Soporte',
                            'Por favor revisar el número de soporte: #' . $support->numeroTicket,
                            $token
                        );
                    } else {
                        $this->info('No se encontró token de dispositivo para el usuario: ' . $usuario->email);
                    }
                } else {
                    $this->info('No se encontró usuario para el contrato con ID: ' . $contrato->id);
                }
            }
        }
   
        if ($supports->isEmpty()) {
            $this->info('No hay notificaciones pendientes.');
        }
    }
    
}
