<?php

namespace App\Console;

use App\Jobs\CreateMonthlyPayrollsJob;
use App\Jobs\CreateVacationJob;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        \App\Console\Commands\EnviarCorreosMensuales::class,
        \App\Console\Commands\PagosContratoIndefinido::class,
        \App\Console\Commands\EnviarCorreoFinContrato::class,
        \App\Console\Commands\CorreoInformativoPago::class,
        \App\Console\Commands\ChangeStatusContract::class,
        \App\Console\Commands\SendEmailEndPrueba::class,
        \App\Console\Commands\StoreDateConfigurationCard::class,
        \App\Console\Commands\SendEmailEndEvent::class,
        \App\Console\Commands\SendEmailEndCheckItem::class,
        \App\Console\Commands\SendNotificationSupport::class,
        \App\Console\Commands\SendNotificationVacacionNomina::class,
        \App\Console\Commands\StorePagoAdministracion::class,
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // $schedule->command('correo:enviar')->monthlyOn(25, '00:00');
        // $schedule->command('pagos:indefinidos')->monthlyOn(25, '01:00');
        // $schedule->command('correo:finalizacion')->dailyAt('8:00')->withoutOverlapping();
        // $schedule->command('correo:informativo')->monthlyOn(25, '02 :00');
        // $schedule->command('change:status')->dailyAt('3:00')->withoutOverlapping();
        // $schedule->command('end:plan')->dailyAt('5:00')->withoutOverlapping();

        // $schedule->command('date-configuration:card')->everyMinute();
        // $schedule->command('end-card:email')->everyMinute();
        // $schedule->command('end:check-email')->everyMinute();
        // $schedule->command('notification:support')->everyMinute();
        $schedule->command('notification-vacations:nomina')->dailyAt('6:00')->withoutOverlapping();

        // $schedule->job(new CreateMonthlyPayrollsJob())->everyMinute();
        // $schedule->job(new CreateVacationJob())->dailyAt('12:00');
        // $schedule->job(new \App\Jobs\EnviarFacturasPendientesJob)->everyFiveMinutes();
        $schedule->command('invoices:retry-failed')->hourly();
        $schedule->command('store:pago-administracion')->dailyAt('7:00')->withoutOverlapping();

        // Registrar inasistencias automáticas para clases que ya terminaron
        $schedule->command('asistencia:registrar-inasistencias')->everyThirtyMinutes()->withoutOverlapping();

        //crear copia local de la base de datos
        $schedule->command('backup:run --only-db --disable-notifications')
            ->dailyAt('02:00')
            ->withoutOverlapping();

        // Mueve la copia local a Google Drive y limpia los locales
        $schedule->command('backup:move-to-google')
            ->dailyAt('02:05')
            ->withoutOverlapping();
    }


    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
