<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('calificacionActividad', function (Blueprint $table) {
            // Agregar la columna (bigint unsigned, nullable o no según tu lógica)
            $table->unsignedBigInteger('idValoracion')->nullable()->after('idCorte');

            // Agregar la clave foránea
            $table->foreign('idValoracion')
                  ->references('id')
                  ->on('valoracion'); 
        });
    }

    public function down()
    {
        Schema::table('calificacionActividad', function (Blueprint $table) {
            $table->dropForeign(['idValoracion']);
            $table->dropColumn('idValoracion');
        });
    }
};