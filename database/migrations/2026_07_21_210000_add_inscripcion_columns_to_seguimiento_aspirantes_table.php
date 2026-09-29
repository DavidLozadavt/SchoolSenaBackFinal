<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 2 del seguimiento de aspirantes: link único de inscripción + estado
 * documental, independiente del estado de WhatsApp (columna `estado`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seguimientoAspirantes', function (Blueprint $table) {
            if (!Schema::hasColumn('seguimientoAspirantes', 'tokenPublico')) {
                $table->uuid('tokenPublico')->nullable()->unique()->after('id');
            }
            if (!Schema::hasColumn('seguimientoAspirantes', 'estadoDocumental')) {
                // link_enviado, formulario_iniciado, formulario_enviado,
                // documentacion_completa, documentacion_incompleta,
                // pendiente_revision, aprobado, rechazado, correccion_solicitada
                $table->string('estadoDocumental')->nullable()->after('estado');
            }
            if (!Schema::hasColumn('seguimientoAspirantes', 'fechaFormularioEnviado')) {
                $table->dateTime('fechaFormularioEnviado')->nullable()->after('fechaRespuesta');
            }
        });
    }

    public function down(): void
    {
        Schema::table('seguimientoAspirantes', function (Blueprint $table) {
            foreach (['tokenPublico', 'estadoDocumental', 'fechaFormularioEnviado'] as $col) {
                if (Schema::hasColumn('seguimientoAspirantes', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
