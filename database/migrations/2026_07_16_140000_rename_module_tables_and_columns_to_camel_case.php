<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Renombra a camelCase las tablas del módulo y las columnas NUEVAS de WhatsApp.
 *
 *  Tablas:  seguimiento_aspirantes -> seguimientoAspirantes
 *           telecom_configs        -> telecomConfigs
 *           whatsapp_plantillas    -> whatsappPlantillas
 *
 *  Columnas nuevas (en seguimientoAspirantes):
 *           fecha_respuesta -> fechaRespuesta
 *           wa_message_id   -> waMessageId
 *           estado_envio    -> estadoEnvio
 *           error_envio     -> errorEnvio
 *
 * Nota: en MySQL con lower_case_table_names=1 los nombres de TABLA se guardan en
 * minúsculas; Eloquent los resuelve sin distinguir mayúsculas. Las COLUMNAS sí
 * conservan camelCase. Se usa SQL crudo para renombrar columnas (Laravel 9 sin doctrine/dbal).
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1) Renombrar columnas nuevas (mientras la tabla aún tiene su nombre actual)
        $this->renameColumn('seguimiento_aspirantes', 'fecha_respuesta', 'fechaRespuesta', 'DATETIME NULL');
        $this->renameColumn('seguimiento_aspirantes', 'wa_message_id', 'waMessageId', 'VARCHAR(255) NULL');
        $this->renameColumn('seguimiento_aspirantes', 'estado_envio', 'estadoEnvio', 'VARCHAR(255) NULL');
        $this->renameColumn('seguimiento_aspirantes', 'error_envio', 'errorEnvio', 'VARCHAR(255) NULL');

        // 2) Renombrar tablas
        $this->renameTable('seguimiento_aspirantes', 'seguimientoAspirantes');
        $this->renameTable('telecom_configs', 'telecomConfigs');
        $this->renameTable('whatsapp_plantillas', 'whatsappPlantillas');
    }

    public function down(): void
    {
        $this->renameTable('seguimientoAspirantes', 'seguimiento_aspirantes');
        $this->renameTable('telecomConfigs', 'telecom_configs');
        $this->renameTable('whatsappPlantillas', 'whatsapp_plantillas');

        $this->renameColumn('seguimiento_aspirantes', 'fechaRespuesta', 'fecha_respuesta', 'DATETIME NULL');
        $this->renameColumn('seguimiento_aspirantes', 'waMessageId', 'wa_message_id', 'VARCHAR(255) NULL');
        $this->renameColumn('seguimiento_aspirantes', 'estadoEnvio', 'estado_envio', 'VARCHAR(255) NULL');
        $this->renameColumn('seguimiento_aspirantes', 'errorEnvio', 'error_envio', 'VARCHAR(255) NULL');
    }

    private function renameColumn(string $table, string $from, string $to, string $definition): void
    {
        if (Schema::hasTable($table) && Schema::hasColumn($table, $from) && !Schema::hasColumn($table, $to)) {
            DB::statement("ALTER TABLE `{$table}` CHANGE `{$from}` `{$to}` {$definition}");
        }
    }

    private function renameTable(string $from, string $to): void
    {
        if (Schema::hasTable($from) && !Schema::hasTable($to)) {
            Schema::rename($from, $to);
        }
    }
};
