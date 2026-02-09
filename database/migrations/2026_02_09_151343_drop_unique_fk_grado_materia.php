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
    public function up()
    {
        Schema::table('gradoMateria', function (Blueprint $table) {

            // eliminar foreign keys
            $table->dropForeign(['idGradoPrograma']);
            $table->dropForeign(['idMateria']);

        });

        Schema::table('gradoMateria', function (Blueprint $table)
        {
            // eliminar unique
            $table->dropUnique('gradomateria_idmateria_idgradoprograma_unique');

        });

        Schema::table('gradoMateria', function (Blueprint $table)
        {
            // Volver a crear foreign keys
            $table->foreign('idGradoPrograma')->references('id')->on('gradoPrograma');
            $table->foreign('idMateria')->references('id')->on('materia');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
};
