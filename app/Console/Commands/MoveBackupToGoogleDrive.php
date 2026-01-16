<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class MoveBackupToGoogleDrive extends Command
{
    protected $signature = 'backup:move-to-google';
    protected $description = 'Mover backups locales a Google Drive con rotación semanal y limpieza local';

    public function handle()
    {
        /**
         * CONFIGURACIÓN
         */
        // Carpeta local donde están los backups
        $backupFolder = 'VirtualTechnology';

        // Subcarpeta dentro del root de Google Drive
        // (el root ya es el ID configurado en filesystems.php)
        $driveSubFolder = 'Backups/RapidoTambo';

        // Día de la semana (Monday, Tuesday...)
        $dayOfWeek = Carbon::now()->format('l');

        // Nombre fijo por día (rotación semanal)
        $filenameInDrive = "backup_{$dayOfWeek}.zip";

        /**
         * OBTENER BACKUPS LOCALES
         */
        $localBackups = Storage::disk('local')->files($backupFolder);

        if (empty($localBackups)) {
            $this->info('No hay backups locales para mover.');
            return;
        }

        // Tomar el último backup generado
        $latestBackup = collect($localBackups)->last();
        $this->info("Último backup local: {$latestBackup}");

        /**
         * RUTA FINAL EN GOOGLE DRIVE
         */
        $pathInDrive = $driveSubFolder . '/' . $filenameInDrive;

        /**
         * ELIMINAR BACKUP ANTERIOR DEL MISMO DÍA
         */
        if (Storage::disk('google')->exists($pathInDrive)) {
            Storage::disk('google')->delete($pathInDrive);
            $this->info("Backup anterior eliminado en Google Drive: {$pathInDrive}");
        }

        /**
         * SUBIR BACKUP A GOOGLE DRIVE
         */
        Storage::disk('google')->put(
            $pathInDrive,
            Storage::disk('local')->get($latestBackup)
        );

        $this->info("Backup subido correctamente a Google Drive: {$pathInDrive}");

        /**
         * LIMPIEZA LOCAL
         * Mantener solo el último backup
         */
        foreach ($localBackups as $backup) {
            if ($backup !== $latestBackup) {
                Storage::disk('local')->delete($backup);
                $this->info("Backup local eliminado: {$backup}");
            }
        }

        $this->info('Proceso completado correctamente.');
    }
}
