<?php

namespace App\Console\Commands;

use App\Models\FacturaElectronica;
use App\Services\FactusClient;
use Illuminate\Console\Command;

class RetryFailedInvoices extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'factus:retry-failed-invoices 
                            {--status=* : Estados a reintentar (error, pending)}
                            {--limit=10 : Número máximo de facturas a procesar}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reintentar facturas fallidas o pendientes en Factus';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $statuses = $this->option('status') ?: ['error', 'pending'];
        $limit = (int) $this->option('limit');

        $this->info("🔄 Buscando facturas con estado: " . implode(', ', $statuses));

        $facturas = FacturaElectronica::whereIn('status', $statuses)
            ->whereHas('ticket')
            ->limit($limit)
            ->get();

        if ($facturas->isEmpty()) {
            $this->info("✅ No se encontraron facturas para reintentar");
            return Command::SUCCESS;
        }

        $this->info("📋 Se encontraron {$facturas->count()} facturas para reintentar");

        $bar = $this->output->createProgressBar($facturas->count());
        $bar->start();

        foreach ($facturas as $factura) {
            $ticket = $factura->ticket;
            
            if (!$ticket) {
                $this->newLine();
                $this->warn("⚠️ Factura {$factura->id} sin ticket asociado");
                continue;
            }

            // Eliminar la factura fallida para que se cree una nueva
            $factura->delete();

            // Despachar el job con delay para evitar sobrecarga
            dispatch(new \App\Jobs\CreateInvoiceJob($ticket))->delay(now()->addSeconds(5));
            
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("✅ Se despacharon {$facturas->count()} jobs para reintentar facturas");
        $this->info("💡 Revisa el log de Laravel para ver el progreso");

        return Command::SUCCESS;
    }

}
