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
        Schema::create('asignacionDiaJornada', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('idDia');
            $table->unsignedBigInteger('idJornada');

            $table->foreign('idDia', 'asignacionDiaJornada_dia_FK')
                ->references('id')
                ->on('dia');

            $table->foreign('idJornada', 'asignacionDiaJornada_jornadas_FK')
                ->references('id')
                ->on('jornadas');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('asignacionDiaJornada');
    }
};
