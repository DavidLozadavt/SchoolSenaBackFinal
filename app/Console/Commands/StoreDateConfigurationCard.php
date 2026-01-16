<?php

namespace App\Console\Commands;

use App\Jobs\SendRecordatorioNotification;
use App\Mail\MailService;
use App\Models\CardDetail;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class StoreDateConfigurationCard extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'date-configuration:card';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Envía correos electrónicos basados en la configuración de las tarjetas';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $cardDetails = CardDetail::where('completado', 0)->get();

        foreach ($cardDetails as $cardDetail) {
            $card = $cardDetail->card;
            $members = $card->members;

            foreach ($members as $member) {
                $user = $member->user;

                if ($user) {
                    SendRecordatorioNotification::dispatch($user, $cardDetail);
                }
            }
        }

        $this->info('Funcion Compeltada.');
        return 0;
    }
}
