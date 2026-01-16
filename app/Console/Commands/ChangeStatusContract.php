<?php

namespace App\Console\Commands;

use App\Models\Contract;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ChangeStatusContract extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'change:status';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cambia el estado de un contrato a inactivo';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $contratos = Contract::where('idEstado', 1)->get();

        foreach ($contratos as $contrato) {
            if ($contrato->fechaFinalContrato !== null && Carbon::now() > $contrato->fechaFinalContrato) {
                $contrato->idEstado = 2;
                $contrato->save();
                $this->info("El contrato {$contrato->id} ha sido actualizado.");
            }
        }

        $this->info('¡Todos los contratos han sido actualizados!');
  
    }
}
