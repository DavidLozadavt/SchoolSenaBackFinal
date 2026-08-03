<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Columnas aditivas para el módulo de Estadísticas WhatsApp: cuentan
 * respuestas del aspirante y autorespuestas del bot, para poder ver el
 * total real de mensajes (enviados + recibidos + autorespuestas) y
 * estimar el volumen que Meta podría facturar. No cambian el flujo ni
 * la lógica del webhook, solo se incrementan de forma aditiva.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seguimientoAspirantes', function (Blueprint $table) {
            if (!Schema::hasColumn('seguimientoAspirantes', 'cantidadRespuestasAspirante')) {
                $table->unsignedInteger('cantidadRespuestasAspirante')->default(0)->after('cantidad_envios');
            }
            if (!Schema::hasColumn('seguimientoAspirantes', 'cantidadAutorespuestas')) {
                $table->unsignedInteger('cantidadAutorespuestas')->default(0)->after('cantidadRespuestasAspirante');
            }
        });
    }

    public function down(): void
    {
        Schema::table('seguimientoAspirantes', function (Blueprint $table) {
            foreach (['cantidadRespuestasAspirante', 'cantidadAutorespuestas'] as $col) {
                if (Schema::hasColumn('seguimientoAspirantes', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
