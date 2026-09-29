<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Revierte 2026_07_23_130000: las estadísticas WhatsApp se simplificaron a
 * solo "total enviados" + desglose por plantilla (lo único que Meta cobra).
 * Los contadores de respuestas/autorespuestas quedaban sin uso.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seguimientoAspirantes', function (Blueprint $table) {
            foreach (['cantidadRespuestasAspirante', 'cantidadAutorespuestas'] as $col) {
                if (Schema::hasColumn('seguimientoAspirantes', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }

    public function down(): void
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
};
