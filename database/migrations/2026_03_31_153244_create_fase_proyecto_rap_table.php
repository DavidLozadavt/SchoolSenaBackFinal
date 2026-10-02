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
        Schema::create('faseProyectoRap', function (Blueprint $table) {
            $table->id();


            $table->unsignedBigInteger('idFaseProyecto');
            $table->foreign('idFaseProyecto')
                ->references('id')
                ->on('faseProyecto');
            $table->unsignedBigInteger('idMateria');
            $table->foreign('idMateria')
                ->references('id')
                ->on('materia');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('faseProyectoRap');
    }
};
