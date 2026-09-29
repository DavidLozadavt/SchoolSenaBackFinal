<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('faseProyectoMaterias', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('idFaseProyectoRap');
            $table->unsignedBigInteger('idMateria');
            $table->timestamps();

            $table->foreign('idFaseProyectoRap')
                ->references('id')
                ->on('faseProyectoRap');

            $table->foreign('idMateria')
                ->references('id')
                ->on('materia');
        });
    }

    public function down()
    {
        Schema::dropIfExists('faseProyectoMaterias');
    }
};