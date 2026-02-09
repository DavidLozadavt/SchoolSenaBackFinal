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
        Schema::table('gradoPrograma', function (Blueprint $table) {

            // eliminar foreign keys
            $table->dropForeign(['idPrograma']);
            $table->dropForeign(['idGrado']);

            // eliminar unique
            $table->dropUnique('gradoprograma_idprograma_idgrado_unique');

            // Volver a crear foreign keys
            $table->foreign('idPrograma')->references('id')->on('programa');
            $table->foreign('idGrado')->references('id')->on('grado');
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
