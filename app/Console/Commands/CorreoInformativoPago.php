<?php

namespace App\Console\Commands;

use App\Mail\MailService;
use App\Models\Contract;
use App\Models\ContratoTransaccion;
use App\Models\Pago;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class CorreoInformativoPago extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'correo:informativo';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Envia un correo notificando que se debe subir el documento para el pago';

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
         $currentMonth = Carbon::now()->month;
         $currentYear = Carbon::now()->year; 
     
         $contratos = Contract::with('persona')->get();
     
         foreach ($contratos as $contrato) {
             $persona = $contrato->persona;
     
             $contratoTransacciones = ContratoTransaccion::where('contrato_id', $contrato->id)->get();
     
             foreach ($contratoTransacciones as $transaccion) {
                 $pagos = Pago::where('idTransaccion', $transaccion->transaccion_id)
                     ->whereMonth('fechaPago', $currentMonth)
                     ->whereYear('fechaPago', $currentYear)
                     ->where('idEstado', 4) // Solo pagos con idEstado 4
                     ->get();
     
                 
                 if ($pagos->isNotEmpty()) {
                     $url = 'https://admin.virtualt.org/#/login';
                     $subject = "Carga de documentos para tu pago mensual.";
                     
                     $message = "Estimado(a) {$persona->nombre1},\n\n";
                     $message .= "Ya se han abierto los procesos para el inicio de tu pago mensual. Recuerda que debes subir un informe donde se evidencie tu desempeño mensual. Por favor, ingresa a $url, dirígete al apartado de gestión laboral y selecciona la opción de pagos pendientes. Luego, haz clic en el botón 'Ver' y carga el documento.\n\n";
                     $message .= "Atentamente,\n";
                     $message .= "El equipo de Virtual Technology.\n\n";
                     
                     $mailService = new MailService($subject, $message);

                     Mail::to($persona->email)->send($mailService);
                     
                     break;
                 }
             }
         }
     }
     
}
