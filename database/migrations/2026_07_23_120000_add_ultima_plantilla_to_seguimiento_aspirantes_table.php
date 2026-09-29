<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Columna aditiva para el módulo de Estadísticas de Mensajes WhatsApp.
 * Registra qué plantilla se usó en el último envío — hoy el sistema solo
 * envía SeguimientoAspiranteController::PLANTILLA_OFICIAL, pero se deja
 * preparado para múltiples plantillas sin tocar el flujo de envío.
 * No crea tabla nueva: reutiliza el snapshot existente por aspirante.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seguimientoAspirantes', function (Blueprint $table) {
            if (!Schema::hasColumn('seguimientoAspirantes', 'ultimaPlantilla')) {
                $table->string('ultimaPlantilla')->nullable()->after('waMessageId');
            }
        });
    }

    public function down(): void
    {
        Schema::table('seguimientoAspirantes', function (Blueprint $table) {
            if (Schema::hasColumn('seguimientoAspirantes', 'ultimaPlantilla')) {
                $table->dropColumn('ultimaPlantilla');
            }
        });
    }
};
