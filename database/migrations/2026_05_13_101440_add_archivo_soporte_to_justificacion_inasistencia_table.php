<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
     public function up(): void
    {
        Schema::table('justificacionInasistencia', function (Blueprint $table) {
            $table->string('archivoSoporte')
                  ->nullable()
                  ->after('observacion')
                  ->comment('Ruta del archivo adjunto que soporta la justificación de inasistencia');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('justificacionInasistencia', function (Blueprint $table) {
             $table->dropColumn('archivoSoporte');
        });
    }
};
