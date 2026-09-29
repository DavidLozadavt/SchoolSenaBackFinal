<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega las columnas de seguimiento de WhatsApp a seguimiento_aspirantes.
 *
 * La migración original ya estaba ejecutada; NO se edita. Esta migración ALTER
 * agrega las columnas que el código realmente usa (webhook + campañas).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seguimiento_aspirantes', function (Blueprint $table) {
            if (!Schema::hasColumn('seguimiento_aspirantes', 'fecha_respuesta')) {
                $table->dateTime('fecha_respuesta')->nullable()->after('respuesta');
            }
            if (!Schema::hasColumn('seguimiento_aspirantes', 'wa_message_id')) {
                $table->string('wa_message_id')->nullable()->after('fecha_respuesta');
            }
            if (!Schema::hasColumn('seguimiento_aspirantes', 'estado_envio')) {
                $table->string('estado_envio')->nullable()->after('wa_message_id'); // sent/delivered/read/failed
            }
            if (!Schema::hasColumn('seguimiento_aspirantes', 'error_envio')) {
                $table->string('error_envio')->nullable()->after('estado_envio');
            }
        });
    }

    public function down(): void
    {
        Schema::table('seguimiento_aspirantes', function (Blueprint $table) {
            foreach (['fecha_respuesta', 'wa_message_id', 'estado_envio', 'error_envio'] as $col) {
                if (Schema::hasColumn('seguimiento_aspirantes', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
