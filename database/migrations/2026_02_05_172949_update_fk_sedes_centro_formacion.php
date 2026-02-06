<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('sedes', function (Blueprint $table) {

            // 1. Eliminar la FK anterior
            $table->dropForeign(['idCentroFormacion']);

            // 2. Volver a crear la FK con el nuevo nombre de tabla
            $table->foreign('idCentroFormacion')
                  ->references('id')
                  ->on('centroFormacion')
                  ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('sedes', function (Blueprint $table) {

            // Revertir cambios
            $table->dropForeign(['idCentroFormacion']);

            $table->foreign('idCentroFormacion')
                  ->references('id')
                  ->on('centroFormacion')
                  ->onDelete('set null');
        });
    }
};
