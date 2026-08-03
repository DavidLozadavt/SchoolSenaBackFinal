<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marca los registros que vienen de la migración inicial de datos (copiados
 * del snapshot de seguimientoAspirantes) para distinguirlos de los eventos
 * reales capturados en tiempo real por enviarWhatsApp/webhook.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_mensajes_historial', function (Blueprint $table) {
            if (!Schema::hasColumn('whatsapp_mensajes_historial', 'esMigrado')) {
                $table->boolean('esMigrado')->default(false)->after('errorDetalle');
            }
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_mensajes_historial', function (Blueprint $table) {
            if (Schema::hasColumn('whatsapp_mensajes_historial', 'esMigrado')) {
                $table->dropColumn('esMigrado');
            }
        });
    }
};
