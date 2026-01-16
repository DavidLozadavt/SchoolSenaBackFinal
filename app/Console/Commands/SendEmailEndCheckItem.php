<?php

namespace App\Console\Commands;

use App\Jobs\SendBasicEmail;
use App\Models\ChecklistItem;
use Illuminate\Console\Command;

class SendEmailEndCheckItem extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'end:check-email';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Enviar email cuadno se va a cumplir el limite de fecha del checkitem';

    /**
     * Execute the console command.
     *
     * @return int
     */


     public function handle()
{
    $now = now(); // Obtiene la fecha y hora actuales

    $checkItems = ChecklistItem::where('completado', 0)->get();

    foreach ($checkItems as $checkItem) {
        // Obtener la fecha y hora desde la relación checkItemDetail
        $fechaFinal = optional($checkItem->checkItemDetail)->fechaFinal;
        $horaFinal = optional($checkItem->checkItemDetail)->hora;

        // Continuar solo si existen la fecha y hora finales
        if ($fechaFinal && $horaFinal) {
            $fechaHoraFinal = \Carbon\Carbon::parse("{$fechaFinal} {$horaFinal}");

            // Verificar si el momento actual coincide con la fecha y hora finales
            if ($now->isSameMinute($fechaHoraFinal)) {
                // Obtener la descripción directamente desde el checkItem
                $descripcionItem = $checkItem->descripcion;

                // Obtener el usuario desde la relación checkItemUser
                $user = optional($checkItem->checkItemUser)->user;

                if ($user) {
                    $email = $user->email;
                    $subject = 'Recordatorio: Fecha Ha Vencido';
                    $message = "Estimado usuario {$user->email},\n\n"
                             . "Le recordamos que la fecha asignada a la tarea: '{$descripcionItem}' ha vencido.\n"
                             . "La fecha final era: {$fechaFinal} a las {$horaFinal}.\n"
                             . "\nPor favor, revise la situación y tome las medidas necesarias.\n\n"
                             . "Atentamente,\n"
                             . "El equipo de Virtual Technology";

                    // Despachar el job para enviar el correo electrónico
                    SendBasicEmail::dispatch($email, $subject, $message);
                }
            }
        }
    }
}

}
