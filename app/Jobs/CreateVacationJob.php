<?php

namespace App\Jobs;

use App\Enums\StatusVacaciones;
use App\Models\Contract;
use App\Models\Nomina\Vacacion;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CreateVacationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $now = Carbon::now();

        Contract::where('idEstado', 1)
            ->whereIn('idtipoContrato', [6, 7, 8])
            ->whereDate('fechaFinalContrato', $now->addDay()->toDateString())
            ->chunkById(100, function ($contracts) use ($now) {
                foreach ($contracts as $contract) {
                    Vacacion::create([
                        'periodo'     => $now->year - 1,
                        'estado'      => StatusVacaciones::PENDIENTE,
                        'idContrato'  => $contract->id,
                        'idSolicitud' => null,
                    ]);
                }
            });

        Log::info("Job ejecutado para crear vacaciones al mediodía de {$now->toDateTimeString()}.");
    }
}
