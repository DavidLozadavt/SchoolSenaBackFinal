<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Formulario de documentos que deben diligenciar los aspirantes que
 * responden SI por WhatsApp. Se asigna una sola vez desde TelecomConfig.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('telecomConfigs', function (Blueprint $table) {
            if (!Schema::hasColumn('telecomConfigs', 'idFormularioInscripcion')) {
                $table->unsignedBigInteger('idFormularioInscripcion')->nullable()->after('webhookUrl');
                $table->foreign('idFormularioInscripcion')->references('id')->on('formularios')->onDelete('set null');
            }
        });
    }

    public function down(): void
    {
        Schema::table('telecomConfigs', function (Blueprint $table) {
            if (Schema::hasColumn('telecomConfigs', 'idFormularioInscripcion')) {
                $table->dropForeign(['idFormularioInscripcion']);
                $table->dropColumn('idFormularioInscripcion');
            }
        });
    }
};
